<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Batch;
use App\Models\Medicine;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Purchase → batches (stock in) → supplier balance → optional payment, in one
 * transaction. Cancellation reverses stock only if none of it has been used.
 */
class PurchaseService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly DocumentNumberService $numbers,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{supplier_id: int, supplier_invoice_no?: string|null, purchase_date: string, notes?: string|null, paid_amount?: string|float|null, payment_method_id?: int|null, items: list<array{medicine_id: int, batch_number: string, expiry_date: string, quantity: int, unit_cost: string|float, selling_price?: string|float|null, discount?: string|float|null, tax_rate?: string|float|null}>}  $data
     */
    public function create(array $data, User $user): Purchase
    {
        return DB::transaction(function () use ($data, $user) {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id']);

            $purchase = new Purchase;
            $purchase->forceFill([
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'reference_no' => $this->numbers->next(DocumentNumberService::PURCHASE),
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'notes' => $data['notes'] ?? null,
                'status' => PurchaseStatus::Received,
                'payment_status' => PaymentStatus::Unpaid,
            ])->save();

            $subtotal = $discountTotal = $taxTotal = 0;

            foreach ($data['items'] as $row) {
                $medicine = Medicine::query()->findOrFail($row['medicine_id']);
                $quantity = (int) $row['quantity'];
                $unitCost = Money::toCents($row['unit_cost']);
                $gross = $unitCost * $quantity;
                $discount = Money::toCents($row['discount'] ?? 0);

                if ($discount > $gross) {
                    throw new BusinessRuleException("Discount on {$medicine->name} exceeds the line amount.");
                }

                $taxRate = (float) ($row['tax_rate'] ?? 0);
                $tax = Money::percentOf($gross - $discount, $taxRate);
                $sellingPrice = isset($row['selling_price']) && $row['selling_price'] !== ''
                    ? Money::toCents($row['selling_price'])
                    : Money::toCents($medicine->selling_price);
                // Effective cost per unit after discount; used for profit calculations.
                $effectiveCost = (int) round(($gross - $discount) / $quantity);

                $batch = $this->inventory->receive($medicine, [
                    'batch_number' => $row['batch_number'],
                    'expiry_date' => $row['expiry_date'],
                    'purchase_price' => Money::fromCents($effectiveCost),
                    'selling_price' => Money::fromCents($sellingPrice),
                    'quantity' => $quantity,
                    'supplier_id' => $supplier->id,
                    'purchase_id' => $purchase->id,
                ], StockMovementType::Purchase, $purchase);

                $purchase->items()->forceCreate([
                    'medicine_id' => $medicine->id,
                    'batch_id' => $batch->id,
                    'batch_number' => $row['batch_number'],
                    'expiry_date' => $row['expiry_date'],
                    'quantity' => $quantity,
                    'unit_cost' => Money::fromCents($unitCost),
                    'selling_price' => Money::fromCents($sellingPrice),
                    'discount' => Money::fromCents($discount),
                    'tax_rate' => $taxRate,
                    'tax_amount' => Money::fromCents($tax),
                    'line_total' => Money::fromCents($gross - $discount + $tax),
                ]);

                // Latest purchase drives the default prices of the medicine.
                $medicine->forceFill([
                    'purchase_price' => Money::fromCents($unitCost),
                    'selling_price' => Money::fromCents($sellingPrice),
                ])->save();

                $subtotal += $gross;
                $discountTotal += $discount;
                $taxTotal += $tax;
            }

            $total = $subtotal - $discountTotal + $taxTotal;
            $paid = Money::toCents($data['paid_amount'] ?? 0);

            if ($paid > $total) {
                throw new BusinessRuleException('Paid amount cannot exceed the purchase total.');
            }

            if ($paid > 0 && empty($data['payment_method_id'])) {
                throw new BusinessRuleException('Choose a payment method for the amount paid.');
            }

            $purchase->forceFill([
                'subtotal' => Money::fromCents($subtotal),
                'discount_total' => Money::fromCents($discountTotal),
                'tax_total' => Money::fromCents($taxTotal),
                'total' => Money::fromCents($total),
                'paid_amount' => Money::fromCents($paid),
                'payment_status' => PaymentStatus::fromAmounts($total, $paid),
            ])->save();

            if ($paid > 0) {
                $payment = new Payment;
                $payment->forceFill([
                    'payment_method_id' => $data['payment_method_id'],
                    'user_id' => $user->id,
                    'direction' => PaymentType::SupplierPayment->direction(),
                    'type' => PaymentType::SupplierPayment,
                    'supplier_id' => $supplier->id,
                    'purchase_id' => $purchase->id,
                    'amount' => Money::fromCents($paid),
                    'paid_at' => now(),
                ])->save();
            }

            $supplier->forceFill([
                'balance' => Money::fromCents(Money::toCents($supplier->balance) + $total - $paid),
            ])->save();

            $this->logger->log('purchase.created', "Purchase {$purchase->reference_no} received from {$supplier->name}", $purchase, [
                'total' => $purchase->total,
                'paid' => $purchase->paid_amount,
                'items' => count($data['items']),
            ]);

            return $purchase->load('items');
        });
    }

    /**
     * Cancel a purchase and remove its stock. Refused if any unit from it has
     * already been sold or adjusted, because that history cannot be undone.
     */
    public function cancel(Purchase $purchase, string $reason, User $user): Purchase
    {
        return DB::transaction(function () use ($purchase, $reason, $user) {
            /** @var Purchase $purchase */
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->isCancelled()) {
                throw new BusinessRuleException('This purchase is already cancelled.');
            }

            $batches = Batch::query()->with('medicine')->where('purchase_id', $purchase->id)->lockForUpdate()->get();

            foreach ($batches as $batch) {
                if ($batch->quantity !== $batch->quantity_received) {
                    throw new BusinessRuleException(
                        "Cannot cancel: stock from batch {$batch->batch_number} has already been sold or adjusted. Use a stock adjustment instead."
                    );
                }
            }

            foreach ($batches as $batch) {
                $this->inventory->removeFromBatch($batch, $batch->quantity, StockMovementType::PurchaseCancel, $purchase, $reason);
            }

            $supplier = Supplier::query()->withTrashed()->lockForUpdate()->findOrFail($purchase->supplier_id);
            // Payments already reduced the balance; removing the purchase total
            // leaves any amount paid as a credit with the supplier.
            $supplier->forceFill([
                'balance' => Money::fromCents(Money::toCents($supplier->balance) - Money::toCents($purchase->total)),
            ])->save();

            $purchase->forceFill([
                'status' => PurchaseStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
            ])->save();

            $this->logger->log('purchase.cancelled', "Purchase {$purchase->reference_no} cancelled", $purchase, ['reason' => $reason]);

            return $purchase;
        });
    }
}
