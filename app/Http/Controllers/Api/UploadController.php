<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => [
                'required', 'file', 'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,heic,heif,mp4,mov,pdf,doc,docx,xls,xlsx,ppt,pptx',
            ],
            'directorio' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-\/]+$/'],
        ]);

        $companyId = $request->user()->company_id;
        $subdirectorio = $request->input('directorio', 'reportes/varios');
        $path = $request->file('archivo')->store($companyId.'/'.$subdirectorio, 's3');

        return response()->json(['path' => $path], 201);
    }
}
