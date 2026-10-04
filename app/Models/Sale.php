<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A completed sale is also the invoice (invoice_number is its sequential,
 * per-pharmacy identifier). Created only by SaleService.
 */
class Sale extends Model
{
    use BelongsToPharmacy, HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'returned_amount' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'status' => SaleStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    /**
     * Amount still owed on this invoice after payments and returns.
     */
    public function dueAmount(): string
    {
        $due = (float) $this->total - (float) $this->returned_amount - (float) $this->paid_amount;

        return number_format(max(0, $due), 2, '.', '');
    }

    public function netTotal(): string
    {
        return number_format((float) $this->total - (float) $this->returned_amount, 2, '.', '');
    }
}
