<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RevisionMedidoresPlantillaExport implements FromArray, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return [
            'contrato',
            'cliente',
            'direccion',
            'barrio',
            'medidor',
            'tipo_medidor',
            'ciclo',
            'descripcion',
        ];
    }

    public function array(): array
    {
        return [
            [
                'CONT-001234',
                'Juan Pérez García',
                'Calle 45 # 23-10',
                'El Poblado',
                'MED-789456',
                'digital',
                'C12',
                'Verificación de lectura solicitada por empresa',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
