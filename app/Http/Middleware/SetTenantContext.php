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

        $esApi = $request->is('api/*') || $request->expectsJson();

        if ($user->is_superadmin) {
            $isLivewireRequest = $request->hasHeader('X-Livewire');
            $allowed = $request->routeIs('superadmin.*')
                || $request->routeIs('logout')
                || $request->routeIs('profile.*')
                || $isLivewireRequest;

            if (! $allowed) {
                if ($esApi) {
                    return response()->json(['message' => 'Los superadministradores no pueden usar la API.'], 403);
                }

                return redirect()->route('superadmin.dashboard');
            }

            return $next($request);
        }

        $company = $user->company_id
            ? Company::withoutGlobalScopes()->find($user->company_id)
            : null;

        if ($company && $company->activo) {
            $this->tenantService->set($company);

            return $next($request);
        }

        // Fail-closed en la API: sin empresa activa el CompanyScope no filtraría y se verían datos de todas las empresas.
        if ($esApi) {
            return response()->json(['message' => 'Tu empresa no está activa o no tienes una empresa asignada.'], 403);
        }

        return $next($request);
    }
}
