<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Decisión consciente: el login de la API no exige el segundo factor (2FA) aunque el
     * usuario lo tenga activado en el panel web. La app móvil no tiene pantalla para pedir
     * el código, así que bloquear aquí dejaría sin acceso a cualquier técnico/auditor con
     * 2FA activo. Si se necesita paridad con el panel web, hay que diseñar un paso adicional
     * en el login de la API antes de exigir 2FA aquí.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->estado) {
            Auth::logout();

            return response()->json(['message' => 'Tu cuenta está desactivada.'], 403);
        }

        if ($user->is_superadmin) {
            Auth::logout();

            return response()->json(['message' => 'Los superadministradores no pueden usar la API.'], 403);
        }

        $company = $user->company_id ? Company::withoutGlobalScopes()->find($user->company_id) : null;

        if (! $company || ! $company->activo) {
            Auth::logout();

            return response()->json(['message' => 'Tu empresa no está activa o no tienes una empresa asignada.'], 403);
        }

        $nombreToken = Str::limit(trim((string) $request->input('device_name', '')), 60, '') ?: 'nativephp';

        $user->tokens()->where('name', $nombreToken)->delete();

        $token = $user->createToken($nombreToken)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'nombre' => $user->fullName(),
                'iniciales' => $user->initials(),
                'personals_id' => $user->personals_id,
                'company_id' => $user->company_id,
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}
