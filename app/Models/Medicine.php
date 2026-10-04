<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * stock_quantity is a cache of SUM(batches.quantity) written only by
 * InventoryService, so it is deliberately not fillable.
 */
#[Fillable([
    'category_id', 'manufacturer_id', 'supplier_id', 'name', 'generic_name', 'strength',
    'sku', 'barcode', 'unit', 'purchase_price', 'selling_price', 'tax_rate',
    'min_stock_level', 'requires_prescription', 'description', 'is_active',
])]
class Medicine extends Model
{
    use BelongsToPharmacy, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'min_stock_level' => 'integer',
            'stock_quantity' => 'integer',
            'requires_prescription' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Stock at or below the reorder level (the medicine's own level, or the
     * store-wide threshold when none is set).
     */
    public function scopeLowStock(Builder $query): void
    {
        $threshold = (int) settings('low_stock_threshold', 10);

        $query->whereRaw(
            'stock_quantity <= (CASE WHEN min_stock_level > 0 THEN min_stock_level ELSE ? END)',
            [$threshold],
        );
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $q->where('name', 'like', $like)
                ->orWhere('generic_name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('barcode', $term);
        });
    }

    public function reorderLevel(): int
    {
        return $this->min_stock_level > 0
            ? $this->min_stock_level
            : (int) settings('low_stock_threshold', 10);
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->reorderLevel();
    }

    public function displayName(): string
    {
        return trim($this->name.' '.($this->strength ?? ''));
    }
}
