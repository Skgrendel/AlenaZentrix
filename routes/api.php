<?php

use App\Http\Controllers\Api\ArchivoController;
use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogoController;
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
Route::middleware(['auth:sanctum', SetTenantContext::class])->group(function () {

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
        Route::get('ordenes/{orden}/reporte', [ReporteController::class, 'show'])
            ->name('ordenes.reporte.show');

        Route::post('ordenes/{orden}/reporte', [ReporteController::class, 'store'])
            ->name('ordenes.reporte.store');

        Route::put('ordenes/{orden}/reporte', [ReporteController::class, 'update'])
            ->name('ordenes.reporte.update');
    });

    /*
     | Auditorías (revisión de hallazgos de Fraudes/Revisión de Medidores)
     */
    Route::prefix('auditorias')->name('api.auditorias.')->group(function () {
        Route::get('{auditoria}', [AuditoriaController::class, 'show'])->name('show');
        Route::put('{auditoria}', [AuditoriaController::class, 'guardar'])->name('guardar');
    });

    /*
     | Catálogos (selects de los formularios)
     */
    Route::prefix('catalogos')->name('api.catalogos.')->group(function () {
        Route::get('tipos-comercio', [CatalogoController::class, 'tiposComercio'])->name('tipos-comercio');
        Route::get('marcas-medidor', [CatalogoController::class, 'marcasMedidor'])->name('marcas-medidor');
        Route::get('marcas-regulador', [CatalogoController::class, 'marcasRegulador'])->name('marcas-regulador');
        Route::get('tipos-regulador', [CatalogoController::class, 'tiposRegulador'])->name('tipos-regulador');
        Route::get('tipos-anomalia', [CatalogoController::class, 'tiposAnomalia'])->name('tipos-anomalia');
        Route::get('tipos-imposibilidad', [CatalogoController::class, 'tiposImposibilidad'])->name('tipos-imposibilidad');
        Route::get('tipos-notificacion-alerta', [CatalogoController::class, 'tiposNotificacionAlerta'])->name('tipos-notificacion-alerta');
        Route::get('convenciones', [CatalogoController::class, 'convenciones'])->name('convenciones');
        Route::get('resultados-reporte', [CatalogoController::class, 'resultadosReporte'])->name('resultados-reporte');
    });

    /*
     | Archivos: proxy seguro para archivos de S3
     */
    Route::get('/archivos/{path}', [ArchivoController::class, 'show'])
        ->where('path', '.*')
        ->name('api.archivos.show');

    /*
     | Subida de archivos a S3
     */
    Route::post('/upload', [UploadController::class, 'store'])->name('api.upload');

    /*
     | Informes (Power BI)
     */
    Route::post('/informes/reporte', [InformeController::class, 'reporte'])
        ->name('api.informes.reporte');
});
