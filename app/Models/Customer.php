<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * balance is maintained by SaleService / PaymentService and is not fillable.
 */
#[Fillable(['name', 'phone', 'email', 'address', 'date_of_birth', 'notes', 'is_active'])]
class Customer extends Model
{
    use BelongsToPharmacy, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }
}
