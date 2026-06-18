<script setup>
import { usePage } from '@inertiajs/vue3'
import { ShieldAlert, ArrowLeft, Home } from 'lucide-vue-next'

const page = usePage()
const message = page.props?.error ?? 'No tienes los permisos necesarios para acceder a esta sección.'
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 px-4">
        <div class="text-center max-w-sm">
            <div class="w-20 h-20 rounded-2xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-6">
                <ShieldAlert class="w-10 h-10 text-red-500 dark:text-red-400" />
            </div>

            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Acceso denegado</h1>

            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8 leading-relaxed">
                {{ message }}
            </p>

            <div class="flex items-center justify-center gap-3">
                <button @click="window.history.back()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition text-sm">
                    <ArrowLeft class="w-4 h-4" />
                    Volver atrás
                </button>
                <a :href="route('dashboard')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition text-sm">
                    <Home class="w-4 h-4" />
                    Ir al inicio
                </a>
            </div>
        </div>
    </div>
</template>
