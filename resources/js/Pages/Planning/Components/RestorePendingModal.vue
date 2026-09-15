<script setup>
import Modal from '@/Components/Modals/Modal.vue'
import { ref, computed, watch } from 'vue'
import { X } from 'lucide-vue-next'

const props = defineProps({
    show: Boolean,
    task: Object,
    workBlocks: Array,
    workingDays: Array,
})

const emit = defineEmits(['close', 'restore'])

const selectedDate = ref('')
const selectedBlock = ref('')
const availableBlocks = ref([])

const today = computed(() => {
    const d = new Date()
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
})

const dayOfWeek = computed(() => {
    if (!selectedDate.value) return null
    const [y, m, d] = selectedDate.value.split('-').map(Number)
    return new Date(y, m - 1, d).getDay()
})

const isNonWorkingDay = computed(() => {
    if (dayOfWeek.value === null) return false
    return !props.workingDays?.includes(dayOfWeek.value)
})

async function checkAvailableBlocks() {
    if (!selectedDate.value || !props.workBlocks?.length) {
        availableBlocks.value = []
        return
    }
    try {
        const res = await axios.get('/planning/occupied-blocks', {
            params: { date: selectedDate.value, except_task_id: props.task?.id }
        })
        availableBlocks.value = res.data.available || props.workBlocks
    } catch {
        availableBlocks.value = props.workBlocks
    }
    selectedBlock.value = availableBlocks.value[0] || ''
}

watch(() => props.show, (val) => {
    if (val) {
        selectedDate.value = today.value
        selectedBlock.value = ''
        checkAvailableBlocks()
    }
})

watch(selectedDate, () => {
    selectedBlock.value = ''
    checkAvailableBlocks()
})

function submit() {
    if (!selectedDate.value || !selectedBlock.value) return
    emit('restore', {
        task_date: selectedDate.value,
        time_range: selectedBlock.value,
    })
}
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Restaurar tarea pendiente</h3>
                <button @click="emit('close')" class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <X class="w-5 h-5 text-gray-500" />
                </button>
            </div>

            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <p class="font-medium text-gray-900 dark:text-white text-sm">{{ task?.title }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Fecha original: {{ task?.original_date }} | Bloque: {{ task?.time_range }}
                </p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nueva fecha</label>
                    <input type="date" v-model="selectedDate" :min="today"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                    <p v-if="isNonWorkingDay" class="mt-1 text-xs text-red-500">Este día no es hábil</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bloque de tiempo</label>
                    <select v-model="selectedBlock"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">Seleccionar bloque</option>
                        <option v-for="block in availableBlocks" :key="block" :value="block">{{ block }}</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button @click="emit('close')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                    Cancelar
                </button>
                <button @click="submit" :disabled="!selectedDate || !selectedBlock || isNonWorkingDay"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Restaurar
                </button>
            </div>
        </div>
    </Modal>
</template>
