<?php

namespace App\Livewire\SuperAdmin\Users;

use App\Enums\TipoDocumento;
use App\Models\Company;
use App\Models\personals;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
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
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.superadmin')]
#[Title('Usuarios')]
class UsersIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(User::withoutGlobalScopes()->with(['personal', 'roles', 'company'])->where('is_superadmin', false))
            ->columns([
                Tables\Columns\TextColumn::make('personal.nombres')->label('Nombres')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('personal.apellidos')->label('Apellidos')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Correo')->searchable(),
                Tables\Columns\TextColumn::make('company.nombre')->label('Empresa')->searchable()->badge()->color('primary'),
                Tables\Columns\TextColumn::make('roles.name')->label('Rol')->badge()->separator(','),
                Tables\Columns\IconColumn::make('estado')->label('Activo')->boolean(),
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
                            'cargo' => $data['cargo'] ?? null,
                            'company_id' => $data['company_id'],
                        ]);

                        $user = User::create([
                            'personals_id' => $personal->id,
                            'email' => $data['email'],
                            'password' => Hash::make($data['password']),
                            'company_id' => $data['company_id'],
                            'estado' => $data['estado'] ?? true,
                        ]);

                        if (! empty($data['rol'])) {
                            $user->assignRole($data['rol']);
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
                        'company_id' => $record->company_id,
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
                            'cargo' => $data['cargo'] ?? null,
                            'company_id' => $data['company_id'],
                        ]);

                        $record->update([
                            'email' => $data['email'],
                            'company_id' => $data['company_id'],
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
                    ->using(fn (User $record) => $record->personal?->delete()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<int, mixed> */
    protected function formSchema(bool $isCreating): array
    {
        $companies = Company::query()->where('activo', true)->pluck('nombre', 'id')->toArray();
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
                    Forms\Components\TextInput::make('numero_documento')->label('Número de Documento')->required()->maxLength(20),
                    Forms\Components\TextInput::make('nombres')->label('Nombres')->required()->maxLength(100),
                    Forms\Components\TextInput::make('apellidos')->label('Apellidos')->required()->maxLength(100),
                    Forms\Components\TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(20),
                    Forms\Components\TextInput::make('cargo')->label('Cargo')->maxLength(100)->columnSpanFull(),
                ]),
            Section::make('Acceso al Sistema')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Empresa')
                        ->options($companies)
                        ->required()
                        ->searchable()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('email')->label('Correo')->email()->required()->maxLength(255),
                    Forms\Components\TextInput::make('password')->label('Contraseña')->password()->revealable()->required($isCreating)->dehydrated(fn ($state) => filled($state))->maxLength(255),
                    Forms\Components\Select::make('rol')->label('Rol')->options($roles)->searchable()->columnSpanFull(),
                    Forms\Components\Toggle::make('estado')->label('Activo')->default(true)->columnSpanFull(),
                ]),
        ];
    }

    public function render(): View
    {
        return view('livewire.super-admin.users.users-index');
    }
}
