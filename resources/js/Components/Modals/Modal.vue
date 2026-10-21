<script setup>
import { ref } from 'vue'
import { useModalA11y } from '@/Composables/useModalA11y'

const props = defineProps({
    show: Boolean,
    title: { type: String, default: null },
})

const emit = defineEmits(['close'])

const dialogRef = ref(null)

useModalA11y(dialogRef, () => props.show, () => emit('close'))
</script>

<template>
    <Teleport to="body">
        <transition name="fade">
            <div
                v-if="show"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50"
                @click.self="emit('close')"
            >
                <div
                    ref="dialogRef"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                    tabindex="-1"
                    class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4 max-h-[90vh] overflow-y-auto outline-none"
                >
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
