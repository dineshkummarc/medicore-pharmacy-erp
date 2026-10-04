<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only ledger of every stock change. Written only by InventoryService.
 */
class StockMovement extends Model
{
    use BelongsToPharmacy;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'balance_after' => 'integer',
            'unit_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class)->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
