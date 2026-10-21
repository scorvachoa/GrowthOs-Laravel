<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { Ban } from 'lucide-vue-next'

defineProps({
    statusLabel: String,
    plan: String,
    trialEndsAt: String,
})

const logout = () => {
    router.post('/logout')
}
</script>

<template>
    <AppLayout>
        <div class="min-h-[60vh] flex items-center justify-center px-4">
            <div class="max-w-md w-full text-center bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-8">
                <div class="mx-auto w-14 h-14 rounded-full bg-red-50 dark:bg-red-950/40 flex items-center justify-center mb-5">
                    <Ban class="w-7 h-7 text-red-600 dark:text-red-400" />
                </div>

                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    Cuenta suspendida
                </h1>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    El acceso de tu empresa está bloqueado
                    <template v-if="statusLabel">
                        (estado: <span class="font-medium">{{ statusLabel }}</span>)
                    </template>
                    <template v-if="plan"> con el plan <span class="font-medium">{{ plan }}</span></template>.
                </p>

                <p v-if="trialEndsAt" class="text-sm text-gray-500 dark:text-gray-500 mb-4">
                    Tu prueba terminó el {{ trialEndsAt }}.
                </p>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    Contacta al administrador para reactivar tu cuenta.
                </p>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-semibold hover:opacity-90 transition-opacity"
                    @click="logout"
                >
                    Cerrar sesión
                </button>
            </div>
        </div>
    </AppLayout>
</template>
