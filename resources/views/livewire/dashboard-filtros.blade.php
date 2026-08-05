<div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <flux:label>Desde</flux:label>
            <flux:input type="date" wire:model="desde" />
        </div>

        <div>
            <flux:label>Hasta</flux:label>
            <flux:input type="date" wire:model="hasta" />
        </div>

        @if ($this->puedeFiltrarPorCriterios())
            <div>
                <flux:label>Estado</flux:label>
                <flux:select wire:model="estado">
                    <flux:select.option value="">Todos</flux:select.option>
                    @foreach ($this->estados as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:label>Prioridad</flux:label>
                <flux:select wire:model="prioridad">
                    <flux:select.option value="">Todos</flux:select.option>
                    @foreach ($this->prioridades as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:label>Servicio</flux:label>
                <flux:select wire:model="tipoServicio">
                    <flux:select.option value="">Todos</flux:select.option>
                    @foreach ($this->servicios as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:label>Resultado</flux:label>
                <flux:select wire:model="resultado">
                    <flux:select.option value="">Todos</flux:select.option>
                    <flux:select.option value="positivo">Positiva</flux:select.option>
                    <flux:select.option value="negativo">Negativa</flux:select.option>
                </flux:select>
            </div>

            <div>
                <flux:label>Estado Auditoría</flux:label>
                <flux:select wire:model="estadoAuditoria">
                    <flux:select.option value="">Todos</flux:select.option>
                    @foreach ($this->estadosAuditoria as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:label>Tipo de Anomalía</flux:label>
                <flux:select wire:model="tipoAnomaliaId">
                    <flux:select.option value="">Todos</flux:select.option>
                    @foreach ($this->tiposAnomalia as $id => $nombre)
                        <flux:select.option value="{{ $id }}">{{ $nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:label>Proceso</flux:label>
                <flux:select wire:model="procesoId">
                    <flux:select.option value="">Todos</flux:select.option>
                    @foreach ($this->procesos as $id => $nombre)
                        <flux:select.option value="{{ $id }}">{{ $nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:label>Personal Asignado</flux:label>
                <flux:select wire:model="personalsId" searchable>
                    <flux:select.option value="">Todos</flux:select.option>
                    @foreach ($this->personales as $id => $nombre)
                        <flux:select.option value="{{ $id }}">{{ $nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    <div class="mt-3 flex items-center justify-end gap-2">
        <flux:button wire:click="limpiar" variant="ghost" size="sm" icon="x-mark">
            Limpiar
        </flux:button>
        <flux:button wire:click="aplicar" variant="primary" size="sm" icon="magnifying-glass">
            Filtrar
        </flux:button>
    </div>
</div>
