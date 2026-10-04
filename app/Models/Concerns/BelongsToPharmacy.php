<?php

namespace App\Models\Concerns;

use App\Models\Pharmacy;
use App\Support\Tenancy\CurrentPharmacy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current pharmacy and stamps pharmacy_id on create.
 *
 * Because the scope is global, route-model binding of another tenant's record
 * resolves to a 404 instead of leaking data (IDOR protection).
 */
trait BelongsToPharmacy
{
    public static function bootBelongsToPharmacy(): void
    {
        static::addGlobalScope('pharmacy', function (Builder $builder) {
            $pharmacyId = app(CurrentPharmacy::class)->id();

            if ($pharmacyId !== null) {
                $builder->where($builder->qualifyColumn('pharmacy_id'), $pharmacyId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->pharmacy_id)) {
                $model->pharmacy_id = app(CurrentPharmacy::class)->id();
            }
        });
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
