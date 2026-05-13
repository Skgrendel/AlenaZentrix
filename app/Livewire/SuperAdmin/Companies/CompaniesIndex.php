<?php

namespace App\Livewire\SuperAdmin\Companies;

use App\Models\Company;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
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

#[Layout('layouts.superadmin')]
#[Title('Empresas')]
class CompaniesIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function createAction(): Action
    {
        return Action::make('create')
            ->label('Nueva empresa')
            ->icon('heroicon-o-plus')
            ->modalHeading('Crear empresa')
            ->form($this->companyFormSchema())
            ->action(fn (array $data) => Company::create($data));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Company::query())
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->label('Empresa')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('activo')->label('Activa')->boolean(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('created_at')->label('Creada')->dateTime('d/m/Y')->sortable(),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar empresa')
                    ->form($this->companyFormSchema())
                    ->using(fn (Company $record, array $data) => $record->update($data)),
                DeleteAction::make()
                    ->modalHeading('Eliminar empresa'),
            ])
            ->defaultSort('nombre');
    }

    /** @return array<int, mixed> */
    protected function companyFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(150),
            Forms\Components\Toggle::make('activo')
                ->label('Activa')
                ->default(true),
        ];
    }

    public function render(): View
    {
        return view('livewire.super-admin.companies.companies-index');
    }
}
