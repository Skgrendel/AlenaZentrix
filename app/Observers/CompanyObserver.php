<?php

namespace App\Observers;

use App\Models\Company;
use App\Services\CatalogDefaultsService;

class CompanyObserver
{
    public function __construct(private readonly CatalogDefaultsService $catalogDefaults) {}

    public function created(Company $company): void
    {
        $this->catalogDefaults->seedForCompany($company);
    }
}
