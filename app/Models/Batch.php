<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Batches are created and changed only through InventoryService.
 */
#[Fillable([
    'medicine_id', 'supplier_id', 'purchase_id', 'batch_number', 'expiry_date',
    'purchase_price', 'selling_price', 'quantity_received', 'quantity', 'received_at',
])]
class Batch extends Model
{
    use BelongsToPharmacy, HasFactory;

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_at' => 'datetime',
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'quantity' => 'integer',
            'quantity_received' => 'integer',
        ];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeInStock(Builder $query): void
    {
        $query->where('quantity', '>', 0);
    }

    public function scopeExpired(Builder $query): void
    {
        $query->whereDate('expiry_date', '<=', today());
    }

    public function scopeSellable(Builder $query): void
    {
        $query->where('quantity', '>', 0)->whereDate('expiry_date', '>', today());
    }

    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $query->whereDate('expiry_date', '>', today())
            ->whereDate('expiry_date', '<=', today()->addDays($days));
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->lte(today());
    }

    public function daysToExpiry(): int
    {
        return (int) today()->diffInDays($this->expiry_date, false);
    }
}
