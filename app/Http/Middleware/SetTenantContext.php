<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(private readonly TenantService $tenantService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->is_superadmin) {
            if (! $request->routeIs('superadmin.*') && ! $request->routeIs('logout') && ! $request->routeIs('profile.*')) {
                return redirect()->route('superadmin.dashboard');
            }

            return $next($request);
        }

        if ($user->company_id) {
            $company = Company::withoutGlobalScopes()->find($user->company_id);

            if ($company && $company->activo) {
                $this->tenantService->set($company);
            }
        }

        return $next($request);
    }
}
