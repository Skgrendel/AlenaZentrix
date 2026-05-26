<?php

namespace App\Livewire\SuperAdmin\Roles;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.superadmin')]
#[Title('Roles y Permisos')]
class RolesIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(Role::query()->withCount(['permissions', 'users']))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Rol')->sortable()->searchable()->badge()->color('primary'),
                Tables\Columns\TextColumn::make('permissions_count')->label('Permisos')->badge()->color('gray')->sortable(),
                Tables\Columns\TextColumn::make('users_count')->label('Usuarios')->badge()->color('success')->sortable(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Nuevo Rol')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Crear Rol')
                    ->form([
                        Forms\Components\TextInput::make('name')->label('Nombre del Rol')->required()->maxLength(50),
                    ])
                    ->action(function (array $data): void {
                        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
                        $this->redirect(route('superadmin.roles.editar', $role), navigate: true);
                    }),
            ])
            ->actions([
                Action::make('editar')
                    ->label('Editar permisos')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->url(fn (Role $record): string => route('superadmin.roles.editar', $record)),
                DeleteAction::make()
                    ->visible(fn (Role $record): bool => $record->name !== 'Administrador')
                    ->before(function (Role $record): void {
                        $record->syncPermissions([]);
                        $record->users()->detach();
                    }),
            ])
            ->defaultSort('name');
    }

    public function render(): View
    {
        return view('livewire.super-admin.roles.roles-index');
    }
}
