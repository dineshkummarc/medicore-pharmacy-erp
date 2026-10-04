<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Tenancy\CurrentPharmacy;
use Illuminate\Support\Facades\Cache;

/**
 * Store settings: config defaults overridden by rows in the settings table,
 * cached per pharmacy.
 */
class SettingsService
{
    /** @var array<int, array<string, mixed>> */
    private array $loaded = [];

    public function __construct(private readonly CurrentPharmacy $pharmacy) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $pharmacyId = (int) $this->pharmacy->id();

        return $this->loaded[$pharmacyId] ??= array_merge(
            config('pharmacy.settings', []),
            $this->stored($pharmacyId),
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function bool(string $key): bool
    {
        return filter_var($this->get($key, false), FILTER_VALIDATE_BOOL);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void
    {
        $pharmacyId = (int) $this->pharmacy->id();

        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(
                ['pharmacy_id' => $pharmacyId, 'key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : $value],
            );
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget($this->cacheKey((int) $this->pharmacy->id()));
        $this->loaded = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(int $pharmacyId): array
    {
        if ($pharmacyId === 0) {
            return [];
        }

        return Cache::rememberForever(
            $this->cacheKey($pharmacyId),
            fn () => Setting::query()->where('pharmacy_id', $pharmacyId)->pluck('value', 'key')->all(),
        );
    }

    private function cacheKey(int $pharmacyId): string
    {
        return "pharmacy.{$pharmacyId}.settings";
    }
}
