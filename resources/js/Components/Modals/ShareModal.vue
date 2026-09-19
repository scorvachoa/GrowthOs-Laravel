<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { X, Users, Search } from 'lucide-vue-next'
import Modal from '@/Components/Modals/Modal.vue'
import axios from 'axios'

const props = defineProps({
    show: Boolean,
    shareableType: { type: String, required: true },
    shareableId: { type: [Number, String], required: true },
    currentShares: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved'])

const orgUsers = ref([])
const search = ref('')
const selectedUsers = ref([])
const roles = ref({})
const loading = ref(false)

const filteredUsers = computed(() => {
    const q = search.value.toLowerCase()
    const selectedIds = selectedUsers.value
    return orgUsers.value.filter(u =>
        !selectedIds.includes(u.id) && u.name.toLowerCase().includes(q)
    )
})

const selectedUserList = computed(() =>
    selectedUsers.value.map(id => orgUsers.value.find(u => u.id === id)).filter(Boolean)
)

watch(() => props.show, async (val) => {
    if (val) {
        selectedUsers.value = props.currentShares.map(s => s.user_id || s.id)
        props.currentShares.forEach(s => {
            roles.value[s.user_id || s.id] = s.role || 'editor'
        })
        try {
            const res = await axios.get('/task-shares/users')
            orgUsers.value = res.data
        } catch (e) {
            console.error('Failed to load org users', e)
        }
    } else {
        search.value = ''
    }
})

function toggleUser(userId) {
    const idx = selectedUsers.value.indexOf(userId)
    if (idx === -1) {
        selectedUsers.value.push(userId)
        if (!roles.value[userId]) roles.value[userId] = 'editor'
    } else {
        selectedUsers.value.splice(idx, 1)
        delete roles.value[userId]
    }
}

function removeUser(userId) {
    selectedUsers.value = selectedUsers.value.filter(id => id !== userId)
    delete roles.value[userId]
}

async function save() {
    loading.value = true
    try {
        await axios.post('/task-shares', {
            shareable_type: props.shareableType,
            shareable_id: props.shareableId,
            user_ids: selectedUsers.value,
            roles: selectedUsers.value.map(id => roles.value[id] || 'editor'),
        })
        emit('saved')
        emit('close')
    } catch (e) {
        console.error('Failed to save shares', e)
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <Modal :show="show" @close="emit('close')">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Users class="w-5 h-5 text-indigo-500" />
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Compartir tarea</h3>
                </div>
                <button @click="emit('close')" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <X class="w-5 h-5 text-gray-500" />
                </button>
            </div>

            <div v-if="selectedUserList.length" class="space-y-2">
                <div v-for="user in selectedUserList" :key="user.id"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                    <span class="flex-1 text-sm font-medium text-indigo-700 dark:text-indigo-300">{{ user.name }}</span>
                    <select v-model="roles[user.id]"
                        class="text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white py-1 px-2">
                        <option value="editor">Editor</option>
                        <option value="reader">Lector</option>
                    </select>
                    <button @click="removeUser(user.id)"
                        class="p-1 rounded hover:bg-indigo-200 dark:hover:bg-indigo-800 transition text-indigo-500">
                        <X class="w-3 h-3" />
                    </button>
                </div>
            </div>

            <div class="relative">
                <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input v-model="search" type="text" placeholder="Buscar usuario..."
                    class="w-full pl-9 pr-3 py-2 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500" />
            </div>

            <div class="max-h-48 overflow-y-auto space-y-1">
                <button v-for="user in filteredUsers" :key="user.id" @click="toggleUser(user.id)"
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-left transition"
                    :class="selectedUsers.includes(user.id)
                        ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'">
                    {{ user.name }}
                </button>
                <p v-if="search && filteredUsers.length === 0" class="text-xs text-gray-400 dark:text-gray-500 text-center py-2">
                    Sin resultados
                </p>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                <button @click="emit('close')"
                    class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    Cancelar
                </button>
                <button @click="save" :disabled="loading"
                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition disabled:opacity-50">
                    {{ loading ? 'Guardando...' : 'Guardar' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
