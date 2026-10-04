<?php

namespace App\Support\Tenancy;

use App\Models\Pharmacy;

/**
 * Holds the pharmacy (tenant) the current request or job operates on.
 *
 * Today the application serves one pharmacy, so when nothing has been set the
 * first pharmacy is used. Supporting several tenants later means resolving the
 * tenant explicitly (from the user, a subdomain, …) and removing the fallback.
 */
class CurrentPharmacy
{
    private ?int $id = null;

    public function set(Pharmacy|int|null $pharmacy): void
    {
        $this->id = $pharmacy instanceof Pharmacy ? $pharmacy->getKey() : $pharmacy;
    }

    public function id(): ?int
    {
        if ($this->id === null) {
            $this->id = Pharmacy::query()->orderBy('id')->value('id');
        }

        return $this->id;
    }

    public function forget(): void
    {
        $this->id = null;
    }
}
