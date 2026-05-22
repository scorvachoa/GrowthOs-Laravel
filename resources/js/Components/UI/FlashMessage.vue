<script setup>
import { usePage } from '@inertiajs/vue3'

const page = usePage()
</script>

<template>
    <div
        v-if="page.props.flash.success"
        class="mb-6 p-4 rounded-xl bg-green-100 text-green-800"
    >
        {{ page.props.flash.success }}
    </div>
</template>