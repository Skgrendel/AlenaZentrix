<?php

namespace App\Models\Scopes;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user && $user->is_superadmin) {
            return;
        }

        $tenantId = app(TenantService::class)->id();

        if ($tenantId) {
            $builder->where($model->getTable().'.company_id', $tenantId);
        }
    }
}
