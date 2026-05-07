<div class="space-y-4">
    <div>
        <flux:heading size="xl">Configuración</flux:heading>
        <flux:text class="mt-1 text-sm">Gestiona los catálogos dinámicos del sistema.</flux:text>
    </div>

    <div
        x-data="{ active: 'anomalia' }"
        class="flex gap-4"
    >
        {{-- Navegación lateral --}}
        <div class="w-52 shrink-0">
            <flux:navlist>
                <flux:navlist.group heading="Tipos">
                    <flux:navlist.item href="#" x-on:click.prevent="active = 'anomalia'" x-bind:data-current="active === 'anomalia'">
                        Anomalía
                    </flux:navlist.item>
                    <flux:navlist.item href="#" x-on:click.prevent="active = 'comercio'" x-bind:data-current="active === 'comercio'">
                        Comercio
                    </flux:navlist.item>
                    <flux:navlist.item href="#" x-on:click.prevent="active = 'imposibilidad'" x-bind:data-current="active === 'imposibilidad'">
                        Imposibilidad
                    </flux:navlist.item>
                    <flux:navlist.item href="#" x-on:click.prevent="active = 'regulador'" x-bind:data-current="active === 'regulador'">
                        Regulador
                    </flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Marcas">
                    <flux:navlist.item href="#" x-on:click.prevent="active = 'medidor'" x-bind:data-current="active === 'medidor'">
                        Medidor
                    </flux:navlist.item>
                    <flux:navlist.item href="#" x-on:click.prevent="active = 'marca-regulador'" x-bind:data-current="active === 'marca-regulador'">
                        Regulador
                    </flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Catálogos Legales">
                    <flux:navlist.item href="#" x-on:click.prevent="active = 'convenciones'" x-bind:data-current="active === 'convenciones'">
                        Convenciones
                    </flux:navlist.item>
                    <flux:navlist.item href="#" x-on:click.prevent="active = 'tipologias-orden'" x-bind:data-current="active === 'tipologias-orden'">
                        Tipologías de Orden
                    </flux:navlist.item>
                    <flux:navlist.item href="#" x-on:click.prevent="active = 'resultados-reporte'" x-bind:data-current="active === 'resultados-reporte'">
                        Resultados del Reporte
                    </flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Alertas">
                    <flux:navlist.item href="#" x-on:click.prevent="active = 'notificacion-alerta'" x-bind:data-current="active === 'notificacion-alerta'">
                        Notificación Alerta
                    </flux:navlist.item>
                    <flux:navlist.item href="#" x-on:click.prevent="active = 'correos-alerta'" x-bind:data-current="active === 'correos-alerta'">
                        Correos de Alerta
                    </flux:navlist.item>
                </flux:navlist.group>
            </flux:navlist>
        </div>

        {{-- Paneles --}}
        <div class="flex-1 min-w-0">
            <div x-show="active === 'anomalia'" x-cloak>
                <livewire:admin.catalogos.manage-catalog
                    :model="\App\Models\TipoAnomalia::class"
                    label="Tipo de Anomalía"
                    wire:key="catalog-anomalia" />
            </div>
            <div x-show="active === 'comercio'" x-cloak>
                <livewire:admin.catalogos.manage-catalog
                    :model="\App\Models\TipoComercio::class"
                    label="Tipo de Comercio"
                    wire:key="catalog-comercio" />
            </div>
            <div x-show="active === 'imposibilidad'" x-cloak>
                <livewire:admin.catalogos.manage-catalog
                    :model="\App\Models\TipoImposibilidad::class"
                    label="Tipo de Imposibilidad"
                    wire:key="catalog-imposibilidad" />
            </div>
            <div x-show="active === 'regulador'" x-cloak>
                <livewire:admin.catalogos.manage-catalog
                    :model="\App\Models\TipoRegulador::class"
                    label="Tipo de Regulador"
                    wire:key="catalog-regulador" />
            </div>
            <div x-show="active === 'medidor'" x-cloak>
                <livewire:admin.catalogos.manage-catalog
                    :model="\App\Models\MarcaMedidor::class"
                    label="Marca de Medidor"
                    wire:key="catalog-medidor" />
            </div>
            <div x-show="active === 'marca-regulador'" x-cloak>
                <livewire:admin.catalogos.manage-catalog
                    :model="\App\Models\MarcaRegulador::class"
                    label="Marca de Regulador"
                    wire:key="catalog-marca-regulador" />
            </div>
            <div x-show="active === 'convenciones'" x-cloak>
                <livewire:admin.catalogos.manage-catalog
                    :model="\App\Models\Convencion::class"
                    label="Convención"
                    wire:key="catalog-convencion" />
            </div>
            <div x-show="active === 'tipologias-orden'" x-cloak>
                <livewire:admin.catalogos.manage-catalog
                    :model="\App\Models\TipologiaOrden::class"
                    label="Tipología de Orden"
                    wire:key="catalog-tipologia-orden" />
            </div>
            <div x-show="active === 'resultados-reporte'" x-cloak>
                <livewire:admin.catalogos.manage-catalog
                    :model="\App\Models\ResultadoReporte::class"
                    label="Resultado del Reporte"
                    wire:key="catalog-resultado-reporte" />
            </div>
            <div x-show="active === 'notificacion-alerta'" x-cloak>
                <livewire:admin.manage-tipo-notificacion-alerta
                    wire:key="catalog-notificacion-alerta" />
            </div>
            <div x-show="active === 'correos-alerta'" x-cloak>
                <livewire:admin.manage-correos-alerta
                    wire:key="catalog-correos-alerta" />
            </div>
        </div>
    </div>
</div>
