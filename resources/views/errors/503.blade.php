<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>503 - Servicio No Disponible</title>
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
                <div class="relative overflow-hidden bg-linear-to-r from-orange-500 to-orange-600 dark:from-orange-600 dark:to-orange-700 px-6 py-12 text-center">
                    <!-- Icono decorativo -->
                    <div class="absolute top-0 right-0 opacity-10">
                        <svg class="w-40 h-40" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/>
                        </svg>
                    </div>

                    <!-- Número del error -->
                    <div class="relative z-10">
                        <div class="text-6xl font-bold text-white mb-2">503</div>
                        <p class="text-orange-100 text-lg font-semibold">Servicio No Disponible</p>
                    </div>
                </div>

                <!-- Contenido -->
                <div class="px-6 py-8 text-center">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-3">
                        Estamos en mantenimiento
                    </h1>

                    <p class="text-slate-600 dark:text-slate-300 mb-8 leading-relaxed">
                        El servicio está temporalmente no disponible mientras realizamos trabajos de mantenimiento. Por favor intenta más tarde.
                    </p>

                    <!-- Información adicional -->
                    <div class="bg-slate-50 dark:bg-slate-700 rounded-lg p-4 mb-8">
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            <span class="font-semibold">Duración estimada:</span> Menos de 1 hora
                        </p>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex flex-col gap-3">
                        <button onclick="location.reload()"
                                class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition duration-200 shadow-md hover:shadow-lg">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Reintentar
                        </button>

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
                            ¿Problemas persistentes?
                            <a href="mailto:admin@example.com" class="text-blue-600 dark:text-blue-400 hover:underline font-semibold">
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
