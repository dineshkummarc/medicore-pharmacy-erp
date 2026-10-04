<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PurchaseStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Settles outstanding balances. A payment is allocated to the oldest unpaid
 * invoices first, producing one payment row per invoice it settles.
 */
class PaymentService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function collectFromCustomer(Customer $customer, string|float $amount, int $methodId, User $user, ?string $reference = null, ?string $note = null): int
    {
        return DB::transaction(function () use ($customer, $amount, $methodId, $user, $reference, $note) {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $amountCents = Money::toCents($amount);

            $this->guardAmount($amountCents, Money::toCents($customer->balance));

            $sales = Sale::query()
                ->where('customer_id', $customer->id)
                ->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::Partial])
                ->orderBy('sold_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $remaining = $amountCents;

            foreach ($sales as $sale) {
                $apply = min($remaining, Money::toCents($sale->dueAmount()));

                if ($apply <= 0) {
                    continue;
                }

                $paid = Money::toCents($sale->paid_amount) + $apply;
                $sale->forceFill([
                    'paid_amount' => Money::fromCents($paid),
                    'payment_status' => PaymentStatus::fromAmounts(Money::toCents($sale->netTotal()), $paid),
                ])->save();

                $this->record(PaymentType::CustomerPayment, $apply, $methodId, $user, $reference, $note, [
                    'customer_id' => $customer->id,
                    'sale_id' => $sale->id,
                ]);

                $remaining -= $apply;

                if ($remaining === 0) {
                    break;
                }
            }

            if ($remaining > 0) {
                throw new BusinessRuleException('The amount exceeds what is owed on open invoices.');
            }

            $customer->forceFill(['balance' => Money::fromCents(Money::toCents($customer->balance) - $amountCents)])->save();

            $this->logger->log('customer.payment', 'Collected '.Money::format(Money::fromCents($amountCents))." from {$customer->name}", $customer, [
                'amount' => Money::fromCents($amountCents),
            ]);

            return $amountCents;
        });
    }

    public function paySupplier(Supplier $supplier, string|float $amount, int $methodId, User $user, ?string $reference = null, ?string $note = null): int
    {
        return DB::transaction(function () use ($supplier, $amount, $methodId, $user, $reference, $note) {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
            $amountCents = Money::toCents($amount);

            $this->guardAmount($amountCents, Money::toCents($supplier->balance));

            $purchases = Purchase::query()
                ->where('supplier_id', $supplier->id)
                ->where('status', PurchaseStatus::Received)
                ->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::Partial])
                ->orderBy('purchase_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $remaining = $amountCents;

            foreach ($purchases as $purchase) {
                $apply = min($remaining, Money::toCents($purchase->dueAmount()));

                if ($apply <= 0) {
                    continue;
                }

                $paid = Money::toCents($purchase->paid_amount) + $apply;
                $purchase->forceFill([
                    'paid_amount' => Money::fromCents($paid),
                    'payment_status' => PaymentStatus::fromAmounts(Money::toCents($purchase->total), $paid),
                ])->save();

                $this->record(PaymentType::SupplierPayment, $apply, $methodId, $user, $reference, $note, [
                    'supplier_id' => $supplier->id,
                    'purchase_id' => $purchase->id,
                ]);

                $remaining -= $apply;

                if ($remaining === 0) {
                    break;
                }
            }

            // Any remainder (e.g. an opening balance not tied to a purchase) is
            // recorded against the supplier without a purchase link.
            if ($remaining > 0) {
                $this->record(PaymentType::SupplierPayment, $remaining, $methodId, $user, $reference, $note, [
                    'supplier_id' => $supplier->id,
                ]);
            }

            $supplier->forceFill(['balance' => Money::fromCents(Money::toCents($supplier->balance) - $amountCents)])->save();

            $this->logger->log('supplier.payment', 'Paid '.Money::format(Money::fromCents($amountCents))." to {$supplier->name}", $supplier, [
                'amount' => Money::fromCents($amountCents),
            ]);

            return $amountCents;
        });
    }

    private function guardAmount(int $amountCents, int $balanceCents): void
    {
        if ($amountCents <= 0) {
            throw new BusinessRuleException('Payment amount must be greater than zero.');
        }

        if ($amountCents > $balanceCents) {
            throw new BusinessRuleException('Payment exceeds the outstanding balance of '.Money::format(Money::fromCents(max(0, $balanceCents))).'.');
        }
    }

    /**
     * @param  array<string, int>  $links
     */
    private function record(PaymentType $type, int $cents, int $methodId, User $user, ?string $reference, ?string $note, array $links): void
    {
        $payment = new Payment;
        $payment->forceFill(array_merge([
            'payment_method_id' => $methodId,
            'user_id' => $user->id,
            'direction' => $type->direction(),
            'type' => $type,
            'amount' => Money::fromCents($cents),
            'paid_at' => now(),
            'reference' => $reference,
            'note' => $note,
        ], $links))->save();
    }
}
