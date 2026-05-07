@props([
    'sidebar' => false,
])

@if($sidebar)
    <a {{ $attributes }} class="flex items-center px-2 py-1">
        <x-app-logo-icon class="h-10 w-auto" />
    </a>
@else
    <flux:brand name="Alena Zentrix" {{ $attributes }}>
        <x-slot name="logo" class="flex items-center justify-center">
            <x-app-logo-icon class="h-8 w-auto" />
        </x-slot>
    </flux:brand>
@endif
