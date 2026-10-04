<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Whitelisted sorting and page sizes for index tables.
 */
trait HandlesTableQueries
{
    /**
     * @param  array<string, string>  $allowed  request key => column
     */
    protected function applySort(Builder $query, Request $request, array $allowed, string $default, string $defaultDirection = 'desc'): Builder
    {
        $key = (string) $request->query('sort', '');
        $column = $allowed[$key] ?? $allowed[$default] ?? $default;
        $direction = $request->query('direction') === 'asc' ? 'asc' : ($request->query('direction') === 'desc' ? 'desc' : $defaultDirection);

        return $query->orderBy($column, $direction)->orderBy($query->getModel()->getQualifiedKeyName(), 'desc');
    }

    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 15);

        return in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 15;
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    protected function dateRange(Request $request, string $fromKey = 'from', string $toKey = 'to'): array
    {
        $parse = function (?string $value) {
            if (! $value) {
                return null;
            }

            try {
                return Carbon::createFromFormat('Y-m-d', $value);
            } catch (\Throwable) {
                return null;
            }
        };

        $from = $parse($request->query($fromKey));
        $to = $parse($request->query($toKey));

        return [$from?->startOfDay(), $to?->endOfDay()];
    }
}
