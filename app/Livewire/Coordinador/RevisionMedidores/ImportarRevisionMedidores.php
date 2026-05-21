<?php

namespace App\Livewire\Coordinador\RevisionMedidores;

use App\Imports\RevisionMedidoresImport;
use App\Services\TenantService;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app')]
#[Title('Importar Revisión de Medidores')]
class ImportarRevisionMedidores extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public array $data = [];

    public ?int $importadas = null;

    /** @var array<int, array{fila: int, campo: string, motivo: string}> */
    public array $errores = [];

    public bool $importacionRealizada = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('archivo')
                    ->label('Archivo CSV o Excel')
                    ->disk('local')
                    ->directory('temp/imports')
                    ->acceptedFileTypes([
                        'text/csv',
                        'application/csv',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'application/octet-stream',
                    ])
                    ->maxSize(10240)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function importar(): void
    {
        $state = $this->form->getState();
        $filePath = $state['archivo'];

        $absolutePath = Storage::disk('local')->path($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $readerType = in_array($extension, ['xlsx', 'xls'])
            ? \Maatwebsite\Excel\Excel::XLSX
            : \Maatwebsite\Excel\Excel::CSV;

        $companyId = app(TenantService::class)->id() ?? auth()->user()->company_id;
        $userId = auth()->id();

        $import = new RevisionMedidoresImport($companyId, $userId);
        Excel::import($import, $absolutePath, null, $readerType);

        Storage::disk('local')->delete($filePath);

        $this->errores = $import->errores();
        $this->importadas = $import->creadas();
        $this->importacionRealizada = true;

        $this->form->fill();
    }

    public function render(): View
    {
        return view('livewire.coordinador.revision-medidores.importar-revision-medidores');
    }
}
