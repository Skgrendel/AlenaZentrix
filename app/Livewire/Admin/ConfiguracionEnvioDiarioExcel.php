<?php

namespace App\Livewire\Admin;

use App\Models\AjusteEnvioExcel;
use App\Models\EnvioExcel;
use App\Services\survey123\EnvioDiarioDeExcel;
use App\Services\TenantService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Pestaña de Configuración del envío diario del Excel por correo: hora, días, destinatarios, asunto y mensaje.
 */
class ConfiguracionEnvioDiarioExcel extends Component
{
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    public bool $activo = false;

    public string $hora = '18:00';

    /** @var array<int, string> números ISO de los días (1 = lunes ... 7 = domingo) */
    public array $dias = [];

    public string $destinatarios = '';

    public string $asunto = '';

    public string $mensaje = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('admin.configuracion'), 403);

        $ajuste = AjusteEnvioExcel::de($this->empresaId());

        $this->activo = $ajuste->activo;
        $this->hora = $ajuste->hora;
        $this->dias = array_map('strval', $ajuste->dias ?? []);
        $this->destinatarios = $ajuste->destinatarios;
        $this->asunto = $ajuste->asunto;
        $this->mensaje = $ajuste->mensaje;
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(): array
    {
        return [
            'activo' => ['boolean'],
            'hora' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'dias' => [$this->activo ? 'required' : 'nullable', 'array'],
            'dias.*' => ['in:1,2,3,4,5,6,7'],
            'destinatarios' => [$this->activo ? 'required' : 'nullable', 'string', 'max:2000', function (string $atributo, mixed $valor, \Closure $fallo): void {
                $correos = (new AjusteEnvioExcel(['destinatarios' => (string) $valor]))->correos();

                if (count($correos) > 10) {
                    $fallo('Máximo 10 correos.');
                }

                foreach ($correos as $correo) {
                    if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
                        $fallo("«{$correo}» no es un correo válido.");

                        return;
                    }
                }
            }],
            'asunto' => ['required', 'string', 'max:150'],
            'mensaje' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'hora.regex' => 'Escribe la hora con el formato 18:00.',
            'dias.required' => 'Elige al menos un día.',
            'destinatarios.required' => 'Escribe al menos un correo.',
            'asunto.required' => 'Escribe el asunto del correo.',
            'mensaje.required' => 'Escribe el mensaje del correo.',
        ];
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()?->can('admin.configuracion'), 403);

        $empresa = $this->empresaId();

        if ($empresa === null) {
            Flux::toast(text: 'Tu usuario no tiene una empresa asignada.', variant: 'danger');

            return;
        }

        $this->validate();

        AjusteEnvioExcel::query()->updateOrCreate(['company_id' => $empresa], $this->datos());

        $this->destinatarios = implode("\n", $this->ajusteDeLaPantalla()->correos());

        Flux::toast(text: 'Configuración guardada.', variant: 'success');
    }

    /** Envía hoy el Excel solo al correo de quien está en sesión, con lo que hay en pantalla (guardado o no). */
    public function enviarPrueba(EnvioDiarioDeExcel $envios): void
    {
        abort_unless(auth()->user()?->can('admin.configuracion'), 403);

        $empresa = $this->empresaId();
        $correo = auth()->user()->email;

        if ($empresa === null || blank($correo)) {
            Flux::toast(text: 'No hay un correo al cual enviar la prueba.', variant: 'danger');

            return;
        }

        $this->validateOnly('asunto');
        $this->validateOnly('mensaje');

        $envio = $envios->enviar($this->ajusteDeLaPantalla(), EnvioExcel::PRUEBA, [$correo]);

        $envio->estado === EnvioExcel::ENVIADO
            ? Flux::toast(text: "Prueba enviada a {$correo} con {$envio->registros} registro(s) de hoy.", variant: 'success')
            : Flux::toast(heading: 'No se pudo enviar la prueba', text: (string) $envio->error, variant: 'danger');
    }

    public function render(): View
    {
        $empresa = $this->empresaId();

        return view('livewire.admin.configuracion-envio-diario-excel', [
            'dias' => self::DIAS,
            'envios' => $empresa === null ? collect() : EnvioExcel::query()->where('company_id', $empresa)->latest()->limit(10)->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        return [
            'activo' => $this->activo,
            'hora' => $this->hora,
            'dias' => collect($this->dias)->map(fn ($d): int => (int) $d)->unique()->sort()->values()->all(),
            'destinatarios' => implode("\n", $this->ajusteDeLaPantalla()->correos()),
            'asunto' => trim($this->asunto),
            'mensaje' => trim($this->mensaje),
        ];
    }

    private function ajusteDeLaPantalla(): AjusteEnvioExcel
    {
        return new AjusteEnvioExcel([
            'company_id' => $this->empresaId(),
            'activo' => $this->activo,
            'hora' => $this->hora,
            'dias' => array_map('intval', $this->dias),
            'destinatarios' => $this->destinatarios,
            'asunto' => trim($this->asunto),
            'mensaje' => trim($this->mensaje),
        ]);
    }

    private function empresaId(): ?string
    {
        return app(TenantService::class)->id() ?? auth()->user()?->company_id;
    }
}
