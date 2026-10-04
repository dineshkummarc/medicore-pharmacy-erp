<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Batch;
use App\Models\Medicine;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The single place where stock changes.
 *
 * Every change updates a batch, records a stock movement and refreshes the
 * medicine's cached stock_quantity. Callers are expected to run inside a DB
 * transaction; each public method also opens one so it is safe on its own.
 *
 * Lock order is always medicine row → batch rows, to avoid deadlocks.
 */
class InventoryService
{
    /**
     * Add a new batch of stock (purchase, opening stock).
     *
     * @param  array{batch_number: string, expiry_date: string, purchase_price: string|float, selling_price: string|float, quantity: int, supplier_id?: int|null, purchase_id?: int|null}  $data
     */
    public function receive(Medicine $medicine, array $data, StockMovementType $type, ?Model $reference = null, ?string $note = null): Batch
    {
        if ($data['quantity'] <= 0) {
            throw new BusinessRuleException('Received quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($medicine, $data, $type, $reference, $note) {
            $this->lockMedicine($medicine);

            $batch = Batch::query()->create([
                'medicine_id' => $medicine->id,
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_id' => $data['purchase_id'] ?? null,
                'batch_number' => $data['batch_number'],
                'expiry_date' => $data['expiry_date'],
                'purchase_price' => $data['purchase_price'],
                'selling_price' => $data['selling_price'],
                'quantity_received' => $data['quantity'],
                'quantity' => $data['quantity'],
                'received_at' => now(),
            ]);

            $this->record($medicine, $batch, $type, $data['quantity'], $reference, $note);

            return $batch;
        });
    }

    /**
     * Take stock out of non-expired batches, earliest expiry first (FEFO).
     *
     * @return list<array{batch: Batch, quantity: int}>
     */
    public function deductFefo(Medicine $medicine, int $quantity, Model $reference, ?string $note = null): array
    {
        if ($quantity <= 0) {
            throw new BusinessRuleException('Quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($medicine, $quantity, $reference, $note) {
            $this->lockMedicine($medicine);

            $batches = $medicine->batches()
                ->sellable()
                ->orderBy('expiry_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = (int) $batches->sum('quantity');

            if ($available < $quantity) {
                throw new BusinessRuleException(sprintf(
                    'Insufficient stock for %s: requested %d, only %d sellable (non-expired) units available.',
                    $medicine->displayName(),
                    $quantity,
                    $available,
                ));
            }

            $allocations = [];
            $remaining = $quantity;

            foreach ($batches as $batch) {
                if ($remaining === 0) {
                    break;
                }

                $take = min($remaining, $batch->quantity);
                $batch->forceFill(['quantity' => $batch->quantity - $take])->save();
                $this->record($medicine, $batch, StockMovementType::Sale, -$take, $reference, $note);

                $allocations[] = ['batch' => $batch, 'quantity' => $take];
                $remaining -= $take;
            }

            return $allocations;
        });
    }

    /**
     * Put stock back into a specific batch (returns, positive adjustments).
     */
    public function addToBatch(Batch $batch, int $quantity, StockMovementType $type, ?Model $reference = null, ?string $note = null): void
    {
        if ($quantity <= 0) {
            throw new BusinessRuleException('Quantity must be greater than zero.');
        }

        DB::transaction(function () use ($batch, $quantity, $type, $reference, $note) {
            $medicine = $batch->medicine;
            $this->lockMedicine($medicine);
            $locked = Batch::query()->lockForUpdate()->findOrFail($batch->id);

            $locked->forceFill(['quantity' => $locked->quantity + $quantity])->save();
            $this->record($medicine, $locked, $type, $quantity, $reference, $note);
            $batch->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Remove stock from a specific batch (negative adjustments, write-offs,
     * purchase cancellation). Never lets a batch go below zero.
     */
    public function removeFromBatch(Batch $batch, int $quantity, StockMovementType $type, ?Model $reference = null, ?string $note = null): void
    {
        if ($quantity <= 0) {
            throw new BusinessRuleException('Quantity must be greater than zero.');
        }

        DB::transaction(function () use ($batch, $quantity, $type, $reference, $note) {
            $medicine = $batch->medicine;
            $this->lockMedicine($medicine);
            $locked = Batch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($locked->quantity < $quantity) {
                throw new BusinessRuleException(sprintf(
                    'Batch %s only has %d units; cannot remove %d.',
                    $locked->batch_number,
                    $locked->quantity,
                    $quantity,
                ));
            }

            $locked->forceFill(['quantity' => $locked->quantity - $quantity])->save();
            $this->record($medicine, $locked, $type, -$quantity, $reference, $note);
            $batch->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Manual stock adjustment of a batch with a mandatory reason.
     */
    public function adjust(Batch $batch, int $delta, string $reason): void
    {
        if ($delta === 0) {
            throw new BusinessRuleException('Adjustment quantity cannot be zero.');
        }

        $delta > 0
            ? $this->addToBatch($batch, $delta, StockMovementType::AdjustmentIn, null, $reason)
            : $this->removeFromBatch($batch, -$delta, StockMovementType::AdjustmentOut, null, $reason);
    }

    /**
     * Number of units that may be sold right now (excludes expired batches).
     */
    public function sellableQuantity(Medicine $medicine): int
    {
        return (int) $medicine->batches()->sellable()->sum('quantity');
    }

    /**
     * Recalculate the cached stock_quantity from batches.
     */
    public function syncStock(Medicine $medicine): int
    {
        $total = (int) Batch::query()->where('medicine_id', $medicine->id)->sum('quantity');
        $medicine->forceFill(['stock_quantity' => $total])->saveQuietly();

        return $total;
    }

    private function lockMedicine(Medicine $medicine): void
    {
        Medicine::query()->withTrashed()->whereKey($medicine->id)->lockForUpdate()->first();
    }

    private function record(Medicine $medicine, Batch $batch, StockMovementType $type, int $quantity, ?Model $reference, ?string $note): StockMovement
    {
        $balance = $this->syncStock($medicine);

        $movement = new StockMovement;
        $movement->forceFill([
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'user_id' => Auth::id(),
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $balance,
            'unit_cost' => $batch->purchase_price,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
        ])->save();

        return $movement;
    }
}
