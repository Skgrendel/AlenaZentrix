# PDF Consolidado de Vigilancia — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generar un PDF descargable que consolida todos los turnos (reportes) de una Orden de Vigilancia en un solo documento.

**Architecture:** Se agrega un método `DownLoadInformeVigilancia` al `ReportePdfController` existente, una vista blade nueva `pdf.reporte-vigilancia`, una ruta GET en `operaciones.php`, y dos botones de descarga en las vistas Livewire de vigilancia.

**Tech Stack:** Laravel 13, Barryvdh DomPDF (`Barryvdh\DomPDF\Facade\Pdf`), Livewire 4, FluxUI v2, Pest v4.

## Global Constraints

- PHP 8.4 — usar constructor property promotion, tipos explícitos en todos los métodos
- El permiso de descarga es `informes` (verificar con `auth()->user()->can('informes')`)
- La ruta debe estar dentro del middleware `permission:ordenes.ver` en `routes/operaciones.php`
- `Storage::fake('s3')` obligatorio en tests que generen PDF (las imágenes se cargan desde S3)
- Correr `vendor/bin/pint --dirty --format agent` tras modificar cualquier archivo PHP
- Correr `php artisan test --compact --filter=` al finalizar cada tarea

---

### Task 1: Ruta + método del controlador + tests

**Files:**
- Modify: `routes/operaciones.php`
- Modify: `app/Http/Controllers/ReportePdfController.php`
- Modify: `tests/Feature/ReportePdfTest.php`

**Interfaces:**
- Consumes: `OrdenJob`, `reportes`, `ResultadoReporte`, métodos privados existentes `buildDatosOrden`, `resolveImagenes`, `fileToBase64`
- Produces: ruta `operaciones.ordenes-trabajo.reporte-vigilancia`, método `DownLoadInformeVigilancia(OrdenJob $orden): Response`, descarga PDF `reporte-vigilancia-{numero_orden}.pdf`

- [ ] **Step 1: Escribir los tests que deben fallar**

Agregar al final de `tests/Feature/ReportePdfTest.php`:

```php
test('guests are redirected to login on vigilancia pdf download', function () {
    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Vigilancia->value]);

    $this->get(route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden))
        ->assertRedirect(route('login'));
});

test('pdf vigilancia returns 403 for non-vigilancia service types', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Investigacion->value]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden))
        ->assertForbidden();
});

test('user without informes permission cannot download vigilancia pdf', function () {
    $user = User::factory()->create();
    $user->assignRole('Lector');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Vigilancia->value]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden))
        ->assertForbidden();
});

test('coordinador can download vigilancia pdf with no reportes', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Vigilancia->value,
        'company_id' => $user->company_id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('coordinador can download vigilancia pdf with multiple reportes', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Vigilancia->value,
        'company_id' => $user->company_id,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'observaciones' => 'Sin novedad en el turno.',
        'datos_servicio' => [
            'fecha_turno' => '2026-07-01',
            'hora_inicio' => '08:00',
            'hora_fin' => '16:00',
            'hay_novedad' => false,
            'hay_reincidencia' => false,
        ],
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'observaciones' => 'Se detectó novedad.',
        'datos_servicio' => [
            'fecha_turno' => '2026-07-02',
            'hora_inicio' => '16:00',
            'hora_fin' => '00:00',
            'hay_novedad' => true,
            'descripcion_novedad' => 'Persona sospechosa en el perímetro.',
            'hay_reincidencia' => true,
        ],
    ]);

    $response = $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});
```

- [ ] **Step 2: Correr los tests para confirmar que fallan (ruta inexistente)**

```
php artisan test --compact --filter="vigilancia pdf"
```

Resultado esperado: todos fallan con error de ruta no encontrada o 404.

- [ ] **Step 3: Agregar la ruta en `routes/operaciones.php`**

Agregar esta línea dentro del grupo `middleware(['auth', 'verified', 'permission:ordenes.ver'])`, junto a las demás rutas de `reporte-*`:

```php
Route::get('ordenes-trabajo/{orden}/reporte-vigilancia', [ReportePdfController::class, 'DownLoadInformeVigilancia'])->name('ordenes-trabajo.reporte-vigilancia');
```

El bloque completo de rutas quedará así (sección relevante):

```php
Route::get('ordenes-trabajo/{orden}/reporte-fraude', [ReportePdfController::class, 'DownLoadInformeFraude'])->name('ordenes-trabajo.reporte-fraude');
Route::get('ordenes-trabajo/{orden}/reporte-investigacion', [ReportePdfController::class, 'DownLoadInformeInvestigacion'])->name('ordenes-trabajo.reporte-investigacion');
Route::get('ordenes-trabajo/{orden}/reporte-gestion-legal', [ReportePdfController::class, 'DownLoadInformeGestionLegal'])->name('ordenes-trabajo.reporte-gestion-legal');
Route::get('ordenes-trabajo/{orden}/reporte-auditoria-campo', [ReportePdfController::class, 'DownLoadInformeAuditoriaCampo'])->name('ordenes-trabajo.reporte-auditoria-campo');
Route::get('ordenes-trabajo/{orden}/reporte-gestion-riesgo', [ReportePdfController::class, 'DownLoadInformeGestionRiesgo'])->name('ordenes-trabajo.reporte-gestion-riesgo');
Route::get('ordenes-trabajo/{orden}/reporte-vigilancia', [ReportePdfController::class, 'DownLoadInformeVigilancia'])->name('ordenes-trabajo.reporte-vigilancia');
```

- [ ] **Step 4: Agregar el método `DownLoadInformeVigilancia` en `ReportePdfController`**

Agregar inmediatamente después del método `DownLoadInformeGestionRiesgo` (línea ~527), antes del método `descargarZipRevisionMedidores`:

```php
public function DownLoadInformeVigilancia(OrdenJob $orden): Response
{
    abort_unless($orden->tipo_servicio === TipoServicio::Vigilancia, 403);
    abort_unless(auth()->user()->can('informes'), 403);

    ini_set('memory_limit', '256M');

    $orden->load(['personal', 'creadoPor', 'proceso']);

    $reportes = $orden->reportes()
        ->with(['personal.usuario'])
        ->orderBy('created_at', 'asc')
        ->get();

    $resultadoIds = $reportes
        ->map(fn (reportes $r) => $r->datos_servicio['resultado_reporte_id'] ?? null)
        ->filter()
        ->unique()
        ->values();

    $resultados = ResultadoReporte::whereIn('id', $resultadoIds)->pluck('nombre', 'id')->all();

    $logoProderi = $this->fileToBase64(public_path('assets/image/LOGO PRODERI CON SLOGAN.png'), 'image/png');

    $imagenesPerTurno = [];
    foreach ($reportes as $reporte) {
        $imagenesPerTurno[$reporte->id] = $this->resolveImagenes($reporte->imagenes ?? []);
    }

    /** @var User $generadorUser */
    $generadorUser = auth()->user();
    $generador = [
        'nombre' => $generadorUser->fullName(),
        'email' => $generadorUser->email,
        'fecha' => now()->format('d/m/Y H:i:s'),
    ];

    $datosOrden = $this->buildDatosOrden($orden);

    $pdf = Pdf::loadView('pdf.reporte-vigilancia', [
        'orden' => $orden,
        'reportes' => $reportes,
        'resultados' => $resultados,
        'logoProderi' => $logoProderi,
        'imagenesPerTurno' => $imagenesPerTurno,
        'fechaGeneracion' => now()->format('d-m-Y'),
        'generador' => $generador,
        'datosOrden' => $datosOrden,
    ]);

    $pdf->setPaper('A4', 'portrait');

    return $pdf->download("reporte-vigilancia-{$orden->numero_orden}.pdf");
}
```

- [ ] **Step 5: Formatear PHP con Pint**

```
vendor/bin/pint app/Http/Controllers/ReportePdfController.php routes/operaciones.php --format agent
```

- [ ] **Step 6: Correr los tests — esperan fallar en "vista no encontrada"**

```
php artisan test --compact --filter="vigilancia pdf"
```

Resultado esperado: los tests que generan PDF fallan con `InvalidArgumentException: View [pdf.reporte-vigilancia] not found`. Los de 403/redirect deben pasar ahora.

- [ ] **Step 7: Commit**

```
git add routes/operaciones.php app/Http/Controllers/ReportePdfController.php tests/Feature/ReportePdfTest.php
git commit -m "feat: add DownLoadInformeVigilancia route and controller method"
```

---

### Task 2: Vista blade del PDF

**Files:**
- Create: `resources/views/pdf/reporte-vigilancia.blade.php`

**Interfaces:**
- Consumes: variables `$orden` (OrdenJob), `$reportes` (Collection de reportes), `$resultados` (array id→nombre), `$logoProderi` (string base64), `$imagenesPerTurno` (array reporte_id→array de base64), `$fechaGeneracion` (string), `$generador` (array con claves `nombre`, `email`, `fecha`), `$datosOrden` (array del buildDatosOrden)
- Produces: vista `pdf.reporte-vigilancia` que rende el PDF completo

- [ ] **Step 1: Crear `resources/views/pdf/reporte-vigilancia.blade.php`**

```blade
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Informe de Vigilancia - {{ $orden->numero_orden }}</title>
    <style>
        @page {
            size: A4;
            margin: 155px 50px 110px 50px;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.2;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .header {
            position: fixed;
            top: -143px;
            left: 0;
            right: 0;
            height: 133px;
            background: white;
            padding: 10px 50px 6px 50px;
            z-index: 1000;
        }

        .header-inner {
            width: 100%;
            border-collapse: collapse;
        }

        .header-td-center {
            text-align: left;
            vertical-align: middle;
        }

        .header-td-proderi {
            width: 140px;
            text-align: right;
            vertical-align: middle;
        }

        .logo-proderi {
            width: 130px;
            height: auto;
        }

        .header h1 {
            color: #003E72;
            font-size: 13pt;
            margin: 0 0 3px 0;
            font-weight: bold;
        }

        .header h2 {
            color: #64748b;
            font-size: 9pt;
            margin: 0 0 4px 0;
            font-weight: normal;
        }

        .header-meta {
            font-size: 7.5pt;
            color: #64748b;
            margin-top: 3px;
        }

        .header-separator {
            position: fixed;
            top: -10px;
            left: 50px;
            right: 50px;
            height: 0;
            border-top: 2px solid #003E72;
            z-index: 999;
        }

        .footer {
            position: fixed;
            bottom: -95px;
            left: 0;
            right: 0;
            height: 75px;
            background: white;
            border-top: 2px solid #003E72;
            padding: 10px 50px;
            font-size: 7pt;
            color: #64748b;
            text-align: center;
        }

        .footer-line {
            font-weight: bold;
            margin-bottom: 2px;
        }

        .content {
            margin-top: 10px;
        }

        .section {
            margin: 10px 0;
            page-break-inside: avoid;
        }

        .section-title {
            color: #003E72;
            font-size: 10pt;
            font-weight: bold;
            margin: 12px 0 5px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #e5e7eb;
            page-break-after: avoid;
        }

        .section-title-turno {
            color: #003E72;
            font-size: 10pt;
            font-weight: bold;
            margin: 16px 0 5px 0;
            padding: 6px 10px;
            background-color: #e8edf5;
            border-left: 4px solid #003E72;
            page-break-after: avoid;
        }

        .section-subtitle {
            color: #003E72;
            font-size: 8.5pt;
            font-weight: bold;
            margin: 8px 0 4px 0;
            padding-bottom: 2px;
            border-bottom: 1px solid #e5e7eb;
            page-break-after: avoid;
        }

        .info-box {
            border: 1px solid #003E72;
            border-radius: 6px;
            padding: 8px 10px;
            margin: 5px 0;
            background-color: #f8f9fa;
            page-break-inside: avoid;
        }

        .info-grid {
            display: table;
            width: 100%;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 3px 12px 3px 0;
            width: 160px;
            vertical-align: top;
            font-size: 8pt;
            color: #003E72;
        }

        .info-value {
            display: table-cell;
            padding: 3px 0;
            vertical-align: top;
            font-size: 8pt;
        }

        .tabla-tecnica {
            width: 100%;
            border-collapse: collapse;
        }

        .tabla-tecnica td {
            border: 1px solid #ccc;
            padding: 5px 8px;
            font-size: 8pt;
            vertical-align: middle;
        }

        .tabla-tecnica .lbl {
            background-color: #e8edf5;
            font-weight: bold;
            color: #003E72;
            width: 22%;
            white-space: nowrap;
        }

        .tabla-tecnica .val {
            width: 28%;
            color: #222;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 7pt;
            font-weight: bold;
        }

        .badge-red {
            background-color: #ef4444;
            color: white;
        }

        .badge-orange {
            background-color: #f97316;
            color: white;
        }

        .badge-green {
            background-color: #10b981;
            color: white;
        }

        .content-box {
            border: 1px solid #003E72;
            border-radius: 6px;
            background-color: #f8f9fa;
            padding: 8px 10px;
            margin: 5px 0;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 8pt;
            line-height: 1.4;
        }

        .evidencias-tabla {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .evidencia-td {
            width: 50%;
            padding: 5px;
            vertical-align: top;
            text-align: center;
        }

        .evidencia-img {
            max-width: 100%;
            max-height: 190px;
            display: block;
            margin: 0 auto;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }

        .evidencia-num {
            font-size: 7pt;
            color: #64748b;
            margin-top: 3px;
        }

        .objetivo-grupo {
            margin-bottom: 8px;
        }

        .objetivo-grupo:last-child {
            margin-bottom: 0;
        }

        .objetivo-item {
            font-size: 8pt;
            line-height: 1.45;
            text-align: justify;
            color: #333;
            margin-bottom: 5px;
            padding-left: 10px;
        }

        .objetivos-box {
            border: 1px solid #003E72;
            border-radius: 6px;
            padding: 10px 14px;
            margin: 8px 0 14px 0;
            background-color: #f8f9fa;
        }

        .generador-info {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 7.5pt;
            color: #64748b;
        }
    </style>
</head>

<body>

    {{-- ── Header fijo ── --}}
    <div class="header">
        <table class="header-inner">
            <tr>
                <td class="header-td-center">
                    <h1>Informe de Vigilancia</h1>
                    <h2>Control y Seguimiento de Dispositivos de Vigilancia</h2>
                    <div class="header-meta">
                        Orden: <strong>{{ $orden->numero_orden }}</strong>
                        @if (!empty($datosOrden['proceso']))
                            &nbsp;&nbsp;|&nbsp;&nbsp;
                            Proceso: <strong>{{ $datosOrden['proceso'] }}</strong>
                        @endif
                        &nbsp;&nbsp;|&nbsp;&nbsp;
                        Fecha de generación: <strong>{{ $fechaGeneracion }}</strong>
                    </div>
                </td>
                <td class="header-td-proderi">
                    <img src="{{ $logoProderi }}" class="logo-proderi" alt="PRODERI">
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Línea separadora del header ── --}}
    <div class="header-separator"></div>

    {{-- ── Footer fijo ── --}}
    <div class="footer">
        <div class="footer-line">Informe de Vigilancia — Uso Interno y Confidencial</div>
        <div>© {{ date('Y') }} AlenaZentrix / PRODERI S.A.S. — Todos los derechos reservados</div>
        <div>Este documento contiene información confidencial protegida por las leyes de propiedad intelectual</div>
    </div>

    {{-- ── Numeración de páginas ── --}}
    <script type="text/php">
        if (isset($pdf)) {
            $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
            $size = 7;
            $font = $fontMetrics->getFont("DejaVu Sans");
            $width = $fontMetrics->get_text_width($text, $font, $size);
            $pageWidth = $pdf->get_width();
            $x = ($pageWidth - $width) / 2;
            $y = $pdf->get_height() - 90;
            $pdf->page_text($x, $y, $text, $font, $size, array(0.4, 0.45, 0.53));
        }
    </script>

    <div class="content">

        <p style="font-size:8.5pt; line-height:1.5; text-align:justify; margin-bottom:10px;">
            Este documento constituye el informe consolidado de dispositivos de vigilancia para la Orden
            N° <strong>{{ $orden->numero_orden }}</strong>. Reúne todos los turnos registrados en campo,
            incluyendo novedades, observaciones y evidencias fotográficas por cada turno.
        </p>

        {{-- ── Antecedentes ── --}}
        @if (!empty($datosOrden['antecedente']) && $datosOrden['antecedente'] !== '—')
            <div class="section">
                <div class="section-title">ANTECEDENTES</div>
                <div class="objetivos-box">
                    <div class="objetivo-grupo">
                        <div class="objetivo-item">{{ $datosOrden['antecedente'] }}</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Objetivos ── --}}
        @if (!empty($datosOrden['objetivo']) && $datosOrden['objetivo'] !== '—')
            <div class="section">
                <div class="section-title">OBJETIVOS</div>
                <div class="objetivos-box">
                    <div class="objetivo-grupo">
                        <div class="objetivo-item">{{ $datosOrden['objetivo'] }}</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Bloques de turno ── --}}
        @forelse ($reportes as $index => $reporte)
            @php
                $datos = $reporte->datos_servicio ?? [];
                $fechaTurno = isset($datos['fecha_turno'])
                    ? \Carbon\Carbon::parse($datos['fecha_turno'])->format('d/m/Y')
                    : '—';
                $horaInicio = $datos['hora_inicio'] ?? '—';
                $horaFin    = $datos['hora_fin'] ?? '—';
                $hayNovedad      = (bool) ($datos['hay_novedad'] ?? false);
                $hayReincidencia = (bool) ($datos['hay_reincidencia'] ?? false);
                $descNovedad  = $datos['descripcion_novedad'] ?? '';
                $resultadoNombre = $resultados[$datos['resultado_reporte_id'] ?? null] ?? '—';
                $nombreInvestigador = $reporte->personal
                    ? trim($reporte->personal->nombres . ' ' . $reporte->personal->apellidos)
                    : '—';
                $imagenesDelTurno = $imagenesPerTurno[$reporte->id] ?? [];
            @endphp

            <div class="section">
                <div class="section-title-turno">
                    TURNO N° {{ $index + 1 }} — {{ $fechaTurno }}
                </div>

                {{-- Datos del turno --}}
                <table class="tabla-tecnica">
                    <tr>
                        <td class="lbl">Fecha</td>
                        <td class="val">{{ $fechaTurno }}</td>
                        <td class="lbl">Horario</td>
                        <td class="val">{{ $horaInicio }} – {{ $horaFin }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Investigador</td>
                        <td class="val">{{ $nombreInvestigador }}</td>
                        <td class="lbl">Resultado</td>
                        <td class="val">{{ $resultadoNombre }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Novedades</td>
                        <td class="val">
                            @if ($hayNovedad)
                                <span class="badge badge-orange">SÍ</span>
                            @else
                                <span class="badge badge-green">NO</span>
                            @endif
                        </td>
                        <td class="lbl">Reincidencia</td>
                        <td class="val">
                            @if ($hayReincidencia)
                                <span class="badge badge-red">SÍ</span>
                            @else
                                <span class="badge" style="background-color:#94a3b8;color:white;">NO</span>
                            @endif
                        </td>
                    </tr>
                </table>

                {{-- Descripción de novedades --}}
                @if ($hayNovedad && $descNovedad)
                    <div class="section-subtitle">DESCRIPCIÓN DE NOVEDADES</div>
                    <div class="content-box">{{ $descNovedad }}</div>
                @endif

                {{-- Observaciones del turno --}}
                <div class="section-subtitle">OBSERVACIONES</div>
                <div class="content-box">{{ $reporte->observaciones ?: 'Sin observaciones registradas.' }}</div>

                {{-- GeoReferencia --}}
                @if (!empty($reporte->geo_referencia))
                    <div class="section-subtitle">GEOREFERENCIA</div>
                    <div class="info-box">
                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-label">URL GeoReferencia:</div>
                                <div class="info-value" style="word-break: break-all;">{{ $reporte->geo_referencia }}</div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Evidencias fotográficas del turno --}}
                @if (count($imagenesDelTurno) > 0)
                    <div class="section-subtitle">EVIDENCIAS FOTOGRÁFICAS</div>
                    @php $rows = array_chunk($imagenesDelTurno, 2); @endphp
                    <table class="evidencias-tabla">
                        @foreach ($rows as $rowIndex => $row)
                            <tr>
                                @foreach ($row as $colIndex => $imgSrc)
                                    <td class="evidencia-td">
                                        <img src="{{ $imgSrc }}" class="evidencia-img" alt="Evidencia">
                                        <div class="evidencia-num">Evidencia {{ $rowIndex * 2 + $colIndex + 1 }}</div>
                                    </td>
                                @endforeach
                                @if (count($row) === 1)
                                    <td class="evidencia-td"></td>
                                @endif
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>

        @empty
            <div class="content-box" style="color:#64748b; font-style:italic;">
                Sin turnos registrados para esta orden.
            </div>
        @endforelse

        {{-- ── Destinación del Informe ── --}}
        @include('pdf.partials.destinacion-informe')

        {{-- ── Generador del informe ── --}}
        <div class="generador-info">
            Informe generado el <strong>{{ $generador['fecha'] }}</strong> por
            <strong>{{ $generador['nombre'] }}</strong> ({{ $generador['email'] }})
        </div>

    </div>
</body>

</html>
```

- [ ] **Step 2: Correr todos los tests de PDF para confirmar que pasan**

```
php artisan test --compact --filter="vigilancia pdf"
```

Resultado esperado: 5 tests PASS.

- [ ] **Step 3: Correr todos los tests del archivo para detectar regresiones**

```
php artisan test --compact tests/Feature/ReportePdfTest.php
```

Resultado esperado: todos los tests PASS.

- [ ] **Step 4: Commit**

```
git add resources/views/pdf/reporte-vigilancia.blade.php
git commit -m "feat: add reporte-vigilancia PDF blade view"
```

---

### Task 3: Botones de descarga en la UI

**Files:**
- Modify: `resources/views/livewire/coordinador/reportes-vigilancia-index.blade.php`
- Modify: `resources/views/livewire/coordinador/reporte-revisar.blade.php`

**Interfaces:**
- Consumes: ruta `operaciones.ordenes-trabajo.reporte-vigilancia` (Task 1), variables `$orden` y `$reporte` ya presentes en ambas vistas
- Produces: botón de descarga visible en index de vigilancia (siempre) y en vista de revisión individual (solo si orden Completada)

- [ ] **Step 1: Agregar botón en `reportes-vigilancia-index.blade.php`**

Localizar el div del header de la vista (líneas 1-37). Actualmente el botón "Volver" está solo:

```blade
<flux:button href="{{ route('operaciones.ordenes-trabajo') }}" icon="arrow-left" variant="ghost" wire:navigate>
    Volver
</flux:button>
```

Reemplazar ese bloque por:

```blade
<div class="flex items-center gap-2">
    @can('informes')
        <flux:button
            href="{{ route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden) }}"
            icon="arrow-down-tray"
            variant="filled"
        >
            Descargar Informe PDF
        </flux:button>
    @endcan
    <flux:button href="{{ route('operaciones.ordenes-trabajo') }}" icon="arrow-left" variant="ghost" wire:navigate>
        Volver
    </flux:button>
</div>
```

- [ ] **Step 2: Agregar botón en `reporte-revisar.blade.php` para órdenes de Vigilancia completadas**

En `reporte-revisar.blade.php`, localizar el bloque de botones PDF existentes (alrededor de líneas 19-39). Tiene esta estructura:

```blade
@if ($orden->tipo_servicio === \App\Enums\TipoServicio::Fraudes && auth()->user()->can('informes.fraude'))
    ...
@endif
@if ($orden->tipo_servicio === \App\Enums\TipoServicio::GestionLegal && auth()->user()->can('informes'))
    ...
@endif
@if ($orden->tipo_servicio === \App\Enums\TipoServicio::AuditoriaCampo && auth()->user()->can('informes'))
    ...
@endif
```

Agregar inmediatamente después del último `@endif`:

```blade
@if ($orden->tipo_servicio === \App\Enums\TipoServicio::Vigilancia && $orden->estado === \App\Enums\EstadoOrden::Completada && auth()->user()->can('informes'))
    <flux:button
        href="{{ route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden) }}"
        icon="arrow-down-tray"
        variant="filled"
    >
        Descargar Informe PDF
    </flux:button>
@endif
```

- [ ] **Step 3: Correr la suite completa para detectar regresiones**

```
php artisan test --compact
```

Resultado esperado: todos los tests PASS.

- [ ] **Step 4: Commit**

```
git add resources/views/livewire/coordinador/reportes-vigilancia-index.blade.php
git add resources/views/livewire/coordinador/reporte-revisar.blade.php
git commit -m "feat: add PDF download button for vigilancia orders"
```
