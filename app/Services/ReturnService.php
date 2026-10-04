<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales returns are reversal documents: the original sale is never edited
 * beyond its returned counters, and stock goes back to the original batch.
 *
 * The returned value first clears any amount the customer still owes on the
 * invoice; only the remainder is paid out as a refund.
 */
class ReturnService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly DocumentNumberService $numbers,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{reason: string, refund_method_id?: int|null, items: list<array{sale_item_id: int, quantity: int, restock?: bool}>}  $data
     */
    public function create(Sale $sale, array $data, User $user): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data, $user) {
            /** @var Sale $sale */
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $items = SaleItem::query()->with('batch.medicine')->where('sale_id', $sale->id)->lockForUpdate()->get()->keyBy('id');

            $requested = collect($data['items'])->filter(fn ($row) => (int) $row['quantity'] > 0);

            if ($requested->isEmpty()) {
                throw new BusinessRuleException('Select at least one item and quantity to return.');
            }

            $return = new SaleReturn;
            $return->forceFill([
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'user_id' => $user->id,
                'return_number' => $this->numbers->next(DocumentNumberService::RETURN),
                'returned_at' => now(),
                'reason' => $data['reason'],
            ])->save();

            $totalCents = $taxCents = 0;

            foreach ($requested as $row) {
                /** @var SaleItem|null $item */
                $item = $items->get((int) $row['sale_item_id']);
                $quantity = (int) $row['quantity'];

                if (! $item) {
                    throw new BusinessRuleException('A returned item does not belong to this invoice.');
                }

                if ($quantity > $item->returnableQuantity()) {
                    throw new BusinessRuleException(sprintf(
                        'Cannot return %d × %s; only %d remaining on this invoice.',
                        $quantity,
                        $item->medicine_name,
                        $item->returnableQuantity(),
                    ));
                }

                [$lineCents, $lineTaxCents] = $this->valueOf($item, $quantity);
                $restock = (bool) ($row['restock'] ?? true);

                $return->items()->forceCreate([
                    'sale_item_id' => $item->id,
                    'medicine_id' => $item->medicine_id,
                    'batch_id' => $item->batch_id,
                    'quantity' => $quantity,
                    'unit_price' => $item->unit_price,
                    'tax_amount' => Money::fromCents($lineTaxCents),
                    'line_total' => Money::fromCents($lineCents),
                    'restocked' => $restock && $item->batch_id !== null,
                ]);

                $item->forceFill(['returned_quantity' => $item->returned_quantity + $quantity])->save();

                if ($restock && $item->batch) {
                    $this->inventory->addToBatch($item->batch, $quantity, StockMovementType::SaleReturn, $return, $data['reason']);
                }

                $totalCents += $lineCents;
                $taxCents += $lineTaxCents;
            }

            $this->settleMoney($sale, $return, $totalCents, $taxCents, $data, $user);
            $this->refreshSaleStatus($sale, $items);

            $this->logger->log('sale.returned', "Return {$return->return_number} against {$sale->invoice_number}", $return, [
                'sale_id' => $sale->id,
                'total' => $return->total,
                'refund' => $return->refund_amount,
                'credit' => $return->credit_amount,
            ]);

            return $return->load('items');
        });
    }

    /**
     * Value of returning $quantity units of a line, pro-rata to what was paid
     * (discount and tax included). The last unit absorbs any rounding residue.
     *
     * @return array{0: int, 1: int} [line total cents, tax cents]
     */
    private function valueOf(SaleItem $item, int $quantity): array
    {
        $lineCents = Money::toCents($item->line_total);
        $taxCents = Money::toCents($item->tax_amount);

        if ($quantity === $item->returnableQuantity()) {
            $previous = SaleReturnItem::query()
                ->where('sale_item_id', $item->id)
                ->selectRaw('COALESCE(SUM(line_total), 0) as value, COALESCE(SUM(tax_amount), 0) as tax')
                ->first();

            return [
                $lineCents - Money::toCents($previous->value),
                $taxCents - Money::toCents($previous->tax),
            ];
        }

        return [
            (int) round($lineCents * $quantity / $item->quantity),
            (int) round($taxCents * $quantity / $item->quantity),
        ];
    }

    /**
     * @param  array{refund_method_id?: int|null}  $data
     */
    private function settleMoney(Sale $sale, SaleReturn $return, int $totalCents, int $taxCents, array $data, User $user): void
    {
        $dueCents = Money::toCents($sale->dueAmount());
        $creditCents = min($totalCents, $dueCents);
        $refundCents = $totalCents - $creditCents;

        if ($refundCents > 0 && empty($data['refund_method_id'])) {
            throw new BusinessRuleException('Choose how the refund is paid out.');
        }

        $return->forceFill([
            'subtotal' => Money::fromCents($totalCents - $taxCents),
            'tax_total' => Money::fromCents($taxCents),
            'total' => Money::fromCents($totalCents),
            'refund_amount' => Money::fromCents($refundCents),
            'credit_amount' => Money::fromCents($creditCents),
        ])->save();

        $sale->forceFill([
            'returned_amount' => Money::fromCents(Money::toCents($sale->returned_amount) + $totalCents),
            'paid_amount' => Money::fromCents(Money::toCents($sale->paid_amount) - $refundCents),
        ]);
        $sale->payment_status = PaymentStatus::fromAmounts(
            Money::toCents($sale->total) - Money::toCents($sale->returned_amount),
            Money::toCents($sale->paid_amount),
        );
        $sale->save();

        if ($creditCents > 0 && $sale->customer_id) {
            $customer = Customer::query()->withTrashed()->lockForUpdate()->findOrFail($sale->customer_id);
            $customer->forceFill([
                'balance' => Money::fromCents(Money::toCents($customer->balance) - $creditCents),
            ])->save();
        }

        if ($refundCents > 0) {
            $payment = new Payment;
            $payment->forceFill([
                'payment_method_id' => $data['refund_method_id'],
                'user_id' => $user->id,
                'direction' => PaymentType::Refund->direction(),
                'type' => PaymentType::Refund,
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'sale_return_id' => $return->id,
                'amount' => Money::fromCents($refundCents),
                'paid_at' => now(),
            ])->save();
        }
    }

    /**
     * @param  Collection<int, SaleItem>  $items
     */
    private function refreshSaleStatus(Sale $sale, $items): void
    {
        $remaining = $items->sum(fn (SaleItem $item) => $item->returnableQuantity());

        $sale->forceFill([
            'status' => $remaining === 0 ? SaleStatus::Returned : SaleStatus::PartiallyReturned,
        ])->save();
    }
}
