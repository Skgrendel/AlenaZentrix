<?php

namespace App\Livewire\Coordinador;

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Enums\TipoServicio;
use App\Mail\OrdenAsignada;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\TipologiaOrden;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Órdenes de Trabajo')]
/**
 * Listado de todas las órdenes de trabajo para el coordinador, con acciones contextuales
 * según el estado y tipo de servicio de cada orden.
 */
class OrdenesTrabajoIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    /**
     * @var array<string, bool>
     */
    protected array $columnPresenceCache = [];

    public function table(Table $table): Table
    {
        return $table
            ->query(OrdenJob::query()->with(['personal', 'reportes.auditoria']))
            ->columns([
                Tables\Columns\TextColumn::make('numero_orden')
                    ->label('N° Orden')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('tipo_servicio')
                    ->label('Servicio')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof TipoServicio ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof TipoServicio ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('contrato')
                    ->label('Contrato')
                    ->searchable()
                    ->visible(fn (): bool => $this->tableColumnHasData('contrato')),
                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->visible(fn (): bool => $this->tableColumnHasData('cliente')),
                Tables\Columns\TextColumn::make('barrio')
                    ->label('Barrio')
                    ->searchable()
                    ->visible(fn (): bool => $this->tableColumnHasData('barrio')),
                Tables\Columns\TextColumn::make('prioridad')
                    ->label('Prioridad')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof PrioridadOrden ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof PrioridadOrden ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof EstadoOrden ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof EstadoOrden ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('personal.nombres')
                    ->label('Personal Asignado')
                    ->formatStateUsing(fn ($state, OrdenJob $record): string => $record->personal
                            ? "{$record->personal->nombres} {$record->personal->apellidos}"
                            : '—'
                    )
                    ->searchable(query: fn ($query, string $search) => $query->whereHas(
                        'personal',
                        fn ($q) => $q->where('nombres', 'like', "%{$search}%")
                            ->orWhere('apellidos', 'like', "%{$search}%")
                    ))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('fecha_limite')
                    ->label('Fecha Límite')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('fecha_completado')
                    ->label('Completado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(
                        collect(EstadoOrden::cases())
                            ->mapWithKeys(fn (EstadoOrden $case) => [$case->value => $case->label()])
                    ),
                Tables\Filters\SelectFilter::make('prioridad')
                    ->label('Prioridad')
                    ->options(
                        collect(PrioridadOrden::cases())
                            ->mapWithKeys(fn (PrioridadOrden $case) => [$case->value => $case->label()])
                    ),
                Tables\Filters\TernaryFilter::make('estado_servicio')
                    ->label('Servicio Activo'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Nueva Orden')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => auth()->user()?->can('ordenes.crear') ?? false)
                    ->url(route('operaciones.ordenes-trabajo.crear')),
            ])
            ->actions([
                Action::make('ver_reportes_vigilancia')
                    ->label('Ver Reportes')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('primary')
                    ->visible(fn (OrdenJob $record): bool => $record->tipo_servicio === TipoServicio::Vigilancia
                        && $record->estado === EstadoOrden::EnProgreso
                    )
                    ->url(fn (OrdenJob $record): string => route('operaciones.ordenes-trabajo.reportes', $record)),
                Action::make('revisar')
                    ->label('Revisar')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::EnRevision)
                    ->url(fn (OrdenJob $record): string => $record->tipo_servicio === TipoServicio::Vigilancia
                        ? route('operaciones.ordenes-trabajo.reportes', $record)
                        : route('operaciones.ordenes-trabajo.revisar', $record)
                    ),
                Action::make('ver_auditoria')
                    ->label('Auditoría')
                    ->icon('heroicon-o-shield-check')
                    ->color('purple')
                    ->visible(function (OrdenJob $record): bool {
                        if ($record->tipo_servicio !== TipoServicio::Fraudes) {
                            return false;
                        }

                        $auditoria = $record->reportes->first()?->auditoria;

                        return $auditoria !== null &&
                            $auditoria->estado !== EstadoAuditoria::PendienteAuditoria;
                    })
                    ->url(fn (OrdenJob $record): string => route(
                        'auditor.auditorias.revisar',
                        $record->reportes->first()?->auditoria
                    ).'?from=ordenes'),
                Action::make('resultado')
                    ->label('Resultado')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::Completada)
                    ->url(fn (OrdenJob $record): string => $record->tipo_servicio === TipoServicio::Vigilancia
                        ? route('operaciones.ordenes-trabajo.reportes', $record)
                        : route('operaciones.ordenes-trabajo.revisar', $record)
                    ),
                Action::make('descargar_pdf_fraude')
                    ->label('')
                    ->tooltip('Descargar Informe de Fraude')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (OrdenJob $record): bool => $record->tipo_servicio === TipoServicio::Fraudes
                        && $record->estado === EstadoOrden::Completada
                        && auth()->user()->can('informes')
                    )
                    ->url(fn (OrdenJob $record): string => route(
                        'operaciones.ordenes-trabajo.reporte-fraude',
                        [$record, 'reporte' => $record->reportes->sortByDesc('created_at')->first()?->id]
                    ))
                    ->openUrlInNewTab(),
                Action::make('descargar_pdf_invsestigacion')
                    ->label('')
                    ->tooltip('Descargar Informe de Investigación')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (OrdenJob $record): bool => $record->tipo_servicio === TipoServicio::Investigacion
                        && $record->estado === EstadoOrden::Completada
                        && auth()->user()->can('informes')
                    )
                    ->url(fn (OrdenJob $record): string => route(
                        'operaciones.ordenes-trabajo.reporte-investigacion',
                        [$record, 'reporte' => $record->reportes->sortByDesc('created_at')->first()?->id]
                    ))
                    ->openUrlInNewTab(),
                Action::make('descargar_pdf_gestion_legal')
                    ->label('')
                    ->tooltip('Descargar Informe de Gestión Legal')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (OrdenJob $record): bool => $record->tipo_servicio === TipoServicio::GestionLegal
                        && $record->estado === EstadoOrden::Completada
                        && auth()->user()->can('informes')
                    )
                    ->url(fn (OrdenJob $record): string => route(
                        'operaciones.ordenes-trabajo.reporte-gestion-legal',
                        [$record, 'reporte' => $record->reportes->sortByDesc('created_at')->first()?->id]
                    ))
                    ->openUrlInNewTab(),
                Action::make('descargar_pdf_auditoria_campo')
                    ->label('')
                    ->tooltip('Descargar Informe de Auditoría de Campo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (OrdenJob $record): bool => $record->tipo_servicio === TipoServicio::AuditoriaCampo
                        && $record->estado === EstadoOrden::Completada
                        && auth()->user()->can('informes')
                    )
                    ->url(fn (OrdenJob $record): string => route(
                        'operaciones.ordenes-trabajo.reporte-auditoria-campo',
                        $record
                    ))
                    ->openUrlInNewTab(),
                Action::make('descargar_pdf_gestion_riesgo')
                    ->label('')
                    ->tooltip('Descargar Informe de Gestión de Riesgo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (OrdenJob $record): bool => $record->tipo_servicio === TipoServicio::Gestion_Riesgo
                        && $record->estado === EstadoOrden::Completada
                        && auth()->user()->can('informes')
                    )
                    ->url(fn (OrdenJob $record): string => route(
                        'operaciones.ordenes-trabajo.reporte-gestion-riesgo',
                        [$record, 'reporte' => $record->reportes->sortByDesc('created_at')->first()?->id]
                    ))
                    ->openUrlInNewTab(),
                Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::EnProgreso || $record->estado === EstadoOrden::EnRevision || $record->estado === EstadoOrden::Asignada)
                    ->url(fn (OrdenJob $record): string => route('operaciones.ordenes-trabajo.show', $record)),
                EditAction::make()
                    ->modalHeading(fn (OrdenJob $record): string => "Editar Orden: {$record->numero_orden}")
                    ->form(fn (OrdenJob $record) => $this->ordenFormSchema($record))
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::Pendiente)
                    ->using(function (OrdenJob $record, array $data): OrdenJob {
                        $tecnicoAnterior = $record->personals_id;
                        $record->update($data);
                        if (
                            ! empty($data['personals_id']) &&
                            $data['personals_id'] !== $tecnicoAnterior
                        ) {
                            $record->load('personal');
                            $this->notificarTecnico($record);
                        }

                        return $record;
                    }),
                DeleteAction::make()
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::Pendiente),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin órdenes de trabajo Asignadas')
            ->emptyStateDescription('No hay órdenes de trabajo registradas en el sistema.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->defaultSort(function ($query) {
                return $query
                    // 1. Las órdenes con estado 'en_revision' irán primero
                    ->orderByRaw("CASE WHEN estado = 'en_revision' THEN 1 ELSE 0 END DESC")
                    // 2. El resto de las órdenes se ordenarán por fecha de creación descendente
                    ->orderBy('created_at', 'desc');
            });
    }

    protected function tableColumnHasData(string $column): bool
    {
        if (array_key_exists($column, $this->columnPresenceCache)) {
            return $this->columnPresenceCache[$column];
        }

        $query = $this->getTable()->getQuery();

        if (! $query) {
            return $this->columnPresenceCache[$column] = false;
        }

        return $this->columnPresenceCache[$column] = (clone $query)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->exists();
    }

    /**
     * @param  OrdenJob|null  $record  Cuando se edita, para apuntar al directorio correcto en S3.
     */
    protected function ordenFormSchema(?OrdenJob $record = null): array
    {
        $tecnicos = personals::query()
            ->select(['id', 'nombres', 'apellidos'])
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => "{$p->nombres} {$p->apellidos}"]);

        $fotoDirectorio = $record
            ? "ordenes/{$record->numero_orden}"
            : 'ordenes/temp';

        $tipoServicio = $record?->tipo_servicio;

        return [
            Section::make('Tipo de Servicio')
                ->schema([
                    Forms\Components\Select::make('tipo_servicio')
                        ->label('Tipo de Servicio')
                        ->options(
                            collect(TipoServicio::cases())
                                ->mapWithKeys(fn (TipoServicio $case) => [$case->value => $case->label()])
                        )
                        ->disabled()
                        ->dehydrated(false),
                ]),

            // ── Fraudes ───────────────────────────────────────────────────
            Section::make('Datos del Punto de Servicio')
                ->columns(2)
                ->visible(
                    $tipoServicio === TipoServicio::Fraudes ||
                    $tipoServicio === TipoServicio::RevisionMedidores
                )
                ->schema([
                    Forms\Components\TextInput::make('contrato')->label('Contrato')->maxLength(255),
                    Forms\Components\TextInput::make('cliente')->label('Cliente')->maxLength(255),
                    Forms\Components\TextInput::make('direccion')->label('Dirección')->maxLength(255),
                    Forms\Components\TextInput::make('barrio')->label('Barrio')->maxLength(255),
                    Forms\Components\TextInput::make('medidor')->label('N° Medidor')->maxLength(255),
                    Forms\Components\TextInput::make('tipo_medidor')->label('Tipo de Medidor')->maxLength(255),
                    Forms\Components\TextInput::make('ciclo')->label('Ciclo')->maxLength(255)->columnSpanFull(),
                ]),

            // ── Investigación ─────────────────────────────────────────────
            Section::make('Datos de la Investigación')
                ->columns(2)
                ->visible($tipoServicio === TipoServicio::Investigacion)
                ->schema([
                    Forms\Components\Select::make('tipo_investigacion')
                        ->label('Tipo de Investigación')
                        ->options([
                            'interna' => 'Interna',
                            'contratista' => 'Contratista',
                        ])
                        ->placeholder('Seleccionar')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('contrato')->label('N° Contrato')->maxLength(255),
                    Forms\Components\TextInput::make('cliente')->label('Nombre del Investigado / Comercio')->maxLength(255),
                    Forms\Components\TextInput::make('direccion')->label('Dirección')->maxLength(255),
                    Forms\Components\TextInput::make('barrio')->label('Barrio')->maxLength(255),
                ]),

            // ── Gestión Legal ─────────────────────────────────────────
            Section::make('Datos de Gestión Legal')
                ->columns(2)
                ->visible($tipoServicio === TipoServicio::GestionLegal)
                ->schema([
                    Forms\Components\Select::make('tipo_gestion_legal')
                        ->label('Tipo de Gestión Legal')
                        ->options(
                            TipologiaOrden::query()
                                ->where('activo', true)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                        )
                        ->placeholder('Seleccionar')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('numero_noticia_criminal')->label('N° Noticia Criminal')->maxLength(255),
                    Forms\Components\TextInput::make('direccion')->label('Dirección')->maxLength(255),
                    Forms\Components\TextInput::make('barrio')->label('Municipio')->maxLength(255),
                ]),

            // ── Auditoría de Campo ────────────────────────────────────────
            Section::make('Datos de Auditoría de Campo')
                ->columns(2)
                ->visible($tipoServicio === TipoServicio::AuditoriaCampo)
                ->schema([
                    Forms\Components\Select::make('tipo_auditoria_campo')
                        ->label('Subtipo de Auditoría')
                        ->options([
                            'visita_calidad_emergencia' => 'Visita de Calidad — Emergencia',
                            'visita_calidad_trabajos_varios' => 'Visita de Calidad — Trabajos Varios',
                        ])
                        ->placeholder('Seleccionar')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('cliente')
                        ->label('Nombre del Usuario')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('contrato')
                        ->label('Contrato')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('direccion')
                        ->label('Dirección')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),

            // ── Vigilancia ────────────────────────────────────────────────
            Section::make('Datos del Punto Vigilado')
                ->columns(2)
                ->visible($tipoServicio === TipoServicio::Vigilancia)
                ->schema([
                    Forms\Components\TextInput::make('contrato')->label('Contrato')->maxLength(255),
                    Forms\Components\TextInput::make('cliente')->label('Nombre del Comercio / Cliente')->maxLength(255),
                    Forms\Components\TextInput::make('direccion')->label('Dirección')->maxLength(255),
                    Forms\Components\TextInput::make('barrio')->label('Barrio / Municipio')->maxLength(255),
                ]),

            Section::make('Fotos de la Orden')
                ->schema([
                    Forms\Components\FileUpload::make('fotos')
                        ->label('Subir fotos')
                        ->disk('s3')
                        ->directory($fotoDirectorio)
                        ->visibility('public')
                        ->multiple()
                        ->image()
                        ->imagePreviewHeight('120')
                        ->maxFiles(10)
                        ->maxSize(5120)
                        ->reorderable()
                        ->panelLayout('grid')
                        ->columnSpanFull(),
                ]),

            Section::make('Gestión de la Orden')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('titulo')->label('Título')->maxLength(255)->columnSpanFull(),
                    Forms\Components\Textarea::make('descripcion')->label('Descripción')->rows(3)->columnSpanFull(),
                    Forms\Components\Select::make('prioridad')
                        ->label('Prioridad')
                        ->options(
                            collect(PrioridadOrden::cases())
                                ->mapWithKeys(fn (PrioridadOrden $case) => [$case->value => $case->label()])
                        )
                        ->required()
                        ->default(PrioridadOrden::Media->value),
                    Forms\Components\Select::make('estado')
                        ->label('Estado')
                        ->options(
                            collect(EstadoOrden::cases())
                                ->mapWithKeys(fn (EstadoOrden $case) => [$case->value => $case->label()])
                        )
                        ->required()
                        ->default(EstadoOrden::Pendiente->value),
                    Forms\Components\DateTimePicker::make('fecha_limite')->label('Fecha Límite')->nullable(),
                    Forms\Components\Select::make('personals_id')
                        ->label($tipoServicio === TipoServicio::Vigilancia ? 'Investigador Asignado' : 'Técnico Asignado')
                        ->options($tecnicos)
                        ->searchable()
                        ->nullable()
                        ->placeholder('Sin asignar')
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('estado_servicio')->label('Servicio Activo')->default(true)->columnSpanFull(),
                ]),
        ];
    }

    /**
     * Mueve fotos desde el directorio temporal al directorio definitivo de la orden en S3.
     *
     * @param  array<string>  $fotos
     * @return array<string>
     */
    protected function moverFotos(array $fotos, string $numeroOrden): array
    {
        $directorio = "ordenes/{$numeroOrden}";
        $resultado = [];

        foreach ($fotos as $rutaTemp) {
            $nombreArchivo = basename($rutaTemp);
            $rutaFinal = "{$directorio}/{$nombreArchivo}";

            try {
                if (Storage::disk('s3')->exists($rutaTemp)) {
                    Storage::disk('s3')->move($rutaTemp, $rutaFinal);
                }
            } catch (\Exception $e) {
                Log::error('No se pudo mover foto de orden.', [
                    'origen' => $rutaTemp,
                    'destino' => $rutaFinal,
                    'error' => $e->getMessage(),
                ]);
                $rutaFinal = $rutaTemp;
            }

            $resultado[] = $rutaFinal;
        }

        return $resultado;
    }

    /** Envía el correo de asignación al técnico; falla silenciosamente si no tiene email registrado. */
    protected function notificarTecnico(OrdenJob $orden): void
    {
        $email = $orden->personal?->usuario?->email;

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new OrdenAsignada($orden, $orden->personal));
        } catch (\Exception $e) {
            Log::error('No se pudo enviar notificación de orden asignada.', [
                'orden' => $orden->numero_orden,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render(): View
    {
        return view('livewire.coordinador.ordenes-trabajo-index');
    }
}
