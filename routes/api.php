<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InformeController;
use App\Http\Controllers\Api\Tecnico\OrdenController;
use App\Http\Controllers\Api\Tecnico\ReporteController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autenticación (pública)
|--------------------------------------------------------------------------
*/
Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');

/*
|--------------------------------------------------------------------------
| Rutas protegidas con token Sanctum
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::delete('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    /*
     | Técnico: órdenes
     */
    Route::prefix('tecnico')->name('api.tecnico.')->group(function () {

        Route::get('mis-ordenes', [OrdenController::class, 'index'])
            ->name('mis-ordenes');

        Route::get('ordenes/{orden}', [OrdenController::class, 'show'])
            ->name('ordenes.show');

        Route::post('ordenes/{orden}/iniciar', [OrdenController::class, 'iniciar'])
            ->name('ordenes.iniciar');

        Route::post('ordenes/{orden}/cerrar-vigilancia', [OrdenController::class, 'cerrarVigilancia'])
            ->name('ordenes.cerrar-vigilancia');

        /*
         | Técnico: reportes
         */
        Route::post('ordenes/{orden}/reporte', [ReporteController::class, 'store'])
            ->name('ordenes.reporte.store');

        Route::put('ordenes/{orden}/reporte', [ReporteController::class, 'update'])
            ->name('ordenes.reporte.update');
    });

    /*
     | Subida de archivos a S3
     */
    Route::post('/upload', [UploadController::class, 'store'])->name('api.upload');

    /*
     | Informes (Power BI)
     */
    Route::middleware(SetTenantContext::class)
        ->post('/informes/reporte', [InformeController::class, 'reporte'])
        ->name('api.informes.reporte');
});
