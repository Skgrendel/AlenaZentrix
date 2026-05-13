<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('superadmin.dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Superadmin')" class="grid">
                <flux:sidebar.item icon="home" :href="route('superadmin.dashboard')"
                    :current="request()->routeIs('superadmin.dashboard')" wire:navigate>
                    {{ __('Panel') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="building-office" :href="route('superadmin.companies')"
                    :current="request()->routeIs('superadmin.companies')" wire:navigate>
                    {{ __('Empresas') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="users" :href="route('superadmin.users')"
                    :current="request()->routeIs('superadmin.users')" wire:navigate>
                    {{ __('Usuarios') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="shield-check" :href="route('superadmin.roles')"
                    :current="request()->routeIs('superadmin.roles')" wire:navigate>
                    {{ __('Roles y Permisos') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:spacer />

        <x-desktop-user-menu class="hidden lg:block" />
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->fullName()" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->fullName() }}</flux:heading>
                                <flux:text class="truncate text-zinc-400">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Cerrar sesión') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    <flux:main>
        {{ $slot }}
    </flux:main>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @stack('scripts')
    @fluxScripts
    @filamentScripts
</body>

</html>
