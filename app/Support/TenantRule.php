<?php

namespace App\Support;

use App\Support\Tenancy\CurrentPharmacy;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules restricted to the current pharmacy, so a request can never
 * reference (or collide with) another tenant's records.
 */
final class TenantRule
{
    public static function exists(string $table, bool $softDeletes = false): Exists
    {
        $rule = (new Exists($table, 'id'))->where('pharmacy_id', app(CurrentPharmacy::class)->id());

        return $softDeletes ? $rule->whereNull('deleted_at') : $rule;
    }

    public static function unique(string $table, string $column): Unique
    {
        return (new Unique($table, $column))->where('pharmacy_id', app(CurrentPharmacy::class)->id());
    }
}
