<?php

namespace App\Livewire\Settings;

use App\Enums\TipoDocumento;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile settings')]
/**
 * Configuración del perfil del usuario autenticado: datos personales y verificación de email.
 * Actualiza el registro `personals` asociado ya que el modelo User no tiene campo `name`.
 */
class Profile extends Component
{
    public string $email = '';

    public string $nombres = '';

    public string $apellidos = '';

    public string $tipo_documento = '';

    public string $numero_documento = '';

    public string $telefono = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->email = $user->email ?? '';

        $personal = $user->personal;
        if ($personal) {
            $this->nombres = $personal->nombres ?? '';
            $this->apellidos = $personal->apellidos ?? '';
            $this->tipo_documento = $personal->tipo_documento instanceof TipoDocumento
                ? $personal->tipo_documento->value
                : ($personal->tipo_documento ?? '');
            $this->numero_documento = $personal->numero_documento ?? '';
            $this->telefono = $personal->telefono ?? '';
        }
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $this->validate([
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'tipo_documento' => ['required', 'string'],
            'numero_documento' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
        ]);

        $personal = $user->personal;

        if ($personal) {
            $personal->update([
                'nombres' => $this->nombres,
                'apellidos' => $this->apellidos,
                'tipo_documento' => $this->tipo_documento,
                'numero_documento' => $this->numero_documento,
                'telefono' => $this->telefono ?: null,
            ]);
        }

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }

    /** @return array<string, string> */
    public function tiposDocumento(): array
    {
        return collect(TipoDocumento::cases())
            ->mapWithKeys(fn (TipoDocumento $case) => [$case->value => $case->label()])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.settings.profile');
    }
}
