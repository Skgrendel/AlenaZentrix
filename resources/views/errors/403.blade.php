<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Prohibido</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-6">
                <img src="{{ asset('assets/image/LogoAlenaZentrix.svg') }}" alt="Logo" class="h-16 mx-auto">
            </div>

            <!-- Card principal -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg overflow-hidden">
                <!-- Header con gradiente -->
                <div class="relative overflow-hidden bg-linear-to-r from-red-500 to-red-600 dark:from-red-600 dark:to-red-700 px-6 py-12 text-center">
                    <!-- Icono decorativo -->
                    <div class="absolute top-0 right-0 opacity-10">
                        <svg class="w-40 h-40" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>

                    <!-- Número del error -->
                    <div class="relative z-10">
                        <div class="text-6xl font-bold text-white mb-2">403</div>
                        <p class="text-red-100 text-lg font-semibold">Acceso Prohibido</p>
                    </div>
                </div>

                <!-- Contenido -->
                <div class="px-6 py-8 text-center">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-3">
                        Acceso Denegado
                    </h1>

                    <p class="text-slate-600 dark:text-slate-300 mb-8 leading-relaxed">
                        No tienes permisos para acceder a este recurso. Si crees que esto es un error,
                        contacta al administrador del sistema.
                    </p>

                    <!-- Información adicional -->
                    <div class="bg-slate-50 dark:bg-slate-700 rounded-lg p-4 mb-8">
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            <span class="font-semibold">Motivo:</span> Tu rol o permisos no te permiten acceder a esta página
                        </p>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex flex-col gap-3">
                        <a href="{{ url('/') }}"
                           class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition duration-200 shadow-md hover:shadow-lg">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-3m0 0l7-4 7 4M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9m-9 4v6m-4-6v6"/>
                            </svg>
                            Ir al Inicio
                        </a>


                        <button onclick="if (window.history.length > 1) { window.history.back(); } else { location.href = '/'; }"
                                class="inline-flex items-center justify-center px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg transition duration-200 dark:bg-slate-600 dark:hover:bg-slate-500 dark:text-slate-100">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Volver Atrás
                        </button>
                    </div>

                    <!-- Nota de soporte -->
                    <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700">
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            ¿Necesitas ayuda?
                            <a href="mailto:directorti@proderi.com" class="text-blue-600 dark:text-blue-400 hover:underline font-semibold">
                                Contacta con Soporte
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Pie de página decorativo -->
            <div class="text-center mt-8">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ config('app.name', 'Laravel') }}
                </p>
            </div>
        </div>
    </div>
</body>
</html>
