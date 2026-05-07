<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white antialiased">
    <div class="relative grid h-dvh flex-col items-center justify-center lg:max-w-none lg:grid-cols-2">

        {{-- Panel izquierdo: imagen --}}
        <div class="relative hidden h-full lg:flex lg:flex-col lg:justify-end lg:p-10">
            <div class="absolute inset-0 bg-cover bg-center"
                style="background-image: url('{{ asset('assets/image/bg_login.webp') }}')"></div>
            <div class="absolute inset-0 bg-black/20"></div>
            <a href="https://proderi.com/" target="_blank" rel="noopener noreferrer">
                <img src="{{ asset('assets/image/logo_proderi.png') }}" alt="Proderi"
                    class="relative z-10 w-24 h-auto opacity-90" />
            </a>
        </div>

        {{-- Panel derecho: formulario --}}
        <div class="flex h-full w-full flex-col items-center justify-between bg-white px-8 py-12 lg:px-16">

            {{-- Espaciador superior --}}
            <div></div>

            {{-- Logo + formulario --}}
            <div class="mx-auto flex w-full max-w-sm flex-col items-center gap-8">
                <a href="{{ route('home') }}" wire:navigate>
                    <img src="{{ asset('assets/image/LogoAlenaZentrix.svg') }}" alt="Alena Zentrix"
                        class="h-16 w-auto" />
                </a>
                <div class="w-full">
                    {{ $slot }}
                </div>
            </div>

            {{-- Footer --}}
            <p class="text-center text-xs text-zinc-400">
                ¡Nuestro equipo hace parte del tuyo!<br>
                &copy; {{ date('Y') }} Alena Zentrix. Todos los derechos reservados.
            </p>

        </div>
    </div>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
