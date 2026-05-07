<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your personal information and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">

            {{-- Datos personales --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input
                    wire:model="nombres"
                    label="Nombres"
                    type="text"
                    required
                    autofocus
                    autocomplete="given-name"
                />
                <flux:input
                    wire:model="apellidos"
                    label="Apellidos"
                    type="text"
                    required
                    autocomplete="family-name"
                />
                <flux:select wire:model="tipo_documento" label="Tipo de Documento" required>
                    <flux:select.option value="" disabled>Seleccionar</flux:select.option>
                    @foreach($this->tiposDocumento() as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input
                    wire:model="numero_documento"
                    label="Número de Documento"
                    type="text"
                    required
                />
                <flux:input
                    wire:model="telefono"
                    label="Teléfono"
                    type="text"
                    autocomplete="tel"
                    class="sm:col-span-2"
                />
            </div>

            {{-- Correo electrónico (solo lectura) --}}
            <flux:input
                wire:model="email"
                label="{{ __('Email') }}"
                type="email"
                readonly
                disabled
                autocomplete="off"
            />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
</section>
