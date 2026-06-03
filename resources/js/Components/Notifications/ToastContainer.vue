<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

import Toast from './Toast.vue'

const page = usePage()

const toasts = ref([])

const addToast = (message, type) => {
    const id = Date.now() + Math.random()

    toasts.value.push({
        id,
        message,
        type,
    })

    setTimeout(() => {
        removeToast(id)
    }, 4000)
}

const removeToast = (id) => {
    toasts.value = toasts.value.filter(
        toast => toast.id !== id
    )
}

watch(
    () => page.props.flash?.success,
    (message) => {
        if (message) {
            addToast(message, 'success')
        }
    }
)

/* Error toasts are handled by ErrorModal.vue */

watch(
    () => page.props.flash?.info,
    (message) => {
        if (message) {
            addToast(message, 'info')
        }
    }
)
</script>

<template>
    <div class="fixed top-6 right-6 z-50 space-y-3">

        <transition-group
            name="toast"
            tag="div"
        >

            <Toast
                v-for="toast in toasts"
                :key="toast.id"
                :message="toast.message"
                :type="toast.type"
            />

        </transition-group>

    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all .3s ease;
}

.toast-enter-from {
    opacity: 0;
    transform: translateY(-10px);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(30px);
}
</style>
