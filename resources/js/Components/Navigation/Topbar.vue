<script setup>
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

const page = usePage()

const user = computed(() =>
    page.props.auth?.user
)

const logout = () => {
    router.post('/logout')
}
</script>

<template>
    <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-8 py-4 flex items-center justify-between">

        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                GrowthOS
            </h2>
        </div>

        <div class="flex items-center gap-4">

            <Link
                href="/profile"
                class="flex items-center gap-3 hover:bg-gray-100 dark:hover:bg-gray-800 px-3 py-2 rounded-xl transition"
            >

                <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold">
                    {{ user?.name?.charAt(0) }}
                </div>

                <div class="text-left">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ user?.name }}
                    </p>

                    <p class="text-xs text-gray-500">
                        {{ user?.email }}
                    </p>
                </div>

            </Link>

            <button
                @click="logout"
                class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white transition"
            >
                Logout
            </button>

        </div>

    </header>
</template>