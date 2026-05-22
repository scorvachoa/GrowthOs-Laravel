<script setup>
import { usePage } from '@inertiajs/vue3'

const page = usePage()
</script>

<template>
    <header
        class="h-16 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-6"
    >
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                Dashboard
            </h2>
        </div>

        <div class="text-sm text-gray-600 dark:text-gray-300">
            {{ page.props.auth.user.name }}
        </div>
    </header>
</template>