<?php

namespace App\Http\Controllers;

use App\Exports\RevisionMedidoresPlantillaExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RevisionMedidoresPlantillaController extends Controller
{
    public function download(): BinaryFileResponse
    {
        return Excel::download(
            new RevisionMedidoresPlantillaExport,
            'plantilla-revision-medidores.xlsx'
        );
    }
}
