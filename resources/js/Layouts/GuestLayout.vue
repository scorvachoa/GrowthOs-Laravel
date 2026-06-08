<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-indigo-950 to-gray-900 flex flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <Link href="/" class="inline-flex items-center gap-2">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg">G</div>
                    <span class="text-white font-bold text-2xl">GrowthOS</span>
                </Link>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-8 backdrop-blur-sm">
                <slot />
            </div>
        </div>
    </div>
</template>
