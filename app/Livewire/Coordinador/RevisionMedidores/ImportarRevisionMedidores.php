<?php

namespace App\Livewire\Coordinador\RevisionMedidores;

use App\Imports\RevisionMedidoresImport;
use App\Services\TenantService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app')]
#[Title('Importar Revisión de Medidores')]
class ImportarRevisionMedidores extends Component
{
    use WithFileUploads;

    #[Validate('required|file|mimes:csv,xlsx,xls|max:10240')]
    public mixed $archivo = null;

    public ?int $importadas = null;

    /** @var array<int, array{fila: int, campo: string, motivo: string}> */
    public array $errores = [];

    public bool $importacionRealizada = false;

    public function importar(): void
    {
        $this->validate();

        $companyId = app(TenantService::class)->id() ?? auth()->user()->company_id;
        $userId = auth()->id();

        $import = new RevisionMedidoresImport($companyId, $userId);

        $extension = strtolower($this->archivo->getClientOriginalExtension());
        $readerType = in_array($extension, ['xlsx', 'xls'])
            ? \Maatwebsite\Excel\Excel::XLSX
            : \Maatwebsite\Excel\Excel::CSV;

        Excel::import($import, $this->archivo->getRealPath(), null, $readerType);

        $this->errores = $import->errores();
        $this->importadas = $import->creadas();

        $this->importacionRealizada = true;
        $this->archivo = null;
    }

    public function render(): View
    {
        return view('livewire.coordinador.revision-medidores.importar-revision-medidores');
    }
}
