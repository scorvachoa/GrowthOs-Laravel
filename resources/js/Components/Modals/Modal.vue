<script setup>
import { watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    show: Boolean,
})

const emit = defineEmits(['close'])

function handleEscape(e) {
    if (e.key === 'Escape' && props.show) {
        emit('close')
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
    <Teleport to="body">
        <transition name="fade">
            <div
                v-if="show"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50"
                @click.self="emit('close')"
            >
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4 max-h-[90vh] overflow-y-auto">
                    <slot />
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity .2s;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
