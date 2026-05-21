<?php

namespace App\Imports;

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RevisionMedidoresImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array{fila: int, campo: string, motivo: string}> */
    private array $errores = [];

    private int $filaActual = 1;

    public function __construct(
        private readonly string $companyId,
        private readonly string $creadoPorId,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->filaActual++;
            $rowArray = $row instanceof Collection ? $row->toArray() : (array) $row;
            $erroresFila = $this->validar($rowArray);

            if (! empty($erroresFila)) {
                foreach ($erroresFila as $error) {
                    $this->errores[] = ['fila' => $this->filaActual, ...$error];
                }

                continue;
            }

            OrdenJob::create([
                'company_id' => $this->companyId,
                'creado_por_id' => $this->creadoPorId,
                'tipo_servicio' => TipoServicio::RevisionMedidores->value,
                'estado' => EstadoOrden::Pendiente->value,
                'contrato' => trim($rowArray['contrato'] ?? ''),
                'cliente' => trim($rowArray['cliente'] ?? ''),
                'direccion' => trim($rowArray['direccion'] ?? ''),
                'barrio' => trim($rowArray['barrio'] ?? ''),
                'medidor' => trim($rowArray['medidor'] ?? ''),
                'tipo_medidor' => trim($rowArray['tipo_medidor'] ?? '') ?: null,
                'ciclo' => trim($rowArray['ciclo'] ?? '') ?: null,
                'comentario_usuario' => trim($rowArray['descripcion'] ?? '') ?: null,
                'estado_servicio' => true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array{campo: string, motivo: string}>
     */
    private function validar(array $row): array
    {
        $requeridos = ['contrato', 'cliente', 'direccion', 'barrio', 'medidor'];
        $errores = [];

        foreach ($requeridos as $campo) {
            if (empty(trim($row[$campo] ?? ''))) {
                $errores[] = ['campo' => $campo, 'motivo' => "El campo '{$campo}' es requerido"];
            }
        }

        return $errores;
    }

    /** @return array<int, array{fila: int, campo: string, motivo: string}> */
    public function errores(): array
    {
        return $this->errores;
    }
}
