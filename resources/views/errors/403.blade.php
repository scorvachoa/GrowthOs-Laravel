<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Acceso denegado - {{ config('app.name', 'GrowthOS') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Figtree', 'system-ui', 'sans-serif'] },
                }
            }
        }
        if (localStorage.getItem('dark') === 'true' || (!localStorage.getItem('dark') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        }
    </script>
</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full text-center">
        <div class="mb-8 flex justify-center">
            <div class="w-24 h-24 rounded-3xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center shadow-lg">
                <svg class="w-12 h-12 text-red-500 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
            </div>
        </div>

        <h1 class="text-7xl font-bold text-gray-900 dark:text-white mb-2">403</h1>
        <h2 class="text-xl font-semibold text-gray-700 dark:text-gray-300 mb-3">Acceso denegado</h2>
        <p class="text-gray-500 dark:text-gray-400 mb-8 leading-relaxed">
            No tienes los permisos necesarios para acceder a esta sección.<br>
            Contacta con el administrador si crees que esto es un error.
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}"
                class="px-6 py-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition shadow-sm">
                ← Volver atrás
            </a>
            <a href="/"
                class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition shadow-sm">
                Ir al inicio
            </a>
        </div>

        <p class="mt-10 text-xs text-gray-400 dark:text-gray-600">
            {{ config('app.name', 'GrowthOS') }}
        </p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mq = window.matchMedia('(prefers-color-scheme: dark)')
            mq.addEventListener('change', function(e) {
                if (!localStorage.getItem('dark')) {
                    if (e.matches) document.documentElement.classList.add('dark')
                    else document.documentElement.classList.remove('dark')
                }
            })
        })
    </script>
</body>
</html>
