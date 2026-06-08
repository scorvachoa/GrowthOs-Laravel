<script setup>
import { ref, watch, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { CheckCircle, ShieldAlert, Info, TriangleAlert } from 'lucide-vue-next'

const page = usePage()
const visible = ref(false)
const message = ref('')
const type = ref('success')
let timer = null

const config = {
    success: {
        icon: CheckCircle,
        bg: 'bg-green-100 dark:bg-green-800',
        border: 'border-green-400 dark:border-green-600',
        iconColor: 'text-green-600 dark:text-green-300',
    },
    error: {
        icon: ShieldAlert,
        bg: 'bg-red-100 dark:bg-red-800',
        border: 'border-red-400 dark:border-red-600',
        iconColor: 'text-red-600 dark:text-red-300',
    },
    warning: {
        icon: TriangleAlert,
        bg: 'bg-amber-100 dark:bg-amber-800',
        border: 'border-amber-400 dark:border-amber-600',
        iconColor: 'text-amber-600 dark:text-amber-300',
    },
    info: {
        icon: Info,
        bg: 'bg-blue-100 dark:bg-blue-800',
        border: 'border-blue-400 dark:border-blue-600',
        iconColor: 'text-blue-600 dark:text-blue-300',
    },
}

function open(msg, t) {
    clearTimeout(timer)
    message.value = msg
    type.value = t
    visible.value = true
    timer = setTimeout(() => { visible.value = false }, 4000)
}

watch(() => page.props.flash, (flash) => {
    if (flash?.success) open(flash.success, 'success')
    else if (flash?.warning) open(flash.warning, 'warning')
    else if (flash?.error) open(flash.error, 'error')
    else if (flash?.info) open(flash.info, 'info')
}, { immediate: true })

onUnmounted(() => clearTimeout(timer))
</script>

<template>
    <Teleport to="body">
        <Transition name="toast">
            <div v-if="visible"
                :class="`fixed top-4 right-4 z-[9999] flex items-center gap-3 px-5 py-3.5 rounded-xl border shadow-lg max-w-sm ${config[type].bg} ${config[type].border}`">
                <component :is="config[type].icon" class="w-5 h-5 shrink-0" :class="config[type].iconColor" />
                <p class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed">{{ message }}</p>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.toast-enter-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-leave-active {
    transition: all 0.2s ease-in;
}
.toast-enter-from {
    opacity: 0;
    transform: translateX(40px) scale(0.95);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(40px) scale(0.95);
}
</style>
