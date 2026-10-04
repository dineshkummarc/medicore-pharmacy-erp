<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class)->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function returnableQuantity(): int
    {
        return $this->quantity - $this->returned_quantity;
    }
}
