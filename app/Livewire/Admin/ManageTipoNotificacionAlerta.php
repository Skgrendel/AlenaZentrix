<?php

namespace App\Livewire\Admin;

use App\Models\TipoNotificacionAlerta;
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
#[Title('Notificaciones de Alerta')]
/**
 * Gestión CRUD del catálogo de tipos de notificación de alerta disponibles en los reportes de Fraudes.
 */
class ManageTipoNotificacionAlerta extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(TipoNotificacionAlerta::query())
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
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
                    ->label('Nueva Alerta')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Crear notificación de alerta')
                    ->form($this->formSchema())
                    ->action(fn (array $data) => TipoNotificacionAlerta::create($data)),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar notificación de alerta')
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
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            Forms\Components\Toggle::make('activo')
                ->label('Activo')
                ->default(true),
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.manage-tipo-notificacion-alerta');
    }
}
