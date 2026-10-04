<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\SaleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * POS checkout: Sale → stock deduction (FEFO) → invoice number → payment →
 * customer balance, all in one transaction.
 */
class SaleService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly DocumentNumberService $numbers,
        private readonly ActivityLogger $logger,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  array{customer_id?: int|null, payment_method_id: int, amount_received?: string|float|null, notes?: string|null, items: list<array{medicine_id: int, quantity: int, discount_percent?: string|float|null}>}  $data
     */
    public function create(array $data, User $user): Sale
    {
        $lines = $this->mergeLines($data['items']);
        $this->guardDiscounts($lines, $user);

        return DB::transaction(function () use ($data, $lines, $user) {
            $customer = isset($data['customer_id'])
                ? Customer::query()->lockForUpdate()->findOrFail($data['customer_id'])
                : null;

            $sale = new Sale;
            $sale->forceFill([
                'customer_id' => $customer?->id,
                'user_id' => $user->id,
                'invoice_number' => $this->numbers->next(DocumentNumberService::INVOICE),
                'sold_at' => now(),
                'notes' => $data['notes'] ?? null,
                'status' => SaleStatus::Completed,
                'payment_status' => PaymentStatus::Unpaid,
            ])->save();

            $taxEnabled = $this->settings->bool('tax_enabled');
            $subtotal = $discountTotal = $taxTotal = 0;

            // Lock medicines in a stable order so concurrent sales cannot deadlock.
            $medicines = Medicine::query()
                ->whereIn('id', array_keys($lines))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($lines as $medicineId => $line) {
                $medicine = $medicines->get($medicineId);

                if (! $medicine || ! $medicine->is_active) {
                    throw new BusinessRuleException('One of the selected medicines is no longer available for sale.');
                }

                $allocations = $this->inventory->deductFefo($medicine, $line['quantity'], $sale);
                $priceCents = Money::toCents($medicine->selling_price);
                $taxRate = $taxEnabled ? (float) $medicine->tax_rate : 0.0;

                foreach ($allocations as $allocation) {
                    $batch = $allocation['batch'];
                    $qty = $allocation['quantity'];

                    $gross = $priceCents * $qty;
                    $discount = Money::percentOf($gross, $line['discount_percent']);
                    $tax = Money::percentOf($gross - $discount, $taxRate);

                    $sale->items()->forceCreate([
                        'medicine_id' => $medicine->id,
                        'batch_id' => $batch->id,
                        'medicine_name' => $medicine->displayName(),
                        'batch_number' => $batch->batch_number,
                        'expiry_date' => $batch->expiry_date,
                        'quantity' => $qty,
                        'unit_price' => $medicine->selling_price,
                        'unit_cost' => $batch->purchase_price,
                        'discount_percent' => $line['discount_percent'],
                        'discount_amount' => Money::fromCents($discount),
                        'tax_rate' => $taxRate,
                        'tax_amount' => Money::fromCents($tax),
                        'line_total' => Money::fromCents($gross - $discount + $tax),
                    ]);

                    $subtotal += $gross;
                    $discountTotal += $discount;
                    $taxTotal += $tax;
                }
            }

            $total = $subtotal - $discountTotal + $taxTotal;
            $received = array_key_exists('amount_received', $data) && $data['amount_received'] !== null && $data['amount_received'] !== ''
                ? Money::toCents($data['amount_received'])
                : $total;
            $paid = min(max(0, $received), $total);
            $due = $total - $paid;

            if ($due > 0) {
                if (! $customer) {
                    throw new BusinessRuleException('Select a customer to sell on credit (amount received is less than the total).');
                }

                if (! $user->hasPermission('sales.credit')) {
                    throw new BusinessRuleException('You are not allowed to sell on credit. Collect the full amount.');
                }
            }

            $sale->forceFill([
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
                    'direction' => PaymentType::Sale->direction(),
                    'type' => PaymentType::Sale,
                    'customer_id' => $customer?->id,
                    'sale_id' => $sale->id,
                    'amount' => Money::fromCents($paid),
                    'paid_at' => now(),
                ])->save();
            }

            if ($customer && $due > 0) {
                $customer->forceFill([
                    'balance' => Money::fromCents(Money::toCents($customer->balance) + $due),
                ])->save();
            }

            $this->logger->log('sale.created', "Sale {$sale->invoice_number} completed", $sale, [
                'total' => $sale->total,
                'paid' => $sale->paid_amount,
                'items' => count($lines),
            ]);
            $this->logger->log('invoice.generated', "Invoice {$sale->invoice_number} generated", $sale);

            return $sale->load('items');
        });
    }

    /**
     * Combine repeated medicines into one line and normalise discounts.
     *
     * @param  list<array{medicine_id: int, quantity: int, discount_percent?: string|float|null}>  $items
     * @return array<int, array{quantity: int, discount_percent: float}>
     */
    private function mergeLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $id = (int) $item['medicine_id'];
            $quantity = (int) $item['quantity'];

            if ($quantity <= 0) {
                throw new BusinessRuleException('Quantities must be at least 1.');
            }

            $lines[$id] = [
                'quantity' => ($lines[$id]['quantity'] ?? 0) + $quantity,
                'discount_percent' => round((float) ($item['discount_percent'] ?? 0), 2),
            ];
        }

        if ($lines === []) {
            throw new BusinessRuleException('The cart is empty.');
        }

        ksort($lines);

        return $lines;
    }

    /**
     * @param  array<int, array{quantity: int, discount_percent: float}>  $lines
     */
    private function guardDiscounts(array $lines, User $user): void
    {
        $max = (float) $this->settings->get('max_discount_percent', 0);

        foreach ($lines as $line) {
            $discount = $line['discount_percent'];

            if ($discount < 0 || $discount > 100) {
                throw new BusinessRuleException('Discount must be between 0 and 100%.');
            }

            if ($discount > 0 && ! $user->hasPermission('sales.discount')) {
                throw new BusinessRuleException('You are not authorised to apply discounts.');
            }

            if ($discount > $max && ! $user->isAdmin()) {
                throw new BusinessRuleException("Discount cannot exceed {$max}%.");
            }
        }
    }
}
