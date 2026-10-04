<?php

use App\Services\SettingsService;
use App\Support\Money;

if (! function_exists('settings')) {
    /**
     * Read a store setting for the current pharmacy.
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingsService::class);

        return $key === null ? $service->all() : $service->get($key, $default);
    }
}

if (! function_exists('money')) {
    function money(string|int|float|null $amount): string
    {
        return Money::format($amount);
    }
}
