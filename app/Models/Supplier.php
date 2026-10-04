<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * balance is maintained by PurchaseService / PaymentService and is not fillable.
 */
#[Fillable(['name', 'contact_person', 'phone', 'email', 'address', 'tax_number', 'notes', 'is_active'])]
class Supplier extends Model
{
    use BelongsToPharmacy, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function medicines(): HasMany
    {
        return $this->hasMany(Medicine::class);
    }
}
