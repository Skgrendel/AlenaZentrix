<?php

namespace App\Livewire\Admin;

use App\Models\AjusteRevisionIA;
use App\Models\SurveyRegistro;
use App\Services\survey123\RevisorFotosIA;
use App\Services\TenantService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;
use Throwable;

/**
 * Pestaña de configuración de la revisión de fotos con IA: modelo, detalle de imagen e instrucciones,
 * con una prueba sobre un registro real para ajustar el texto antes de guardarlo.
 */
class ConfiguracionRevisionFotosIA extends Component
{
    public string $modelo = '';

    public string $detalle_imagen = 'high';

    public string $instrucciones = '';

    public ?string $registroPrueba = null;

    /** @var array<string, mixed>|null */
    public ?array $resultadoPrueba = null;

    public ?string $errorPrueba = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('admin.configuracion'), 403);

        $ajuste = AjusteRevisionIA::de($this->empresaId());

        $this->modelo = $ajuste->modelo;
        $this->detalle_imagen = $ajuste->detalle_imagen;
        $this->instrucciones = $ajuste->instrucciones;
    }

    /** @return array<string, array<int, string>> */
    protected function rules(): array
    {
        return [
            'modelo' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'detalle_imagen' => ['required', 'in:low,high,auto'],
            'instrucciones' => ['required', 'string', 'min:30', 'max:12000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'modelo.regex' => 'El nombre del modelo solo puede tener letras, números, punto, guion y dos puntos.',
            'instrucciones.min' => 'Escribe las instrucciones para la revisión.',
            'instrucciones.max' => 'Las instrucciones son demasiado largas (máximo 12.000 caracteres).',
        ];
    }

    private function empresaId(): ?string
    {
        return app(TenantService::class)->id() ?? auth()->user()?->company_id;
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()?->can('admin.configuracion'), 403);

        $datos = $this->validate();

        $empresa = $this->empresaId();

        if ($empresa === null) {
            Notification::make()->danger()->title('Tu usuario no tiene una empresa asignada')->send();

            return;
        }

        AjusteRevisionIA::query()->updateOrCreate(['company_id' => $empresa], $datos);

        Notification::make()->success()->title('Configuración guardada')->send();
    }

    public function restablecerInstrucciones(): void
    {
        $this->instrucciones = RevisorFotosIA::INSTRUCCIONES_RECOMENDADAS;
        $this->resultadoPrueba = null;
    }

    /** Revisa un registro con lo que hay en pantalla (guardado o no), sin cambiar la auditoría. */
    public function probar(RevisorFotosIA $revisor): void
    {
        abort_unless(auth()->user()?->can('admin.configuracion'), 403);

        $this->validate();
        $this->resultadoPrueba = null;
        $this->errorPrueba = null;

        if (blank($this->registroPrueba)) {
            $this->errorPrueba = 'Elige un registro para probar.';

            return;
        }

        $registro = SurveyRegistro::query()->whereHas('adjuntos')->find($this->registroPrueba);

        if ($registro === null) {
            $this->errorPrueba = 'No se encontró el registro o no tiene fotos.';

            return;
        }

        try {
            $this->resultadoPrueba = $revisor->revisar($registro, new AjusteRevisionIA([
                'company_id' => $registro->company_id,
                'modelo' => $this->modelo,
                'detalle_imagen' => $this->detalle_imagen,
                'instrucciones' => $this->instrucciones,
            ]));
        } catch (Throwable $e) {
            $this->errorPrueba = $e->getMessage();
        }
    }

    public function render(): View
    {
        /** @var Collection<string, string> $registros */
        $registros = SurveyRegistro::query()
            ->whereHas('adjuntos')
            ->orderByDesc('fecha_registro')
            ->limit(100)
            ->get(['id', 'orden_trabajo', 'contrato'])
            ->mapWithKeys(fn (SurveyRegistro $r): array => [$r->id => "OT {$r->orden_trabajo} · contrato {$r->contrato}"]);

        return view('livewire.admin.configuracion-revision-fotos-ia', [
            'modelos' => array_keys((array) config('services.openai.modelos')),
            'registros' => $registros,
            'tarifa' => config('services.openai.modelos')[$this->modelo] ?? null,
        ]);
    }
}
