<?php

use App\Enums\EstadoSurveyRegistro;
use App\Enums\TipoServicio;
use App\Exports\SurveyAuditoria\FiltrosSurveyExportacion;
use App\Exports\SurveyAuditoria\HojaAuditoriaExport;
use App\Exports\SurveyAuditoria\SurveyAuditoriaExport;
use App\Livewire\Coordinador\SurveyAuditoria\SurveyRegistrosIndex;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use App\Models\ubicacion;
use App\Models\User;
use App\Services\coordinador\DataGisServices;
use App\Services\survey123\AuditoriaSurveyRegistro;
use App\Services\survey123\RequisitosDeFotos;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as TipoExcel;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mock(DataGisServices::class, function ($mock): void {
        $mock->shouldReceive('consultarContrato')->andReturn(['error' => 'Servicio GIS no disponible']);
    });
});

/** @param array<string, mixed> $atributos */
function registroExportable(Company $empresa, array $atributos = []): SurveyRegistro
{
    return SurveyRegistro::factory()->create($atributos + ['company_id' => $empresa->id]);
}

/**
 * Marca el registro como auditado con los hallazgos indicados (sin correr las reglas reales).
 *
 * @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $hallazgos  [regla, severidad, campo, mensaje]
 */
function auditadoCon(SurveyRegistro $registro, array $hallazgos = []): SurveyRegistro
{
    $lista = array_map(fn (array $h): array => ['regla' => $h[0], 'severidad' => $h[1], 'campo' => $h[2], 'mensaje' => $h[3]], $hallazgos);

    $registro->forceFill([
        'hallazgos' => $lista,
        'total_errores' => count(array_filter($lista, fn ($h) => $h['severidad'] === 'error')),
        'total_alertas' => count(array_filter($lista, fn ($h) => $h['severidad'] === 'alerta')),
        'auditado_en' => now(),
    ])->save();

    return $registro;
}

function libroDe(SurveyAuditoriaExport $export): Spreadsheet
{
    $ruta = tempnam(sys_get_temp_dir(), 'sx').'.xlsx';
    file_put_contents($ruta, Excel::raw($export, TipoExcel::XLSX));

    try {
        return IOFactory::load($ruta);
    } finally {
        @unlink($ruta);
    }
}

function exportacionDe(Company $empresa, array $filtros = []): SurveyAuditoriaExport
{
    return SurveyAuditoriaExport::conFiltros(new FiltrosSurveyExportacion($empresa->id, ...$filtros));
}

/**
 * Filas de una hoja como arreglos asociativos encabezado => valor (la primera fila de datos es la 0).
 *
 * @return array<int, array<string, mixed>>
 */
function filasDeHoja(Worksheet $hoja): array
{
    $matriz = $hoja->toArray(null, false, false, false);
    $encabezados = array_shift($matriz);

    return array_map(fn (array $fila): array => array_combine($encabezados, $fila), $matriz);
}

/** Orden de auditoría con un reporte del auditor ya comparado contra el registro. */
function visitaDeAuditoria(SurveyRegistro $registro, array $datos = [], string $nombre = 'Carlos'): reportes
{
    $auditor = personals::factory()->create(['company_id' => $registro->company_id, 'nombres' => $nombre, 'apellidos' => 'Pérez']);

    $orden = OrdenJob::factory()->create([
        'company_id' => $registro->company_id,
        'personals_id' => $auditor->id,
        'tipo_servicio' => TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
        'survey_registro_id' => $registro->id,
        'lectura_referencia' => $registro->lectura_medidor,
        'serie_medidor_referencia' => $registro->serie_medidor,
        'latitud_referencia' => $registro->latitud,
        'longitud_referencia' => $registro->longitud,
    ]);

    $punto = ubicacion::create(['latitud' => (string) $registro->latitud, 'longitud' => (string) $registro->longitud]);

    return reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'ubicacions_id' => $punto->id,
        'datos_servicio' => $datos + [
            'categoria_campo' => $registro->categoria,
            'suspension_ejecutada' => true,
            'metodo_suspension' => $registro->metodo_suspension,
            'serie_medidor' => $registro->serie_medidor,
            'lectura_medidor' => $registro->lectura_medidor,
        ],
    ])->fresh();
}

function usuarioConRol(string $rol, ?Company $empresa = null): User
{
    test()->seed(PermissionsSeeder::class);

    $user = User::factory()->create(['company_id' => ($empresa ?? Company::factory()->create())->id]);
    $user->assignRole($rol);

    return $user;
}

test('la hoja Auditoría replica el formato del libro: variables con su Validar y cierre de errores, alertas y estado', function () {
    $empresa = Company::factory()->create();
    registroExportable($empresa);

    $libro = libroDe(exportacionDe($empresa));

    expect($libro->getSheetNames())->toBe(['Auditoría', 'Visitas de auditoría', 'Resumen']);

    $variables = [
        'ObjectID', 'GlobalID', 'Fecha de registro', 'Nombre del Técnico', 'Contrato', 'Orden de Trabajo',
        'Categoría en campo', 'Serie de medidor instalado', 'Lectura del medidor instalado', '¿Qué actividad va a ejecutar?',
        '¿Se pudo ejecutar la suspensión?', '¿Método de suspensión instalado?', 'Serie de la sonda utilizada', 'Serie del Caps',
        'Motivo por el cual no se pudo ejecutar la suspensión', 'Fotografías de la fachada (3 fotos)', 'Fotografías del medidor', 'Fotografía del IVT',
        'Fotografías de la suspensión, reconexión ejecutada o novedad encontrada', 'JUSTIFICACIÓN', 'CreationDate', 'EditDate', 'x', 'y',
    ];

    $esperados = [];
    foreach ($variables as $variable) {
        array_push($esperados, $variable, "Validar ▸ {$variable}");
    }
    array_push($esperados, 'Estado de asignación', 'Errores ✖', 'Alertas ⚠', 'Estado de la fila');

    $hoja = $libro->getSheetByName('Auditoría');
    $encabezados = $hoja->toArray(null, false, false, false)[0];

    expect($encabezados)->toBe($esperados)
        ->and(implode('|', $encabezados))->not->toContain('Base:')
        ->and(implode('|', $encabezados))->not->toContain('Distancia a la base')
        ->and(implode('|', $encabezados))->not->toContain('Ubicación vs base')
        ->and(implode('|', $encabezados))->not->toContain('reconexión?')
        ->and($hoja->getFreezePane())->toBe('A2')
        ->and($hoja->getAutoFilter()->getRange())->toStartWith('A1:')
        ->and($hoja->getStyle('A1')->getFont()->getBold())->toBeTrue()
        ->and($hoja->getStyle('B1')->getFill()->getStartColor()->getRGB())->toBe('2E7D32');
});

test('cada columna Validar muestra OK, el error o la alerta; varios hallazgos se unen con una barra', function () {
    $empresa = Company::factory()->create();

    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '311111111']), [
        ['tecnico', 'alerta', 'tecnico', 'El nombre del técnico no incluye apellido.'],
        ['serie_medidor', 'error', 'serie_medidor', 'Falta la serie del medidor.'],
        ['serie_medidor', 'alerta', 'serie_medidor', 'La serie del medidor está repetida en otro registro.'],
    ]);

    $fila = filasDeHoja(libroDe(exportacionDe($empresa))->getSheetByName('Auditoría'))[0];

    expect($fila['Validar ▸ Nombre del Técnico'])->toBe('⚠ El nombre del técnico no incluye apellido.')
        ->and($fila['Validar ▸ Serie de medidor instalado'])->toBe('✖ Falta la serie del medidor. | ⚠ La serie del medidor está repetida en otro registro.')
        ->and($fila['Validar ▸ Contrato'])->toBe('OK')
        ->and($fila['Validar ▸ Orden de Trabajo'])->toBe('OK')
        ->and($fila['Errores ✖'])->toBe(1)
        ->and($fila['Alertas ⚠'])->toBe(2)
        ->and($fila['Estado de la fila'])->toBe('Con errores');
});

test('un registro sin auditar deja las validaciones vacías', function () {
    $empresa = Company::factory()->create();
    registroExportable($empresa);

    $fila = filasDeHoja(libroDe(exportacionDe($empresa))->getSheetByName('Auditoría'))[0];

    expect($fila['Validar ▸ Contrato'])->toBeNull()
        ->and($fila['Validar ▸ x'])->toBeNull()
        ->and($fila['Errores ✖'])->toBeNull()
        ->and($fila['Estado de la fila'])->toBe('Sin auditar');
});

test('el estado de la fila es OK, Con alertas o Con errores', function () {
    $empresa = Company::factory()->create();

    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000001']));
    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000002']), [['fecha', 'alerta', 'fecha_registro', 'Pasaron más de 24 horas.']]);
    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000003']), [['fecha', 'alerta', 'fecha_registro', 'a'], ['lectura', 'error', 'lectura_medidor', 'Falta la lectura del medidor.']]);

    $estados = collect(filasDeHoja(libroDe(exportacionDe($empresa))->getSheetByName('Auditoría')))
        ->pluck('Estado de la fila', 'Orden de Trabajo')->all();

    expect($estados)->toBe([300000001 => 'OK', 300000002 => 'Con alertas', 300000003 => 'Con errores']);
});

test('los hallazgos de ubicación y de fotos van en sus columnas, las fotos se cuentan por tipo', function () {
    $empresa = Company::factory()->create();
    $registro = auditadoCon(registroExportable($empresa), [
        ['ubicacion', 'error', 'ubicacion', 'Falta la ubicación de la visita.'],
        ['fotos_minimas', 'error', 'foto_fachada', 'Hay 2 foto(s) de fachada y se requieren al menos 3.'],
        ['fotos_repetidas', 'error', 'fotos', 'Hay fotos repetidas dentro del mismo registro: a.jpg, b.jpg.'],
    ]);

    foreach (['foto_fachada' => 2, 'foto_medidor' => 1, 'foto_actividad' => 3] as $campo => $cantidad) {
        foreach (range(1, $cantidad) as $n) {
            SurveyAdjunto::create([
                'survey_registro_id' => $registro->id,
                'attachment_id' => random_int(1, 1_000_000_000),
                'campo' => $campo,
                'nombre' => "{$campo}-{$n}.jpg",
            ]);
        }
    }

    $fila = filasDeHoja(libroDe(exportacionDe($empresa))->getSheetByName('Auditoría'))[0];

    expect($fila['Fotografías de la fachada (3 fotos)'])->toBe(2)
        ->and($fila['Fotografías del medidor'])->toBe(1)
        ->and($fila['Fotografías de la suspensión, reconexión ejecutada o novedad encontrada'])->toBe(3)
        ->and($fila['Validar ▸ Fotografías de la fachada (3 fotos)'])->toContain('✖ Hay 2 foto(s) de fachada')->toContain('fotos repetidas')
        ->and($fila['Validar ▸ Fotografías del medidor'])->toBe('OK')
        ->and($fila['Validar ▸ x'])->toBe('✖ Falta la ubicación de la visita.')
        ->and($fila['Validar ▸ y'])->toBe('✖ Falta la ubicación de la visita.');
});

test('incluye identificadores, coordenadas, fechas y estado de asignación', function () {
    $empresa = Company::factory()->create();
    registroExportable($empresa, [
        'object_id' => 777,
        'global_id' => 'dd600e38-8bd0-44e3-b15d-1b73e68eff57',
        'latitud' => 10.407333,
        'longitud' => -75.488048,
        'suspension_ejecutada' => true,
        'estado' => EstadoSurveyRegistro::Asignado,
        'creado_en_origen' => '2026-11-10 08:00:00',
        'editado_en_origen' => '2026-11-10 09:30:00',
        'fecha_registro' => '2026-11-10 07:45:00',
    ]);

    $libro = libroDe(exportacionDe($empresa));
    $hoja = $libro->getSheetByName('Auditoría');
    $fila = filasDeHoja($hoja)[0];

    expect($fila['ObjectID'])->toBe(777)
        ->and($fila['GlobalID'])->toBe('dd600e38-8bd0-44e3-b15d-1b73e68eff57')
        ->and($fila['x'])->toEqual(-75.488048)
        ->and($fila['y'])->toEqual(10.407333)
        ->and($fila['¿Se pudo ejecutar la suspensión?'])->toBe('si')
        ->and($fila['Estado de asignación'])->toBe('Asignado a campo')
        ->and($hoja->getCell('E2')->getFormattedValue())->toBe('2026-11-10 07:45:00')
        ->and($hoja->getCell('AO2')->getFormattedValue())->toBe('2026-11-10 08:00:00');
});

test('la hoja de visitas muestra lo reportado, lo encontrado y la validación por campo', function () {
    $empresa = Company::factory()->create();
    $registro = registroExportable($empresa, [
        'orden_trabajo' => '319890863', 'contrato' => '296192', 'serie_medidor' => '981797', 'lectura_medidor' => '6972',
        'metodo_suspension' => 'taponamiento_acometida',
    ]);
    $reporte = visitaDeAuditoria($registro, ['serie_medidor' => '111111']);

    registroExportable($empresa, ['orden_trabajo' => '300000009']);

    $hoja = libroDe(exportacionDe($empresa))->getSheetByName('Visitas de auditoría');
    $filas = filasDeHoja($hoja);

    expect($reporte->resultado_comparacion)->toBe('no_conforme')
        ->and($filas)->toHaveCount(1);

    $fila = $filas[0];

    expect($fila['OT original'])->toBe(319890863)
        ->and($fila['Contrato'])->toBe(296192)
        ->and($fila['Auditor'])->toBe('Carlos Pérez')
        ->and($fila['Resultado final'])->toBe('No conforme')
        ->and($fila['Serie del medidor (reportado)'])->toEqual('981797')
        ->and($fila['Serie del medidor (encontrado)'])->toEqual('111111')
        ->and($fila['Validar ▸ Serie del medidor'])->toBe('✖ No coincide')
        ->and($fila['Validar ▸ Lectura del medidor'])->toBe('Coincide')
        ->and($fila['Validar ▸ Método de suspensión'])->toBe('Coincide')
        ->and($fila['Validar ▸ Serie de la sonda'])->toBeNull()
        ->and($hoja->getFreezePane())->toBe('A2');
});

test('la hoja de visitas marca como sin dato lo que el auditor no registró', function () {
    $empresa = Company::factory()->create();
    $registro = registroExportable($empresa);
    visitaDeAuditoria($registro, ['lectura_medidor' => null]);

    $fila = filasDeHoja(libroDe(exportacionDe($empresa))->getSheetByName('Visitas de auditoría'))[0];

    expect($fila['Validar ▸ Lectura del medidor'])->toBe('⚠ Sin dato')
        ->and($fila['Lectura del medidor (encontrado)'])->toBeNull();
});

test('la hoja resumen totaliza registros, técnicos, reglas y visitas', function () {
    $empresa = Company::factory()->create();

    auditadoCon(registroExportable($empresa, ['tecnico' => 'Luis Quiroga']));
    auditadoCon(registroExportable($empresa, ['tecnico' => 'Luis Quiroga']), [
        ['fecha', 'alerta', 'fecha_registro', 'a'],
    ]);
    auditadoCon(registroExportable($empresa, ['tecnico' => 'Ana Gómez']), [
        ['lectura', 'error', 'lectura_medidor', 'b'],
        ['fecha', 'alerta', 'fecha_registro', 'a'],
    ]);
    auditadoCon(registroExportable($empresa, ['tecnico' => 'Ana Gómez']), [
        ['lectura', 'error', 'lectura_medidor', 'b'],
    ]);
    registroExportable($empresa, ['tecnico' => 'Ana Gómez']);

    visitaDeAuditoria(registroExportable($empresa, ['tecnico' => 'Pedro Ruiz']));
    visitaDeAuditoria(registroExportable($empresa), ['suspension_ejecutada' => false, 'metodo_suspension' => null, 'motivo_no_ejecucion' => 'predio_cerrado']);
    visitaDeAuditoria(registroExportable($empresa), ['serie_medidor' => '999999']);

    $hoja = libroDe(exportacionDe($empresa))->getSheetByName('Resumen');
    $celdas = collect($hoja->toArray(null, false, false, false))->filter(fn ($f) => $f[0] !== null)->mapWithKeys(fn ($f) => [$f[0] => array_slice($f, 1)]);

    expect($celdas['Registros'][0])->toBe(8)
        ->and($celdas['Con errores'][0])->toBe(2)
        ->and($celdas['Con alertas'][0])->toBe(2)
        ->and($celdas['OK (auditados sin hallazgos)'][0])->toBe(1)
        ->and($celdas['Sin auditar'][0])->toBe(4)
        ->and(array_slice($celdas['Ana Gómez'], 0, 4))->toBe([3, 2, 1, 0])
        ->and(array_slice($celdas['Luis Quiroga'], 0, 4))->toBe([4, 0, 1, 1])
        ->and($celdas['lectura'])->toBe(['Error', 2, 2, null])
        ->and($celdas['fecha'])->toBe(['Alerta', 2, 2, null])
        ->and($celdas['Conforme'][0])->toBe(1)
        ->and($celdas['No conforme'][0])->toBe(1)
        ->and($celdas['No verificable'][0])->toBe(1)
        ->and($celdas['Total de visitas'][0])->toBe(3);
});

test('el filtro de estado deja solo pendientes o asignados', function () {
    $empresa = Company::factory()->create();
    registroExportable($empresa, ['orden_trabajo' => '300000001']);
    registroExportable($empresa, ['orden_trabajo' => '300000002', 'estado' => EstadoSurveyRegistro::Asignado]);

    $ots = fn (EstadoSurveyRegistro $e) => (new FiltrosSurveyExportacion($empresa->id, estado: $e))->query()->pluck('orden_trabajo')->all();

    expect($ots(EstadoSurveyRegistro::Pendiente))->toBe(['300000001'])
        ->and($ots(EstadoSurveyRegistro::Asignado))->toBe(['300000002']);

    $hoja = libroDe(exportacionDe($empresa, ['estado' => EstadoSurveyRegistro::Asignado]))->getSheetByName('Auditoría');
    expect(filasDeHoja($hoja))->toHaveCount(1);
});

test('los filtros de solo errores y solo alertas', function () {
    $empresa = Company::factory()->create();
    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000001']));
    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000002']), [['fecha', 'alerta', 'fecha_registro', 'a']]);
    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000003']), [['lectura', 'error', 'lectura_medidor', 'b']]);

    $ots = fn (array $f) => (new FiltrosSurveyExportacion($empresa->id, ...$f))->query()->orderBy('orden_trabajo')->pluck('orden_trabajo')->all();

    expect($ots(['soloConErrores' => true]))->toBe(['300000003'])
        ->and($ots(['soloConAlertas' => true]))->toBe(['300000002'])
        ->and($ots([]))->toHaveCount(3);
});

test('el filtro por rango de fechas incluye los días de los extremos', function () {
    $empresa = Company::factory()->create();
    registroExportable($empresa, ['orden_trabajo' => '300000001', 'fecha_registro' => '2026-11-01 23:59:00']);
    registroExportable($empresa, ['orden_trabajo' => '300000002', 'fecha_registro' => '2026-11-05 00:00:00']);
    registroExportable($empresa, ['orden_trabajo' => '300000003', 'fecha_registro' => '2026-11-10 23:30:00']);
    registroExportable($empresa, ['orden_trabajo' => '300000004', 'fecha_registro' => '2026-11-11 00:00:00']);

    $filtros = new FiltrosSurveyExportacion($empresa->id, desde: Carbon::parse('2026-11-02'), hasta: Carbon::parse('2026-11-10'));

    expect($filtros->query()->orderBy('orden_trabajo')->pluck('orden_trabajo')->all())->toBe(['300000002', '300000003']);
});

test('el filtro por técnico y por ids', function () {
    $empresa = Company::factory()->create();
    $luis = registroExportable($empresa, ['tecnico' => 'Luis Quiroga']);
    $ana = registroExportable($empresa, ['tecnico' => 'Ana Gómez']);
    registroExportable($empresa, ['tecnico' => 'Ana Gómez']);

    expect((new FiltrosSurveyExportacion($empresa->id, tecnico: 'quiroga'))->query()->pluck('id')->all())->toBe([$luis->id])
        ->and((new FiltrosSurveyExportacion($empresa->id, tecnico: 'Ana'))->query()->count())->toBe(2)
        ->and((new FiltrosSurveyExportacion($empresa->id, ids: [$luis->id, $ana->id]))->query()->count())->toBe(2)
        ->and((new FiltrosSurveyExportacion($empresa->id, ids: []))->query()->count())->toBe(0);
});

test('la exportación solo incluye registros y visitas de la empresa indicada', function () {
    $propia = Company::factory()->create();
    $ajena = Company::factory()->create();

    registroExportable($propia, ['orden_trabajo' => '300000001']);
    registroExportable($ajena, ['orden_trabajo' => '399999999']);
    visitaDeAuditoria(registroExportable($ajena, ['orden_trabajo' => '388888888']));

    $libro = libroDe(exportacionDe($propia));

    $ots = collect(filasDeHoja($libro->getSheetByName('Auditoría')))->pluck('Orden de Trabajo')->all();

    expect($ots)->toBe([300000001])
        ->and(filasDeHoja($libro->getSheetByName('Visitas de auditoría')))->toBeEmpty();
});

test('la acción Exportar Excel de la tabla descarga solo la empresa del usuario y respeta los filtros', function () {
    $user = usuarioConRol('Coordinador');
    $ajena = Company::factory()->create();

    auditadoCon(registroExportable($user->company, ['orden_trabajo' => '300000001']), [['lectura', 'error', 'lectura_medidor', 'b']]);
    auditadoCon(registroExportable($user->company, ['orden_trabajo' => '300000002']));
    registroExportable($ajena, ['orden_trabajo' => '399999999']);

    $descarga = fn (array $filtros = []) => Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->filterTable('con_errores', $filtros['con_errores'] ?? null)
        ->callTableAction('exportar_excel');

    $ruta = tempnam(sys_get_temp_dir(), 'sx').'.xlsx';

    $componente = $descarga();
    $componente->assertFileDownloaded();
    file_put_contents($ruta, base64_decode($componente->effects['download']['content']));
    $todos = collect(filasDeHoja(IOFactory::load($ruta)->getSheetByName('Auditoría')))->pluck('Orden de Trabajo')->sort()->values()->all();

    $componente = $descarga(['con_errores' => true]);
    file_put_contents($ruta, base64_decode($componente->effects['download']['content']));
    $conErrores = collect(filasDeHoja(IOFactory::load($ruta)->getSheetByName('Auditoría')))->pluck('Orden de Trabajo')->all();
    @unlink($ruta);

    expect($todos)->toBe([300000001, 300000002])
        ->and($conErrores)->toBe([300000001]);
});

test('la acción masiva Exportar seleccionados descarga solo los registros elegidos', function () {
    $user = usuarioConRol('Coordinador');
    $uno = registroExportable($user->company, ['orden_trabajo' => '300000001']);
    $dos = registroExportable($user->company, ['orden_trabajo' => '300000002']);

    $componente = Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->callTableBulkAction('exportar_seleccionados', [$dos]);

    $componente->assertFileDownloaded();

    $ruta = tempnam(sys_get_temp_dir(), 'sx').'.xlsx';
    file_put_contents($ruta, base64_decode($componente->effects['download']['content']));
    $ots = collect(filasDeHoja(IOFactory::load($ruta)->getSheetByName('Auditoría')))->pluck('Orden de Trabajo')->all();
    @unlink($ruta);

    expect($ots)->toBe([300000002])->and($uno->id)->not->toBe($dos->id);
});

test('sin el permiso ordenes.ver las acciones de exportación no se ven ni se pueden ejecutar', function () {
    $user = usuarioConRol('Auditor');
    registroExportable($user->company);

    Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->assertTableActionHidden('exportar_excel')
        ->assertTableBulkActionHidden('exportar_seleccionados');

});

test('los textos que empiezan con = + - @ se escriben como texto y no como fórmula', function () {
    $empresa = Company::factory()->create();
    $registro = registroExportable($empresa, [
        'tecnico' => '=HYPERLINK("http://x.test","Luis")',
        'justificacion' => '@SUM(1+1)',
        'serie_medidor' => '+1+1',
        'motivo_no_ejecucion' => '-2+3',
        'categoria' => "\t=1+1",
    ]);

    $hoja = libroDe(exportacionDe($empresa))->getSheetByName('Auditoría');
    $fila = filasDeHoja($hoja)[0];

    expect($fila['Nombre del Técnico'])->toBe('=HYPERLINK("http://x.test","Luis")')
        ->and($hoja->getCell('G2')->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($hoja->getCell('G2')->getStyle()->getQuotePrefix())->toBeTrue()
        ->and($fila['JUSTIFICACIÓN'])->toBe('@SUM(1+1)')
        ->and($fila['Serie de medidor instalado'])->toBe('+1+1')
        ->and($fila['Motivo por el cual no se pudo ejecutar la suspensión'])->toBe('-2+3')
        ->and($registro->id)->not->toBeNull();

    $hojaResumen = libroDe(exportacionDe($empresa))->getSheetByName('Resumen');
    expect($hojaResumen->getCell('A13')->getDataType())->toBe(DataType::TYPE_STRING);
});

test('el comando exporta el libro a la ruta indicada', function () {
    $empresa = Company::factory()->create();
    registroExportable($empresa, ['orden_trabajo' => '300000001']);
    registroExportable($empresa, ['orden_trabajo' => '300000002']);

    $directorio = sys_get_temp_dir().'/exp-'.Str::random(8);
    $ruta = $directorio.'/salida.xlsx';

    $this->artisan('survey123:exportar', ['--salida' => $ruta])
        ->expectsOutputToContain('2 registros exportados')
        ->assertSuccessful();

    expect(file_exists($ruta))->toBeTrue();

    $libro = IOFactory::load($ruta);
    expect($libro->getSheetNames())->toBe(['Auditoría', 'Visitas de auditoría', 'Resumen'])
        ->and(filasDeHoja($libro->getSheetByName('Auditoría')))->toHaveCount(2);

    File::deleteDirectory($directorio);
});

test('el comando aplica estado, errores, alertas y fechas, y solo la empresa indicada', function () {
    $empresa = Company::factory()->create();
    $otra = Company::factory()->create();

    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000001', 'fecha_registro' => '2026-11-03 10:00:00', 'estado' => EstadoSurveyRegistro::Asignado]), [['lectura', 'error', 'lectura_medidor', 'b'], ['fecha', 'alerta', 'fecha_registro', 'a']]);
    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000002', 'fecha_registro' => '2026-10-03 10:00:00']), [['lectura', 'error', 'lectura_medidor', 'b']]);
    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000003', 'fecha_registro' => '2026-11-04 10:00:00']));
    registroExportable($otra, ['orden_trabajo' => '399999999', 'fecha_registro' => '2026-11-03 10:00:00']);

    $directorio = sys_get_temp_dir().'/exp-'.Str::random(8);
    $ruta = $directorio.'/filtrado.xlsx';

    $this->artisan('survey123:exportar', [
        '--company' => $empresa->id, '--estado' => 'asignado', '--con-errores' => true, '--con-alertas' => true,
        '--desde' => '2026-11-01', '--hasta' => '2026-11-30', '--salida' => $ruta,
    ])->expectsOutputToContain('1 registros exportados')->assertSuccessful();

    $ots = collect(filasDeHoja(IOFactory::load($ruta)->getSheetByName('Auditoría')))->pluck('Orden de Trabajo')->all();
    expect($ots)->toBe([300000001]);

    File::deleteDirectory($directorio);
});

test('el comando pide --company si hay varias empresas y rechaza un estado inválido', function () {
    Company::factory()->count(2)->create();

    $this->artisan('survey123:exportar')->expectsOutputToContain('--company')->assertFailed();

    $empresa = Company::query()->first();

    $this->artisan('survey123:exportar', ['--company' => $empresa->id, '--estado' => 'otro'])
        ->expectsOutputToContain('pendiente')->assertFailed();
});

test('el comando usa la carpeta exportaciones por defecto', function () {
    $empresa = Company::factory()->create();
    registroExportable($empresa);

    $antes = glob(storage_path('app/exportaciones/auditoria-suspensiones-*.xlsx')) ?: [];

    $this->artisan('survey123:exportar')->assertSuccessful();

    $nuevos = array_diff(glob(storage_path('app/exportaciones/auditoria-suspensiones-*.xlsx')) ?: [], $antes);
    expect($nuevos)->toHaveCount(1);

    array_map('unlink', $nuevos);
});

test('exporta 5.000 registros por bloques sin agotar la memoria', function () {
    $empresa = Company::factory()->create();
    $ahora = now()->toDateTimeString();
    $filas = [];

    foreach (SurveyRegistro::factory()->count(5000)->raw(['company_id' => $empresa->id]) as $i => $datos) {
        $conHallazgos = $i % 5 === 0;

        $filas[] = array_merge($datos, [
            'id' => (string) Str::uuid(),
            'estado' => $i % 2 === 0 ? 'pendiente' : 'asignado',
            'orden_trabajo' => (string) (300000000 + $i),
            'hallazgos' => json_encode($conHallazgos ? [['regla' => 'lectura', 'severidad' => 'error', 'campo' => 'lectura_medidor', 'mensaje' => 'Falta la lectura del medidor.']] : []),
            'total_errores' => $conHallazgos ? 1 : 0,
            'total_alertas' => 0,
            'auditado_en' => $ahora,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    foreach (array_chunk($filas, 500) as $bloque) {
        SurveyRegistro::query()->withoutGlobalScopes()->insert($bloque);
    }

    $adjuntos = [];
    foreach (array_slice($filas, 0, 5000) as $i => $fila) {
        $adjuntos[] = ['id' => (string) Str::uuid(), 'survey_registro_id' => $fila['id'], 'attachment_id' => $i + 1, 'campo' => 'foto_fachada', 'nombre' => "f{$i}.jpg", 'created_at' => $ahora, 'updated_at' => $ahora];
    }
    foreach (array_chunk($adjuntos, 500) as $bloque) {
        DB::table('survey_adjuntos')->insert($bloque);
    }

    expect(SurveyRegistro::query()->withoutGlobalScopes()->count())->toBe(5000);

    Storage::fake('local');
    unset($filas, $adjuntos);
    gc_collect_cycles();
    memory_reset_peak_usage();
    $memoriaAntes = memory_get_usage(true);
    $inicio = microtime(true);

    Excel::store(exportacionDe($empresa), 'volumen.xlsx', 'local');

    $segundos = microtime(true) - $inicio;
    $picoMb = (memory_get_peak_usage(true) - $memoriaAntes) / 1048576;

    fwrite(STDERR, sprintf("\n[volumen] 5000 registros: %.1f s, pico +%.0f MB\n", $segundos, $picoMb));

    $ruta = Storage::disk('local')->path('volumen.xlsx');
    $info = collect(IOFactory::createReader('Xlsx')->listWorksheetInfo($ruta))->pluck('totalRows', 'worksheetName');

    $filasAuditoria = (int) $info['Auditoría'];
    Storage::disk('local')->delete('volumen.xlsx');
    unset($info);
    gc_collect_cycles();

    expect($filasAuditoria)->toBe(5001)
        ->and($picoMb)->toBeLessThan(512);
});

/**
 * Valores de las columnas «Validar ▸ …» de una fila de la hoja Auditoría.
 *
 * @param  array<string, mixed>  $fila
 * @return array<string, string>
 */
function validacionesDe(array $fila): array
{
    return collect($fila)
        ->filter(fn ($valor, $encabezado) => str_starts_with((string) $encabezado, 'Validar ▸ ') && $valor !== null)
        ->mapWithKeys(fn ($valor, $encabezado) => [substr((string) $encabezado, strlen('Validar ▸ ')) => $valor])
        ->all();
}

/** @return array<string, array<int, string>> campo del hallazgo => columnas donde debe aparecer */
function columnasPorCampo(): array
{
    $actividad = 'Fotografías de la suspensión, reconexión ejecutada o novedad encontrada';

    return [
        'tecnico' => ['Nombre del Técnico'],
        'contrato' => ['Contrato'],
        'orden_trabajo' => ['Orden de Trabajo'],
        'categoria' => ['Categoría en campo'],
        'serie_medidor' => ['Serie de medidor instalado'],
        'lectura_medidor' => ['Lectura del medidor instalado'],
        'fecha_registro' => ['Fecha de registro'],
        'actividad' => ['¿Qué actividad va a ejecutar?'],
        'suspension_ejecutada' => ['¿Se pudo ejecutar la suspensión?'],
        'metodo_suspension' => ['¿Método de suspensión instalado?'],
        'serie_sonda' => ['Serie de la sonda utilizada'],
        'serie_caps' => ['Serie del Caps'],
        'motivo_no_ejecucion' => ['Motivo por el cual no se pudo ejecutar la suspensión'],
        'justificacion' => ['JUSTIFICACIÓN'],
        'foto_fachada' => ['Fotografías de la fachada (3 fotos)'],
        'fotos' => ['Fotografías de la fachada (3 fotos)'],
        'foto_medidor' => ['Fotografías del medidor'],
        'foto_ivt' => ['Fotografía del IVT'],
        'foto_actividad' => [$actividad],
        'foto_actividad1' => [$actividad],
        'foto_actividad2' => [$actividad],
        'foto_actividad3' => [$actividad],
        'ubicacion' => ['x', 'y'],
    ];
}

/** @return array<int, array{0: string, 1: string}> celdas [hoja, coordenada] que son fórmula o texto peligroso sin protección */
function celdasDeTextoSinProteger(Spreadsheet $libro): array
{
    $sinProteger = [];

    foreach ($libro->getAllSheets() as $hoja) {
        foreach ($hoja->getCellCollection()->getCoordinates() as $coordenada) {
            $celda = $hoja->getCell($coordenada);
            $valor = $celda->getValue();

            if ($celda->getDataType() === DataType::TYPE_FORMULA) {
                $sinProteger[] = [$hoja->getTitle(), $coordenada];
            } elseif (is_string($valor) && preg_match('/^[=+\-@\t\r]/', $valor) === 1 && ! $celda->getStyle()->getQuotePrefix()) {
                $sinProteger[] = [$hoja->getTitle(), $coordenada];
            }
        }
    }

    return $sinProteger;
}

function libroDeDescarga($componente): Spreadsheet
{
    $ruta = tempnam(sys_get_temp_dir(), 'sx').'.xlsx';
    file_put_contents($ruta, base64_decode($componente->effects['download']['content']));

    try {
        return IOFactory::load($ruta);
    } finally {
        @unlink($ruta);
    }
}

test('todo campo de hallazgo que produce la auditoría tiene una columna Validar', function () {
    $fuente = file_get_contents(app_path('Services/survey123/AuditoriaSurveyRegistro.php'));
    preg_match_all("/->hallazgo\('[a-z_]+', self::(?:ERROR|ALERTA), '([a-z_]+)'/", $fuente, $coincidencias);

    $campos = array_unique(array_merge(
        $coincidencias[1],
        ['serie_sonda', 'serie_caps'],
        array_keys(config('survey_auditoria.parametros.fotos_minimas')),
        [RequisitosDeFotos::ACTIVIDAD_ANTERIOR],
        array_keys(RequisitosDeFotos::FOTOS_NUEVAS),
    ));

    $cubiertos = (new HojaAuditoriaExport(SurveyRegistro::query()))->camposValidados();

    expect($coincidencias[1])->not->toBeEmpty()
        ->and(array_diff($campos, $cubiertos))->toBe([])
        ->and(array_diff(array_keys(columnasPorCampo()), $campos))->toBe([]);
});

test('cada hallazgo aterriza solo en la columna Validar de su campo', function () {
    $empresa = Company::factory()->create();
    $porOt = [];

    foreach (array_keys(columnasPorCampo()) as $i => $campo) {
        $ot = 300000100 + $i;
        $porOt[$ot] = $campo;
        auditadoCon(registroExportable($empresa, ['orden_trabajo' => (string) $ot]), [
            ['regla_'.$campo, 'error', $campo, "mensaje-{$campo}"],
            ['regla_'.$campo, 'alerta', $campo, "aviso-{$campo}"],
        ]);
    }

    $filas = collect(filasDeHoja(libroDe(exportacionDe($empresa))->getSheetByName('Auditoría')))->keyBy('Orden de Trabajo');

    foreach (columnasPorCampo() as $campo => $columnas) {
        $ot = array_search($campo, $porOt, true);
        $validaciones = validacionesDe($filas[$ot]);
        $conMensaje = array_keys(array_filter($validaciones, fn ($v) => str_contains($v, "mensaje-{$campo}") && str_contains($v, "aviso-{$campo}")));

        expect($conMensaje)->toBe($columnas, "El campo {$campo} debe verse solo en: ".implode(', ', $columnas));

        foreach ($columnas as $columna) {
            expect($validaciones[$columna])->toBe("✖ mensaje-{$campo} | ⚠ aviso-{$campo}");
        }

        expect($filas[$ot]['Errores ✖'])->toBe(1)->and($filas[$ot]['Alertas ⚠'])->toBe(1);
    }
});

test('con la auditoría real, ningún hallazgo se pierde en el libro', function () {
    $empresa = Company::factory()->create();

    $registro = registroExportable($empresa, [
        'tecnico' => 'Luis',
        'contrato' => 'abc',
        'orden_trabajo' => '12',
        'categoria' => 'otra',
        'actividad' => 'reconexion',
        'suspension_ejecutada' => true,
        'metodo_suspension' => 'sonda_ips',
        'serie_medidor' => null,
        'lectura_medidor' => '-4',
        'serie_sonda' => null,
        'serie_caps' => '0',
        'justificacion' => 'corta',
        'latitud' => null,
        'longitud' => null,
        'fecha_registro' => now()->addDay(),
    ]);

    foreach (['a.jpg', 'b.jpg'] as $n => $nombre) {
        SurveyAdjunto::create(['survey_registro_id' => $registro->id, 'attachment_id' => $n + 1, 'campo' => 'foto_fachada', 'nombre' => $nombre, 'hash' => 'h1']);
    }

    app(AuditoriaSurveyRegistro::class)->auditarYGuardar($registro);
    $hallazgos = $registro->fresh()->hallazgos;

    $fila = filasDeHoja(libroDe(exportacionDe($empresa))->getSheetByName('Auditoría'))[0];
    $textoValidaciones = implode(' || ', validacionesDe($fila));

    expect(count($hallazgos))->toBeGreaterThan(10)
        ->and(collect($hallazgos)->pluck('campo')->unique()->count())->toBeGreaterThan(8);

    foreach ($hallazgos as $hallazgo) {
        expect($textoValidaciones)->toContain($hallazgo['mensaje']);
    }

    expect($fila['Errores ✖'])->toBe($registro->fresh()->total_errores)
        ->and($fila['Alertas ⚠'])->toBe($registro->fresh()->total_alertas);
});

test('los colores de fila con errores y con alertas están en el archivo', function () {
    $empresa = Company::factory()->create();
    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000001', 'fecha_registro' => '2026-11-01 10:00:00']), [['lectura', 'error', 'lectura_medidor', 'b']]);
    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000002', 'fecha_registro' => '2026-11-02 10:00:00']), [['fecha', 'alerta', 'fecha_registro', 'a']]);
    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000003', 'fecha_registro' => '2026-11-03 10:00:00']));

    $hoja = libroDe(exportacionDe($empresa))->getSheetByName('Auditoría');

    expect($hoja->getHighestColumn())->toBe('AZ');

    $condicionales = $hoja->getConditionalStyles('A2');
    expect($condicionales)->toHaveCount(2)
        ->and($condicionales[0]->getConditions()[0])->toContain('$AZ2')->toContain('Con errores')
        ->and($condicionales[0]->getStyle()->getFill()->getEndColor()->getRGB())->toBe('FDE2E2')
        ->and($condicionales[1]->getConditions()[0])->toContain('Con alertas')
        ->and($condicionales[1]->getStyle()->getFill()->getEndColor()->getRGB())->toBe('FFF1CC')
        ->and($hoja->getConditionalStyles('AY4'))->not->toBeEmpty();

    $relleno = fn (string $celda) => $hoja->getStyle($celda)->getFill()->getFillType() === 'solid'
        ? $hoja->getStyle($celda)->getFill()->getStartColor()->getRGB()
        : null;

    expect([$relleno('AX2'), $relleno('AY2'), $relleno('AZ2')])->toBe(['FDE2E2', 'FDE2E2', 'FDE2E2'])
        ->and([$relleno('AX3'), $relleno('AZ3')])->toBe(['FFF1CC', 'FFF1CC'])
        ->and([$relleno('AX4'), $relleno('AZ4')])->toBe([null, null])
        ->and($relleno('A1'))->toBe('1F3864')
        ->and($relleno('B1'))->toBe('2E7D32')
        ->and($relleno('AZ1'))->toBe('7F1D1D');
});

test('la hoja de visitas replica conforme, no conforme y no verificable fila a fila', function () {
    $empresa = Company::factory()->create();
    Carbon::setTestNow('2026-11-10 10:00:00');
    $conforme = visitaDeAuditoria(registroExportable($empresa, ['orden_trabajo' => '300000001', 'contrato' => '111111', 'serie_medidor' => '555555', 'lectura_medidor' => '100', 'categoria' => 'comercial']), [], 'Ana');

    Carbon::setTestNow('2026-11-10 11:00:00');
    $noConforme = visitaDeAuditoria(registroExportable($empresa, ['orden_trabajo' => '300000002', 'contrato' => '222222', 'serie_medidor' => '666666', 'lectura_medidor' => '200']), ['serie_medidor' => '123456', 'lectura_medidor' => null], 'Beto');

    Carbon::setTestNow('2026-11-10 12:00:00');
    $noVerificable = visitaDeAuditoria(registroExportable($empresa, ['orden_trabajo' => '300000003', 'contrato' => '333333']), ['suspension_ejecutada' => false, 'metodo_suspension' => null, 'motivo_no_ejecucion' => 'predio_cerrado'], 'Carla');
    Carbon::setTestNow();

    expect([$conforme->resultado_comparacion, $noConforme->resultado_comparacion, $noVerificable->resultado_comparacion])
        ->toBe(['conforme', 'no_conforme', 'no_verificable']);

    $hoja = libroDe(exportacionDe($empresa))->getSheetByName('Visitas de auditoría');
    [$a, $b, $c] = filasDeHoja($hoja);

    expect($hoja->getCell('E2')->getFormattedValue())->toBe('2026-11-10 10:00:00')
        ->and($hoja->getCell('E4')->getFormattedValue())->toBe('2026-11-10 12:00:00');

    expect($a['OT original'])->toBe(300000001)
        ->and($a['Contrato'])->toBe(111111)
        ->and($a['Auditor'])->toBe('Ana Pérez')
        ->and($a['Resultado final'])->toBe('Conforme')
        ->and($a['¿Suspensión ejecutada? (reportado)'])->toBe('Sí')
        ->and($a['¿Suspensión ejecutada? (encontrado)'])->toBe('Sí')
        ->and($a['Validar ▸ ¿Suspensión ejecutada?'])->toBe('Coincide')
        ->and($a['Método de suspensión (reportado)'])->toBe('Taponamiento de acometida')
        ->and($a['Validar ▸ Método de suspensión'])->toBe('Coincide')
        ->and($a['Serie del medidor (reportado)'])->toEqual('555555')
        ->and($a['Validar ▸ Serie del medidor'])->toBe('Coincide')
        ->and($a['Validar ▸ Lectura del medidor'])->toBe('Coincide')
        ->and($a['Validar ▸ Ubicación de la visita'])->toBe('Coincide')
        ->and($a['Categoría (reportado)'])->toBe('Comercial')
        ->and($a['Validar ▸ Categoría'])->toBe('Coincide')
        ->and($a['Alertas (campos no críticos)'])->toBe(0);

    expect($b['Auditor'])->toBe('Beto Pérez')
        ->and($b['Resultado final'])->toBe('No conforme')
        ->and($b['Serie del medidor (reportado)'])->toEqual('666666')
        ->and($b['Serie del medidor (encontrado)'])->toEqual('123456')
        ->and($b['Validar ▸ Serie del medidor'])->toBe('✖ No coincide')
        ->and($b['Lectura del medidor (reportado)'])->toEqual('200')
        ->and($b['Lectura del medidor (encontrado)'])->toBeNull()
        ->and($b['Validar ▸ Lectura del medidor'])->toBe('⚠ Sin dato');

    expect($c['Auditor'])->toBe('Carla Pérez')
        ->and($c['Resultado final'])->toBe('No verificable')
        ->and($c['¿Suspensión ejecutada? (reportado)'])->toBe('Sí')
        ->and($c['¿Suspensión ejecutada? (encontrado)'])->toBe('No')
        ->and($c['Validar ▸ ¿Suspensión ejecutada?'])->toBe('✖ No coincide')
        ->and($c['Validar ▸ Método de suspensión'])->toBeNull();

    $condicionales = $hoja->getConditionalStyles('A2');
    expect($condicionales[0]->getConditions()[0])->toContain('No conforme')
        ->and($condicionales[1]->getConditions()[0])->toContain('No verificable');
});

test('el resumen ordena las reglas por frecuencia y cuenta registros afectados', function () {
    $empresa = Company::factory()->create();
    auditadoCon(registroExportable($empresa), [['fecha', 'alerta', 'fecha_registro', 'a'], ['lectura', 'error', 'lectura_medidor', 'b'], ['lectura', 'error', 'lectura_medidor', 'c']]);
    auditadoCon(registroExportable($empresa), [['lectura', 'error', 'lectura_medidor', 'b']]);
    auditadoCon(registroExportable($empresa), [['fotos_minimas', 'error', 'foto_fachada', 'x']]);

    $filas = libroDe(exportacionDe($empresa))->getSheetByName('Resumen')->toArray(null, false, false, false);
    $inicio = collect($filas)->search(fn ($f) => $f[0] === 'Regla');
    $reglas = collect(array_slice($filas, $inicio + 1, 3))->map(fn ($f) => array_slice($f, 0, 4))->all();

    expect($reglas)->toBe([['lectura', 'Error', 3, 2], ['fecha', 'Alerta', 1, 1], ['fotos_minimas', 'Error', 1, 1]]);
});

test('ninguna celda de texto de Survey123 se interpreta como fórmula, en ninguna hoja', function () {
    $empresa = Company::factory()->create();
    $malo = "=cmd|' /C calc'!A0";

    $registro = auditadoCon(registroExportable($empresa, [
        'tecnico' => '=1+1',
        'contrato' => '@contrato',
        'orden_trabajo' => '+orden',
        'categoria' => '-categoria',
        'actividad' => "\t=actividad",
        'metodo_suspension' => '=metodo',
        'serie_medidor' => '=serie',
        'lectura_medidor' => '@lectura',
        'serie_sonda' => '+sonda',
        'serie_caps' => '-caps',
        'motivo_no_ejecucion' => '=motivo',
        'justificacion' => $malo,
        'global_id' => '=global',
    ]), [['lectura', 'error', 'lectura_medidor', $malo]]);

    visitaDeAuditoria($registro, ['serie_medidor' => $malo, 'lectura_medidor' => '@x', 'categoria_campo' => '=cat'], '=evil');

    $libro = libroDe(exportacionDe($empresa));
    $protegidas = 0;

    foreach ($libro->getAllSheets() as $h) {
        foreach ($h->getCellCollection()->getCoordinates() as $coordenada) {
            $valor = $h->getCell($coordenada)->getValue();
            $protegidas += is_string($valor) && preg_match('/^[=+\-@\t\r]/', $valor) === 1 ? 1 : 0;
        }
    }

    expect(celdasDeTextoSinProteger($libro))->toBe([])
        ->and($protegidas)->toBeGreaterThanOrEqual(12)
        ->and($libro->getSheetByName('Auditoría')->getCell('G2')->getValue())->toBe('=1+1')
        ->and($libro->getSheetByName('Visitas de auditoría')->getCell('D2')->getValue())->toBe('=evil Pérez')
        ->and(collect($libro->getSheetByName('Resumen')->toArray(null, false, false, false))->pluck(0))->toContain('=1+1');
});

test('Exportar Excel respeta el filtro de estado y entrega un xlsx con nombre y tipo correctos', function () {
    $user = usuarioConRol('Coordinador');
    registroExportable($user->company, ['orden_trabajo' => '300000001']);
    registroExportable($user->company, ['orden_trabajo' => '300000002', 'estado' => EstadoSurveyRegistro::Asignado]);

    $componente = Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->filterTable('estado', 'asignado')
        ->callTableAction('exportar_excel');

    $componente->assertFileDownloaded();

    expect($componente->effects['download']['name'])->toMatch('/^auditoria-suspensiones-\d{8}-\d{6}\.xlsx$/')
        ->and($componente->effects['download']['contentType'])->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $ots = collect(filasDeHoja(libroDeDescarga($componente)->getSheetByName('Auditoría')))->pluck('Orden de Trabajo')->all();
    expect($ots)->toBe([300000002]);
});

test('un usuario de otra empresa solo exporta lo suyo, aunque seleccione registros ajenos', function () {
    $usuarioA = usuarioConRol('Coordinador');
    $usuarioB = usuarioConRol('Coordinador');

    $deA = registroExportable($usuarioA->company, ['orden_trabajo' => '300000001']);
    registroExportable($usuarioB->company, ['orden_trabajo' => '300000002']);

    $todo = Livewire::actingAs($usuarioB)->test(SurveyRegistrosIndex::class)->callTableAction('exportar_excel');
    expect(collect(filasDeHoja(libroDeDescarga($todo)->getSheetByName('Auditoría')))->pluck('Orden de Trabajo')->all())->toBe([300000002]);

    $ajeno = Livewire::actingAs($usuarioB)->test(SurveyRegistrosIndex::class)->callTableBulkAction('exportar_seleccionados', [$deA]);
    expect(filasDeHoja(libroDeDescarga($ajeno)->getSheetByName('Auditoría')))->toBeEmpty();
});

test('el comando acepta cada opción por separado', function () {
    $empresa = Company::factory()->create();
    registroExportable($empresa, ['orden_trabajo' => '300000001', 'fecha_registro' => '2026-11-01 10:00:00']);
    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000002', 'fecha_registro' => '2026-11-05 10:00:00', 'estado' => EstadoSurveyRegistro::Asignado]), [['fecha', 'alerta', 'fecha_registro', 'a']]);
    auditadoCon(registroExportable($empresa, ['orden_trabajo' => '300000003', 'fecha_registro' => '2026-11-09 10:00:00']), [['lectura', 'error', 'lectura_medidor', 'b']]);

    $raiz = sys_get_temp_dir().'/exp-'.Str::random(8);
    $directorio = "{$raiz}/no/existe/aun";

    $casos = [
        [['--desde' => '2026-11-05'], 2],
        [['--hasta' => '2026-11-05'], 2],
        [['--estado' => 'pendiente'], 2],
        [['--estado' => 'asignado'], 1],
        [['--con-errores' => true], 1],
        [['--con-alertas' => true], 1],
        [['--company' => $empresa->id], 3],
    ];

    foreach ($casos as $n => [$opciones, $esperados]) {
        $this->artisan('survey123:exportar', $opciones + ['--salida' => "{$directorio}/caso{$n}.xlsx"])
            ->expectsOutputToContain("{$esperados} registros exportados")
            ->assertSuccessful();

        expect(filasDeHoja(IOFactory::load("{$directorio}/caso{$n}.xlsx")->getSheetByName('Auditoría')))->toHaveCount($esperados);
    }

    File::deleteDirectory($raiz);
});

test('el comando falla con fechas inválidas sin crear el archivo', function () {
    Company::factory()->create();
    $ruta = sys_get_temp_dir().'/exp-'.Str::random(8).'.xlsx';

    $this->artisan('survey123:exportar', ['--desde' => 'no-es-fecha', '--salida' => $ruta])->assertFailed();
    $this->artisan('survey123:exportar', ['--hasta' => '31/31/2026', '--salida' => $ruta])->assertFailed();

    expect(file_exists($ruta))->toBeFalse();
});

test('la cantidad de consultas no crece con el número de registros', function () {
    config(['excel.exports.chunk_size' => 50]);

    $consultas = function (int $cantidad): int {
        $empresa = Company::factory()->create();

        foreach (range(1, $cantidad) as $i) {
            $registro = auditadoCon(registroExportable($empresa, ['orden_trabajo' => (string) (310000000 + $i)]), [['lectura', 'error', 'lectura_medidor', 'b']]);
            SurveyAdjunto::create(['survey_registro_id' => $registro->id, 'attachment_id' => $i, 'campo' => 'foto_medidor', 'nombre' => "{$i}.jpg"]);

            if ($i % 20 === 0) {
                visitaDeAuditoria($registro);
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        Excel::raw(exportacionDe($empresa), TipoExcel::XLSX);
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };

    $pequeno = $consultas(40);
    $grande = $consultas(200);

    expect($grande)->toBeLessThanOrEqual($pequeno + 6);
});

test('las fechas salen con la hora de la zona de la aplicación, sin desfase', function () {
    $zonaOriginal = date_default_timezone_get();

    try {
        foreach (['UTC', 'America/Bogota'] as $zona) {
            date_default_timezone_set($zona);
            config(['app.timezone' => $zona]);
            Carbon::setTestNow(Carbon::parse('2026-11-10 10:00:00', $zona));

            $empresa = Company::factory()->create();
            $registro = registroExportable($empresa, ['fecha_registro' => '2026-11-10 07:45:00', 'creado_en_origen' => '2026-11-10 08:00:00', 'editado_en_origen' => '2026-11-10 09:30:00']);
            visitaDeAuditoria($registro);

            $libro = libroDe(exportacionDe($empresa));

            expect($libro->getSheetByName('Auditoría')->getCell('E2')->getFormattedValue())->toBe('2026-11-10 07:45:00', $zona)
                ->and($libro->getSheetByName('Auditoría')->getCell('AO2')->getFormattedValue())->toBe('2026-11-10 08:00:00')
                ->and($libro->getSheetByName('Auditoría')->getCell('AQ2')->getFormattedValue())->toBe('2026-11-10 09:30:00')
                ->and($libro->getSheetByName('Visitas de auditoría')->getCell('E2')->getFormattedValue())->toBe('2026-11-10 10:00:00');
        }
    } finally {
        date_default_timezone_set($zonaOriginal);
        Carbon::setTestNow();
    }
});

test('el Excel cuenta la foto del IVT y suma las fotos de actividad de ambas versiones del formulario', function () {
    $empresa = Company::factory()->create();
    $nuevo = registroExportable($empresa, ['orden_trabajo' => '300000001']);
    $anterior = registroExportable($empresa, ['orden_trabajo' => '300000002']);

    $agregar = function (SurveyRegistro $registro, string $campo, int $n) {
        SurveyAdjunto::create([
            'survey_registro_id' => $registro->id, 'attachment_id' => $registro->object_id * 100 + $n,
            'campo' => $campo, 'nombre' => "{$campo}-{$n}.jpg", 'hash' => sha1($registro->id.$campo.$n),
        ]);
    };

    foreach (['foto_ivt', 'foto_actividad1', 'foto_actividad2', 'foto_actividad3'] as $n => $campo) {
        $agregar($nuevo, $campo, $n + 1);
    }
    $agregar($anterior, 'foto_actividad', 1);
    $agregar($anterior, 'foto_actividad', 2);

    $actividad = 'Fotografías de la suspensión, reconexión ejecutada o novedad encontrada';
    $filas = collect(filasDeHoja(libroDe(exportacionDe($empresa))->getSheetByName('Auditoría')))->keyBy('Orden de Trabajo');

    expect($filas[300000001]['Fotografía del IVT'])->toBe(1)
        ->and($filas[300000001][$actividad])->toBe(3)
        ->and($filas[300000002]['Fotografía del IVT'])->toBe(0)
        ->and($filas[300000002][$actividad])->toBe(2);
});
