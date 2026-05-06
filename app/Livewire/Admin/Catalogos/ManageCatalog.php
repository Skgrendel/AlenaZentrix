<?php

namespace App\Livewire\Admin\Catalogos;

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
use Livewire\Component;

class ManageCatalog extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public string $catalogModel;

    public string $catalogLabel;

    public function mount(string $model, string $label): void
    {
        $this->catalogModel = $model;
        $this->catalogLabel = $label;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->catalogModel::query())
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nomenclatura')
                    ->label('Nomenclatura')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Nuevo')
                    ->icon('heroicon-o-plus')
                    ->modalHeading(fn () => 'Crear '.$this->catalogLabel)
                    ->form($this->catalogFormSchema())
                    ->action(fn (array $data) => $this->catalogModel::create($data)),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar')
                    ->form($this->catalogFormSchema()),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre');
    }

    protected function catalogFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(100),
            Forms\Components\TextInput::make('nomenclatura')
                ->label('Nomenclatura')
                ->maxLength(20),
            Forms\Components\Toggle::make('activo')
                ->label('Activo')
                ->default(true),
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.catalogos.manage-catalog');
    }
}
