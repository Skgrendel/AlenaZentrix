<?php

namespace App\Livewire\Admin;

use App\Enums\TipoDocumento;
use App\Enums\TipoServicio;
use App\Mail\WelcomeNewUser;
use App\Models\personals;
use App\Models\User;
use App\Models\UsuarioServicioAuditable;
use App\Services\TenantService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Gestión de Usuarios')]
/**
 * Gestión CRUD de usuarios del sistema. Crea simultáneamente el registro `personals` y el `User`
 * y envía el correo de bienvenida con las credenciales al nuevo usuario.
 */
class ManageUsers extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->with(['personal', 'roles']))
            ->columns([
                Tables\Columns\TextColumn::make('personal.nombres')
                    ->label('Nombres')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('personal.apellidos')
                    ->label('Apellidos')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('personal.tipo_documento')
                    ->label('Tipo Doc.')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof TipoDocumento ? $state->label() : $state),
                Tables\Columns\TextColumn::make('personal.numero_documento')
                    ->label('Documento')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),
                Tables\Columns\IconColumn::make('estado')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->separator(','),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Nuevo usuario')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Crear usuario')
                    ->form($this->formSchema(isCreating: true))
                    ->action(function (array $data): void {
                        $tenantId = app(TenantService::class)->id();

                        $personal = personals::create([
                            'tipo_documento' => $data['tipo_documento'],
                            'numero_documento' => $data['numero_documento'],
                            'nombres' => $data['nombres'],
                            'apellidos' => $data['apellidos'],
                            'telefono' => $data['telefono'] ?? null,
                            'cargo' => $data['cargo'] ?? null,
                            'company_id' => $tenantId,
                        ]);

                        $plainPassword = $data['password'];
                        $user = User::create([
                            'personals_id' => $personal->id,
                            'email' => $data['email'],
                            'password' => Hash::make($plainPassword),
                            'company_id' => $tenantId,
                            'estado' => $data['estado'] ?? true,
                        ]);

                        if (! empty($data['rol'])) {
                            $user->assignRole($data['rol']);
                        }

                        // Si es Auditor, sincronizar servicios auditables
                        if ($data['rol'] === 'Auditor' && ! empty($data['servicios_auditables'])) {
                            UsuarioServicioAuditable::where('user_id', $user->id)->delete();
                            foreach ($data['servicios_auditables'] as $servicio) {
                                UsuarioServicioAuditable::create([
                                    'user_id' => $user->id,
                                    'tipo_servicio' => $servicio,
                                ]);
                            }
                        }

                        try {
                            Mail::to($user->email)->send(new WelcomeNewUser(
                                $user->email,
                                $personal->nombres,
                                $personal->apellidos,
                                $plainPassword,
                                route('login'),
                            ));
                        } catch (\Exception $e) {
                            \Log::error('Welcome email failed for user: '.$user->email, ['error' => $e->getMessage()]);
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar usuario')
                    ->form($this->formSchema(isCreating: false))
                    ->fillForm(fn (User $record) => [
                        'tipo_documento' => $record->personal?->tipo_documento,
                        'numero_documento' => $record->personal?->numero_documento,
                        'nombres' => $record->personal?->nombres,
                        'apellidos' => $record->personal?->apellidos,
                        'telefono' => $record->personal?->telefono,
                        'cargo' => $record->personal?->cargo,
                        'email' => $record->email,
                        'estado' => $record->estado,
                        'rol' => $record->roles->first()?->name,
                        'servicios_auditables' => $record->serviciosAuditables()->pluck('tipo_servicio')->toArray(),
                    ])
                    ->using(function (User $record, array $data): User {
                        $record->personal?->update([
                            'tipo_documento' => $data['tipo_documento'],
                            'numero_documento' => $data['numero_documento'],
                            'nombres' => $data['nombres'],
                            'apellidos' => $data['apellidos'],
                            'telefono' => $data['telefono'] ?? null,
                            'cargo' => $data['cargo'] ?? null,
                        ]);

                        $record->update([
                            'email' => $data['email'],
                            'estado' => $data['estado'],
                        ]);

                        if (filled($data['password'] ?? null)) {
                            $record->update(['password' => Hash::make($data['password'])]);
                        }

                        if (! empty($data['rol'])) {
                            $record->syncRoles([$data['rol']]);
                        }

                        // Si es Auditor, sincronizar servicios auditables
                        if ($data['rol'] === 'Auditor' && ! empty($data['servicios_auditables'])) {
                            UsuarioServicioAuditable::where('user_id', $record->id)->delete();
                            foreach ($data['servicios_auditables'] as $servicio) {
                                UsuarioServicioAuditable::create([
                                    'user_id' => $record->id,
                                    'tipo_servicio' => $servicio,
                                ]);
                            }
                        } elseif ($data['rol'] !== 'Auditor') {
                            // Si cambia a otro rol, eliminar servicios auditables
                            UsuarioServicioAuditable::where('user_id', $record->id)->delete();
                        }

                        return $record;
                    }),

                Action::make('toggleEstado')
                    ->label(fn (User $record) => $record->estado ? 'Desactivar' : 'Activar')
                    ->icon(fn (User $record) => $record->estado ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn (User $record) => $record->estado ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record) => $record->estado ? 'Desactivar usuario' : 'Activar usuario')
                    ->modalDescription(fn (User $record) => $record->estado
                        ? "¿Está seguro que desea desactivar a {$record->fullName()}? El usuario no podrá iniciar sesión hasta que sea activado nuevamente."
                        : "¿Desea activar nuevamente a {$record->fullName()}?"
                    )
                    ->modalSubmitActionLabel(fn (User $record) => $record->estado ? 'Sí, desactivar' : 'Sí, activar')
                    ->action(fn (User $record) => $record->update(['estado' => ! $record->estado])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Esquema de formulario compartido para crear y editar usuarios.
     * La contraseña es obligatoria solo en creación; en edición se omite si está vacía.
     *
     * @return array<int, mixed>
     */
    protected function formSchema(bool $isCreating): array
    {
        $roles = Role::pluck('name', 'name')->toArray();

        return [
            Section::make('Datos Personales')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tipo_documento')
                        ->label('Tipo de Documento')
                        ->options(
                            collect(TipoDocumento::cases())
                                ->mapWithKeys(fn (TipoDocumento $case) => [$case->value => $case->label()])
                        )
                        ->required(),

                    Forms\Components\TextInput::make('numero_documento')
                        ->label('Número de Documento')
                        ->required()
                        ->maxLength(20)
                        ->unique(
                            table: 'personals',
                            column: 'numero_documento',
                            ignorable: fn ($record) => $record?->personal,
                        )
                        ->validationMessages([
                            'unique' => 'Este número de documento ya está registrado en el sistema.',
                        ]),

                    Forms\Components\TextInput::make('nombres')
                        ->label('Nombres')
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('apellidos')
                        ->label('Apellidos')
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('telefono')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(20),

                    Forms\Components\TextInput::make('cargo')
                        ->label('Cargo')
                        ->maxLength(100)
                        ->columnSpanFull(),
                ]),

            Section::make('Acceso al Sistema')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('email')
                        ->label('Correo de Acceso')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(
                            table: 'users',
                            column: 'email',
                            ignorable: fn ($record) => $record,
                        )
                        ->validationMessages([
                            'unique' => 'Este correo electrónico ya está registrado en el sistema.',
                        ]),

                    Forms\Components\TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->required($isCreating)
                        ->dehydrated(fn ($state) => filled($state))
                        ->maxLength(255),

                    Forms\Components\Select::make('rol')
                        ->label('Rol')
                        ->options($roles)
                        ->searchable()
                        ->live()
                        ->columnSpanFull(),

                    Forms\Components\CheckboxList::make('servicios_auditables')
                        ->label('Servicios que puede auditar')
                        ->options(
                            collect(TipoServicio::cases())
                                ->mapWithKeys(fn (TipoServicio $case) => [$case->value => $case->label()])
                        )
                        ->visible(fn ($get) => $get('rol') === 'Auditor')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('estado')
                        ->label('Activo')
                        ->default(true)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.manage-users');
    }
}
