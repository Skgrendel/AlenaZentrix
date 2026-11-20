<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Convierte UNA sola vez las fechas que ya estaban guardadas en UTC a hora de Colombia (resta 5 horas),
 * después de pasar la zona horaria de la aplicación a America/Bogota.
 *
 * Seguridad: por defecto solo simula; para aplicar exige --ejecutar y --respaldo-hecho; todo va en una
 * transacción; deja constancia y se niega a repetirse; y se puede deshacer con --revertir.
 */
class ConvertirFechasAColombia extends Command
{
    private const NOMBRE = 'utc-a-colombia';

    /** Colombia es UTC-5 todo el año (no tiene horario de verano). */
    private const HORAS = 5;

    /**
     * Tablas que no se tocan: la propia constancia, el historial de migraciones y los trabajos fallidos, cuya fecha
     * la pone la propia base de datos (DEFAULT CURRENT_TIMESTAMP) y ya está en la hora del servidor.
     */
    private const EXCLUIDAS = ['conversiones_de_hora', 'migrations', 'failed_jobs'];

    protected $signature = 'fechas:convertir-a-colombia
        {--ejecutar : Aplica el cambio. Sin esto solo simula y no modifica nada}
        {--respaldo-hecho : Confirmo que hice un respaldo de la base de datos antes de ejecutar}
        {--revertir : Deshace la conversión (suma las 5 horas de nuevo)}
        {--forzar : Permite ejecutar aunque la zona horaria de la base de datos tenga horario de verano}';

    protected $description = 'Convierte una sola vez las fechas guardadas en UTC a hora de Colombia (resta 5 horas). Por defecto solo simula.';

    public function handle(): int
    {
        $revertir = (bool) $this->option('revertir');
        $constancia = DB::table('conversiones_de_hora')->where('nombre', self::NOMBRE)->first();

        if (! $revertir && $constancia !== null) {
            $this->error("Esta conversión ya se aplicó el {$constancia->ejecutada_en}. No se puede repetir. Si fue un error, usa --revertir.");

            return self::FAILURE;
        }

        if ($revertir && $constancia === null) {
            $this->error('No hay ninguna conversión aplicada que revertir.');

            return self::FAILURE;
        }

        if (config('app.timezone') !== 'America/Bogota' && ! $revertir) {
            $this->error('La zona horaria de la aplicación no es America/Bogota (APP_TIMEZONE). Cámbiala antes de convertir los datos.');

            return self::FAILURE;
        }

        $zonaDeLaBase = $this->zonaDeLaBase();

        if (! self::zonaTieneDesfaseFijo($zonaDeLaBase) && ! $this->option('forzar')) {
            $this->error("La zona horaria de la base de datos ({$zonaDeLaBase}) puede tener horario de verano, y las columnas timestamp podrían quedar mal. Revísalo con quien administra la base, o usa --forzar si estás seguro.");

            return self::FAILURE;
        }

        $this->line("Zona horaria de la base de datos: {$zonaDeLaBase} (desfase fijo, la resta es exacta).");

        $columnas = $this->columnasDeFecha();
        $total = 0;
        $filas = [];

        foreach ($columnas as $tabla => $lista) {
            $cantidad = (int) DB::table($tabla)->count();
            $total += $cantidad;
            $filas[] = [$tabla, implode(', ', $lista), $cantidad];
        }

        $accion = $revertir ? 'sumar' : 'restar';
        $this->info(($revertir ? 'REVERTIR' : 'CONVERTIR')." — se van a {$accion} ".self::HORAS.' horas a '.array_sum(array_map('count', $columnas)).' columnas de fecha en '.count($columnas).' tablas.');
        $this->table(['Tabla', 'Columnas de fecha', 'Filas'], $filas);

        $this->mostrarEjemplo($columnas, $revertir);

        if (! $this->option('ejecutar')) {
            $this->warn('Simulación: no se modificó nada. Para aplicar: --ejecutar --respaldo-hecho (después de respaldar la base y poner el sitio en mantenimiento).');

            return self::SUCCESS;
        }

        if (! $this->option('respaldo-hecho')) {
            $this->error('Falta confirmar el respaldo: agrega --respaldo-hecho cuando ya hayas respaldado la base de datos.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($columnas, $revertir, $total): void {
                $funcion = $revertir ? 'DATE_ADD' : 'DATE_SUB';

                foreach ($columnas as $tabla => $lista) {
                    // Una sola sentencia por tabla: todas sus columnas cambian juntas y ninguna se pisa.
                    $cambios = implode(', ', array_map(fn (string $c): string => "`{$c}` = {$funcion}(`{$c}`, INTERVAL ".self::HORAS.' HOUR)', $lista));
                    DB::statement("UPDATE `{$tabla}` SET {$cambios}");
                }

                if ($revertir) {
                    DB::table('conversiones_de_hora')->where('nombre', self::NOMBRE)->delete();

                    return;
                }

                DB::table('conversiones_de_hora')->insert([
                    'nombre' => self::NOMBRE,
                    'horas' => self::HORAS,
                    'columnas' => array_sum(array_map('count', $columnas)),
                    'filas' => $total,
                    'ejecutada_en' => now(),
                ]);
            });
        } catch (Throwable $e) {
            $this->error('No se aplicó ningún cambio (se deshizo todo): '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info($revertir ? 'Conversión revertida.' : 'Conversión aplicada. Las fechas guardadas ahora están en hora de Colombia.');

        return self::SUCCESS;
    }

    /** La zona con la que la base lee y escribe las fechas: la de la sesión o, si es «SYSTEM», la del servidor. */
    private function zonaDeLaBase(): string
    {
        $zona = DB::selectOne('select @@session.time_zone as sesion, @@system_time_zone as sistema');

        return (string) ($zona->sesion === 'SYSTEM' ? $zona->sistema : $zona->sesion);
    }

    /**
     * Restar horas a una columna es exacto si la zona de la base tiene un desfase fijo (UTC, -05, -05:00, Bogotá),
     * porque lo que se resta y lo que se guarda usan la misma zona. Con horario de verano (Madrid, Nueva York, CET)
     * algunas horas no existen o se repiten y el resultado podría quedar mal.
     */
    public static function zonaTieneDesfaseFijo(string $zona): bool
    {
        $zona = strtoupper(trim($zona));

        return in_array($zona, ['UTC', 'GMT', 'Z', 'COT', 'AMERICA/BOGOTA'], true)
            || preg_match('/^[+-]\d{1,2}(:?\d{2})?$/', $zona) === 1;
    }

    /** @return array<string, array<int, string>> tabla => columnas datetime o timestamp */
    private function columnasDeFecha(): array
    {
        $filas = DB::select(
            "select c.table_name as tabla, c.column_name as columna
             from information_schema.columns c
             join information_schema.tables t on t.table_schema = c.table_schema and t.table_name = c.table_name
             where c.table_schema = ? and t.table_type = 'BASE TABLE' and c.data_type in ('datetime', 'timestamp')
             order by c.table_name, c.ordinal_position",
            [DB::getDatabaseName()],
        );

        $columnas = [];

        foreach ($filas as $fila) {
            if (! in_array($fila->tabla, self::EXCLUIDAS, true)) {
                $columnas[$fila->tabla][] = $fila->columna;
            }
        }

        return $columnas;
    }

    /** @param  array<string, array<int, string>>  $columnas */
    private function mostrarEjemplo(array $columnas, bool $revertir): void
    {
        foreach ($columnas as $tabla => $lista) {
            $columna = $lista[0];
            $valor = DB::table($tabla)->whereNotNull($columna)->orderBy($columna, 'desc')->value($columna);

            if ($valor === null) {
                continue;
            }

            $despues = DB::selectOne('select '.($revertir ? 'DATE_ADD' : 'DATE_SUB').'(?, INTERVAL '.self::HORAS.' HOUR) as v', [$valor])->v;
            $this->line("Ejemplo ({$tabla}.{$columna}): {$valor}  →  {$despues}");

            return;
        }
    }
}
