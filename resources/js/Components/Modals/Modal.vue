<script setup>
defineProps({
    show: Boolean,
})
</script>

<template>
    <transition name="fade">

        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        >

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 mx-4 max-h-[90vh] overflow-y-auto">
                <slot />
            </div>

        </div>

    </transition>
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