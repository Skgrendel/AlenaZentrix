<?php

namespace App\Services;

use App\Models\Company;

class TenantService
{
    private ?Company $current = null;

    public function set(Company $company): void
    {
        $this->current = $company;
    }

    public function get(): ?Company
    {
        return $this->current;
    }

    public function active(): bool
    {
        return $this->current !== null;
    }

    public function id(): ?string
    {
        return $this->current?->id;
    }
}
