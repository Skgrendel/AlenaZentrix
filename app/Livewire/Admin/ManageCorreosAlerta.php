<?php

namespace App\Livewire\Admin;

use App\Models\CorreoAlerta;
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
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Correos de Alerta')]
/**
 * Gestión CRUD de destinatarios de alertas por correo ante incidencias graves detectadas en campo.
 */
class ManageCorreosAlerta extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(CorreoAlerta::query())
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre / Destinatario')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Nuevo Correo')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Agregar correo de alerta')
                    ->form($this->formSchema())
                    ->action(fn (array $data) => CorreoAlerta::create($data)),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar correo de alerta')
                    ->form($this->formSchema()),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre');
    }

    protected function formSchema(): array
    {
        return [
            Forms\Components\TextInput::make('nombre')
                ->label('Nombre / Destinatario')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('email')
                ->label('Correo Electrónico')
                ->email()
                ->required()
                ->maxLength(255),
            Forms\Components\Toggle::make('activo')
                ->label('Activo')
                ->default(true),
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.manage-correos-alerta');
    }
}
