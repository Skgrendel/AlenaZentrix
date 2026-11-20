<?php

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

test('se niega si la base de datos no está en UTC, salvo que se fuerce', function () {
    $registro = SurveyRegistro::factory()->create(['fecha_registro' => '2026-11-20 21:23:00']);

    conZonaDeLaBase('+03:00', function () {
        $this->artisan('fechas:convertir-a-colombia', ['--ejecutar' => true, '--respaldo-hecho' => true])
            ->expectsOutputToContain('no es UTC')
            ->assertFailed();

        $this->artisan('fechas:convertir-a-colombia', ['--forzar' => true])->assertSuccessful();
    });

    expect(fechaGuardada('survey_registros', 'fecha_registro', $registro->id))->toBe('2026-11-20 21:23:00');
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
