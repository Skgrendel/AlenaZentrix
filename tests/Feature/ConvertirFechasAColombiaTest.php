<?php

use App\Console\Commands\ConvertirFechasAColombia;
use App\Models\OrdenJob;
use App\Models\SurveyRegistro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Ejecuta la prueba con la sesión de la base en la zona indicada y la deja como estaba. */
function conZonaDeLaBase(string $zona, Closure $prueba): void
{
    $antes = DB::selectOne('select @@session.time_zone as z')->z;
    DB::statement("SET time_zone = '{$zona}'");

    try {
        $prueba();
    } finally {
        DB::statement("SET time_zone = '{$antes}'");
    }
}

function fechaGuardada(string $tabla, string $columna, ?string $id = null): ?string
{
    return DB::table($tabla)->when($id, fn ($q) => $q->where('id', $id))->value($columna);
}

test('la aplicación opera en hora de Colombia', function () {
    expect(config('app.timezone'))->toBe('America/Bogota')
        ->and(now()->timezoneName)->toBe('America/Bogota')
        ->and(now()->utcOffset())->toBe(-300);
});

test('por defecto solo simula: muestra qué cambiaría y no modifica nada', function () {
    $registro = SurveyRegistro::factory()->create(['fecha_registro' => '2026-11-20 21:23:00']);

    conZonaDeLaBase('+00:00', function () {
        $this->artisan('fechas:convertir-a-colombia')
            ->expectsOutputToContain('Simulación: no se modificó nada')
            ->expectsOutputToContain('survey_registros')
            ->assertSuccessful();
    });

    expect(fechaGuardada('survey_registros', 'fecha_registro', $registro->id))->toBe('2026-11-20 21:23:00')
        ->and(DB::table('conversiones_de_hora')->count())->toBe(0);
});

test('sin confirmar el respaldo no aplica nada', function () {
    $registro = SurveyRegistro::factory()->create(['fecha_registro' => '2026-11-20 21:23:00']);

    conZonaDeLaBase('+00:00', function () {
        $this->artisan('fechas:convertir-a-colombia', ['--ejecutar' => true])
            ->expectsOutputToContain('--respaldo-hecho')
            ->assertFailed();
    });

    expect(fechaGuardada('survey_registros', 'fecha_registro', $registro->id))->toBe('2026-11-20 21:23:00');
});

test('al ejecutar resta 5 horas a todas las columnas de fecha, deja constancia y no se puede repetir', function () {
    $registro = SurveyRegistro::factory()->create(['fecha_registro' => '2026-11-20 21:23:00', 'creado_en_origen' => '2026-11-20 21:30:02']);
    $usuario = User::factory()->create();
    DB::table('users')->where('id', $usuario->id)->update(['created_at' => '2026-11-20 20:00:00']);
    $nulo = SurveyRegistro::factory()->create(['fecha_registro' => null]);

    conZonaDeLaBase('+00:00', function () {
        $this->artisan('fechas:convertir-a-colombia', ['--ejecutar' => true, '--respaldo-hecho' => true])
            ->expectsOutputToContain('Conversión aplicada')
            ->assertSuccessful();

        $this->artisan('fechas:convertir-a-colombia', ['--ejecutar' => true, '--respaldo-hecho' => true])
            ->expectsOutputToContain('ya se aplicó')
            ->assertFailed();
    });

    expect(fechaGuardada('survey_registros', 'fecha_registro', $registro->id))->toBe('2026-11-20 16:23:00')
        ->and(fechaGuardada('survey_registros', 'creado_en_origen', $registro->id))->toBe('2026-11-20 16:30:02')
        ->and(fechaGuardada('users', 'created_at', $usuario->id))->toBe('2026-11-20 15:00:00')
        ->and(fechaGuardada('survey_registros', 'fecha_registro', $nulo->id))->toBeNull()
        ->and(DB::table('conversiones_de_hora')->where('nombre', 'utc-a-colombia')->count())->toBe(1);

    // La pantalla ya muestra la hora que se ve en el formulario de los técnicos.
    expect(SurveyRegistro::query()->withoutGlobalScopes()->find($registro->id)->fecha_registro->format('d/m/Y h:i a'))->toBe('20/11/2026 04:23 pm');
});

test('se puede revertir y todo vuelve a como estaba', function () {
    $registro = SurveyRegistro::factory()->create(['fecha_registro' => '2026-11-20 21:23:00']);

    conZonaDeLaBase('+00:00', function () {
        $this->artisan('fechas:convertir-a-colombia', ['--ejecutar' => true, '--respaldo-hecho' => true])->assertSuccessful();
        $this->artisan('fechas:convertir-a-colombia', ['--ejecutar' => true, '--respaldo-hecho' => true, '--revertir' => true])
            ->expectsOutputToContain('Conversión revertida')
            ->assertSuccessful();

        $this->artisan('fechas:convertir-a-colombia', ['--revertir' => true, '--ejecutar' => true, '--respaldo-hecho' => true])
            ->expectsOutputToContain('No hay ninguna conversión aplicada')
            ->assertFailed();
    });

    expect(fechaGuardada('survey_registros', 'fecha_registro', $registro->id))->toBe('2026-11-20 21:23:00')
        ->and(DB::table('conversiones_de_hora')->count())->toBe(0);
});

test('no toca su propia constancia ni el historial de migraciones', function () {
    conZonaDeLaBase('+00:00', function () {
        $this->artisan('fechas:convertir-a-colombia')
            ->doesntExpectOutputToContain('conversiones_de_hora')
            ->assertSuccessful();
    });
});

test('acepta una base con desfase fijo, como la de Colombia (-05:00), y la convierte igual', function () {
    $registro = SurveyRegistro::factory()->create(['fecha_registro' => '2026-11-20 21:23:00']);

    conZonaDeLaBase('-05:00', function () {
        $this->artisan('fechas:convertir-a-colombia', ['--ejecutar' => true, '--respaldo-hecho' => true])
            ->expectsOutputToContain('desfase fijo')
            ->assertSuccessful();
    });

    expect(fechaGuardada('survey_registros', 'fecha_registro', $registro->id))->toBe('2026-11-20 16:23:00');
});

test('reconoce qué zonas de la base tienen desfase fijo y cuáles tienen horario de verano', function (string $zona, bool $fijo) {
    expect(ConvertirFechasAColombia::zonaTieneDesfaseFijo($zona))->toBe($fijo);
})->with([
    'UTC' => ['UTC', true],
    'más cero' => ['+00:00', true],
    'Colombia abreviada' => ['-05', true],
    'Colombia con minutos' => ['-05:00', true],
    'COT' => ['COT', true],
    'Bogotá por nombre' => ['America/Bogota', true],
    'Madrid' => ['Europe/Madrid', false],
    'Nueva York' => ['America/New_York', false],
    'CET' => ['CET', false],
    'EST' => ['EST', false],
    'vacío' => ['', false],
]);

test('no convierte los trabajos fallidos: su fecha la pone la propia base y ya está en su hora', function () {
    conZonaDeLaBase('+00:00', function () {
        $this->artisan('fechas:convertir-a-colombia')
            ->doesntExpectOutputToContain('failed_jobs')
            ->assertSuccessful();
    });
});

test('no convierte lo que escribe una persona: fecha de entrega y fecha límite de las órdenes normales', function () {
    $survey = SurveyRegistro::factory()->create();
    $normal = OrdenJob::factory()->create();
    $deSuspension = OrdenJob::factory()->create(['survey_registro_id' => $survey->id]);

    foreach ([$normal, $deSuspension] as $orden) {
        DB::table('orden_jobs')->where('id', $orden->id)->update([
            'fecha_asignacion' => '2026-11-20 21:00:07',
            'fecha_limite' => '2026-11-21 17:00:00',
            'fecha_entrega' => '2026-11-20 10:30:00',
            'created_at' => '2026-11-20 21:00:07',
        ]);
    }

    conZonaDeLaBase('+00:00', function () {
        $this->artisan('fechas:convertir-a-colombia')
            ->expectsOutputToContain('orden_jobs.fecha_entrega')
            ->expectsOutputToContain('solo auditorías de suspensiones')
            ->assertSuccessful();

        $this->artisan('fechas:convertir-a-colombia', ['--ejecutar' => true, '--respaldo-hecho' => true])->assertSuccessful();
    });

    // Lo que pone el sistema pasa de UTC a hora de Colombia.
    expect(fechaGuardada('orden_jobs', 'fecha_asignacion', $normal->id))->toBe('2026-11-20 16:00:07')
        ->and(fechaGuardada('orden_jobs', 'created_at', $normal->id))->toBe('2026-11-20 16:00:07')
        // Lo que escribió una persona ya está en hora de Colombia: no se toca.
        ->and(fechaGuardada('orden_jobs', 'fecha_entrega', $normal->id))->toBe('2026-11-20 10:30:00')
        ->and(fechaGuardada('orden_jobs', 'fecha_limite', $normal->id))->toBe('2026-11-21 17:00:00')
        ->and(fechaGuardada('orden_jobs', 'fecha_entrega', $deSuspension->id))->toBe('2026-11-20 10:30:00')
        // En las auditorías de suspensiones la fecha límite la calcula el sistema (UTC): sí se convierte.
        ->and(fechaGuardada('orden_jobs', 'fecha_limite', $deSuspension->id))->toBe('2026-11-21 12:00:00');

    conZonaDeLaBase('+00:00', function () {
        $this->artisan('fechas:convertir-a-colombia', ['--revertir' => true, '--ejecutar' => true, '--respaldo-hecho' => true])->assertSuccessful();
    });

    expect(fechaGuardada('orden_jobs', 'fecha_asignacion', $normal->id))->toBe('2026-11-20 21:00:07')
        ->and(fechaGuardada('orden_jobs', 'fecha_limite', $normal->id))->toBe('2026-11-21 17:00:00')
        ->and(fechaGuardada('orden_jobs', 'fecha_limite', $deSuspension->id))->toBe('2026-11-21 17:00:00')
        ->and(fechaGuardada('orden_jobs', 'fecha_entrega', $normal->id))->toBe('2026-11-20 10:30:00');
});

test('se niega a convertir si la aplicación no está en hora de Colombia', function () {
    config(['app.timezone' => 'UTC']);

    conZonaDeLaBase('+00:00', function () {
        $this->artisan('fechas:convertir-a-colombia', ['--ejecutar' => true, '--respaldo-hecho' => true])
            ->expectsOutputToContain('America/Bogota')
            ->assertFailed();
    });

    expect(DB::table('conversiones_de_hora')->count())->toBe(0);
});
