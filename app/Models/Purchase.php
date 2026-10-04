<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Totals and status are computed by PurchaseService; nothing is mass assignable.
 */
class Purchase extends Model
{
    use BelongsToPharmacy, HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'status' => PurchaseStatus::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function dueAmount(): string
    {
        return number_format(max(0, (float) $this->total - (float) $this->paid_amount), 2, '.', '');
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseStatus::Cancelled;
    }
}
