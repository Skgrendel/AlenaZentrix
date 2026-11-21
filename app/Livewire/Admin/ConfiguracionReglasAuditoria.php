<?php

namespace App\Livewire\Admin;

use App\Jobs\ReauditarRegistrosJob;
use App\Models\AjusteAuditoria;
use App\Services\TenantService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Pestaña de Configuración «Reglas de la auditoría»: qué tan grave es cada regla para la empresa.
 * Bloqueante propone «No se aprueba»; corregible propone «Se aprueba solicitando correcciones».
 */
class ConfiguracionReglasAuditoria extends Component
{
    /** @var array<string, string> regla => nivel */
    public array $niveles = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('admin.configuracion'), 403);

        $this->niveles = AjusteAuditoria::pesosDe($this->empresaId());
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(): array
    {
        return ['niveles.*' => ['required', 'in:'.implode(',', array_keys(AjusteAuditoria::NIVELES))]];
    }

    /** Guarda los niveles y vuelve a auditar los registros ya cargados para que se vean con las reglas nuevas. */
    public function guardar(): void
    {
        abort_unless(auth()->user()?->can('admin.configuracion'), 403);

        $empresa = $this->empresaId();

        if ($empresa === null) {
            Flux::toast(text: 'Tu usuario no tiene una empresa asignada.', variant: 'danger');

            return;
        }

        $this->validate();

        AjusteAuditoria::query()->updateOrCreate(
            ['company_id' => $empresa],
            ['pesos' => array_intersect_key($this->niveles, AjusteAuditoria::REGLAS)],
        );

        ReauditarRegistrosJob::dispatch($empresa);

        Flux::toast(text: 'Reglas guardadas. Los registros ya cargados se están auditando de nuevo con estas reglas.', variant: 'success');
    }

    /** Vuelve a poner en pantalla los valores sugeridos (no guarda hasta pulsar Guardar). */
    public function restablecerSugeridos(): void
    {
        abort_unless(auth()->user()?->can('admin.configuracion'), 403);

        $this->niveles = array_merge(array_fill_keys(array_keys(AjusteAuditoria::REGLAS), AjusteAuditoria::SEGUN_EL_CASO), AjusteAuditoria::sugeridos());
    }

    public function render(): View
    {
        return view('livewire.admin.configuracion-reglas-auditoria', [
            'reglas' => AjusteAuditoria::REGLAS,
            'opciones' => AjusteAuditoria::NIVELES,
            'sugeridos' => AjusteAuditoria::sugeridos(),
        ]);
    }

    private function empresaId(): ?string
    {
        return app(TenantService::class)->id() ?? auth()->user()?->company_id;
    }
}
