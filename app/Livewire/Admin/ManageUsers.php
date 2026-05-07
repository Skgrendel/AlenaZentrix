<?php

namespace App\Livewire\Admin;

use App\Enums\TipoDocumento;
use App\Mail\WelcomeNewUser;
use App\Models\personals;
use App\Models\User;
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
                        $personal = personals::create([
                            'tipo_documento' => $data['tipo_documento'],
                            'numero_documento' => $data['numero_documento'],
                            'nombres' => $data['nombres'],
                            'apellidos' => $data['apellidos'],
                            'telefono' => $data['telefono'] ?? null,
                            'estado' => '1',
                        ]);

                        $plainPassword = $data['password'];
                        $user = User::create([
                            'personals_id' => $personal->id,
                            'email' => $data['email'],
                            'password' => Hash::make($plainPassword),
                            'estado' => $data['estado'] ?? true,
                        ]);

                        if (! empty($data['rol'])) {
                            $user->assignRole($data['rol']);
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
                        'email' => $record->email,
                        'estado' => $record->estado,
                        'rol' => $record->roles->first()?->name,
                    ])
                    ->using(function (User $record, array $data): User {
                        $record->personal?->update([
                            'tipo_documento' => $data['tipo_documento'],
                            'numero_documento' => $data['numero_documento'],
                            'nombres' => $data['nombres'],
                            'apellidos' => $data['apellidos'],
                            'telefono' => $data['telefono'] ?? null,
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

                        return $record;
                    }),
                DeleteAction::make()
                    ->modalHeading('Eliminar usuario')
                    ->using(fn (User $record) => $record->personal?->delete()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->using(fn ($records) => $records->each(fn (User $user) => $user->personal?->delete())),
                ]),
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
                        ->maxLength(20),

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
                ]),

            Section::make('Acceso al Sistema')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('email')
                        ->label('Correo de Acceso')
                        ->email()
                        ->required()
                        ->maxLength(255),

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
