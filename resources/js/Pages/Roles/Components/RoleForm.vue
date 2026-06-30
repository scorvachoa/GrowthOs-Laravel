<script setup>
import { computed, ref } from 'vue'
import TextInput from '@/Components/Forms/TextInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { Shield, ShieldCheck, LayoutDashboard, Users, Shield as ShieldIcon, ClipboardList, BarChart3, Youtube, Sparkles, Lightbulb, Building2, Download, Eye, FileDown, Upload, Settings, Search, SearchX, Umbrella, CalendarClock, HardDrive } from 'lucide-vue-next'

const props = defineProps({
    form: Object,
    permissions: Array,
    submitLabel: { type: String, default: 'Guardar' },
})

const emit = defineEmits(['submit'])

const permissionSearch = ref('')

const filteredGroups = computed(() => {
    const q = permissionSearch.value.toLowerCase().trim()
    if (!q) return groups.value
    return groups.value.map(g => ({
        ...g,
        permissions: g.permissions.filter(p => p.name.toLowerCase().includes(q)),
    })).filter(g => g.permissions.length > 0)
})

const groups = computed(() => {
    const iconMap = {
        'view dashboard': LayoutDashboard,
        'view users': Users,
        'view roles': ShieldIcon,
        'view planning': ClipboardList,
        'view tasks': Eye,
        'view ideas': Lightbulb,
        'view reports': BarChart3,
        'view youtube': Youtube,
        'generate ai': Sparkles,
        'view empresa': Building2,
        'view configuracion': Settings,
        'view vacations': Umbrella,
        'view time off': CalendarClock,
        'view backup': HardDrive,
    }

    const groupMap = {
        'view dashboard': 'Dashboard',
        'view users': 'Usuarios',
        'create users': 'Usuarios',
        'edit users': 'Usuarios',
        'delete users': 'Usuarios',
        'view roles': 'Roles',
        'create roles': 'Roles',
        'edit roles': 'Roles',
        'delete roles': 'Roles',
        'view planning': 'Planificación',
        'create planning': 'Planificación',
        'edit planning': 'Planificación',
        'delete planning': 'Planificación',
        'export planning': 'Planificación',
        'view tasks': 'Tareas',
        'view ideas': 'Ideas',
        'create ideas': 'Ideas',
        'edit ideas': 'Ideas',
        'delete ideas': 'Ideas',
        'import ideas': 'Ideas',
        'export ideas': 'Ideas',
        'view reports': 'Reportes',
        'download reports': 'Reportes',
        'view youtube': 'YouTube',
        'generate ai': 'Generador IA',
        'view ai history': 'Generador IA',
        'download ai': 'Generador IA',
        'view empresa': 'Empresa',
        'create empresa': 'Empresa',
        'edit empresa': 'Empresa',
        'delete empresa': 'Empresa',

        'view configuracion': 'Configuración',
        'configure work hours': 'Configuración',
        'configure youtube': 'Configuración',
        'configure dashboard': 'Configuración',
        'configure backup': 'Configuración',
        'view vacations': 'Vacaciones',
        'create vacations': 'Vacaciones',
        'edit vacations': 'Vacaciones',
        'delete vacations': 'Vacaciones',
        'approve vacations': 'Vacaciones',
        'reject vacations': 'Vacaciones',
        'view time off': 'Permisos',
        'create time off': 'Permisos',
        'edit time off': 'Permisos',
        'delete time off': 'Permisos',
        'approve time off': 'Permisos',
        'reject time off': 'Permisos',
        'view backup': 'Backup',
        'create backup': 'Backup',
    }

    const groupOrder = ['Dashboard', 'Usuarios', 'Roles', 'Planificación', 'Tareas', 'Ideas', 'Reportes', 'YouTube', 'Generador IA', 'Empresa', 'Configuración', 'Vacaciones', 'Permisos', 'Backup']
    const grouped = {}

    for (const p of props.permissions || []) {
        const group = groupMap[p.name] || 'Otros'
        if (!grouped[group]) grouped[group] = []
        grouped[group].push(p)
    }

    return groupOrder.filter(g => grouped[g]).map(g => ({
        name: g,
        icon: iconMap[grouped[g][0]?.name] || ShieldIcon,
        permissions: grouped[g],
    }))
})

const actionColor = (name) => {
    if (name.startsWith('delete')) return 'red'
    if (name.startsWith('create')) return 'amber'
    if (name.startsWith('edit')) return 'blue'
    if (name.startsWith('view')) return 'teal'
    if (name.startsWith('export') || name.startsWith('download') || name.startsWith('import')) return 'purple'
    if (name.startsWith('approve')) return 'green'
    if (name.startsWith('reject')) return 'red'
    return 'gray'
}

const colorClass = (name, selected) => {
    const action = actionColor(name)
    const colors = {
        red: selected
            ? 'bg-red-100 text-red-700 border-red-300 dark:bg-red-900/40 dark:text-red-300 dark:border-red-700'
            : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100 dark:bg-gray-800/50 dark:text-gray-400 dark:border-gray-700 dark:hover:bg-gray-700/50',
        amber: selected
            ? 'bg-amber-100 text-amber-700 border-amber-300 dark:bg-amber-900/40 dark:text-amber-300 dark:border-amber-700'
            : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100 dark:bg-gray-800/50 dark:text-gray-400 dark:border-gray-700 dark:hover:bg-gray-700/50',
        blue: selected
            ? 'bg-blue-100 text-blue-700 border-blue-300 dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-700'
            : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100 dark:bg-gray-800/50 dark:text-gray-400 dark:border-gray-700 dark:hover:bg-gray-700/50',
        teal: selected
            ? 'bg-teal-100 text-teal-700 border-teal-300 dark:bg-teal-900/40 dark:text-teal-300 dark:border-teal-700'
            : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100 dark:bg-gray-800/50 dark:text-gray-400 dark:border-gray-700 dark:hover:bg-gray-700/50',
        purple: selected
            ? 'bg-purple-100 text-purple-700 border-purple-300 dark:bg-purple-900/40 dark:text-purple-300 dark:border-purple-700'
            : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100 dark:bg-gray-800/50 dark:text-gray-400 dark:border-gray-700 dark:hover:bg-gray-700/50',
        green: selected
            ? 'bg-green-100 text-green-700 border-green-300 dark:bg-green-900/40 dark:text-green-300 dark:border-green-700'
            : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100 dark:bg-gray-800/50 dark:text-gray-400 dark:border-gray-700 dark:hover:bg-gray-700/50',
        gray: selected
            ? 'bg-gray-100 text-gray-700 border-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600'
            : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100 dark:bg-gray-800/50 dark:text-gray-400 dark:border-gray-700 dark:hover:bg-gray-700/50',
    }
    return colors[action] || colors.gray
}

const togglePermission = (permission) => {
    const exists = (props.form.permissions || []).includes(permission)
    if (exists) {
        props.form.permissions = props.form.permissions.filter(p => p !== permission)
        return
    }
    props.form.permissions = [...(props.form.permissions || []), permission]
}

const allSelected = () => {
    return props.permissions?.length > 0 && props.form.permissions?.length === props.permissions?.length
}

const toggleAll = () => {
    if (allSelected()) {
        props.form.permissions = []
    } else {
        props.form.permissions = props.permissions?.map(p => p.name) || []
    }
}

const groupAllSelected = (perms) => {
    return perms?.length > 0 && perms.every(p => props.form.permissions?.includes(p.name))
}

const toggleGroup = (perms) => {
    if (groupAllSelected(perms)) {
        props.form.permissions = props.form.permissions.filter(p => !perms.some(pp => pp.name === p))
    } else {
        for (const p of perms) {
            if (!props.form.permissions?.includes(p.name)) {
                props.form.permissions = [...(props.form.permissions || []), p.name]
            }
        }
    }
}
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">
        <TextInput v-model="form.name" label="Nombre del rol" :error="form.errors.name" />

        <div>
            <div class="flex items-center justify-between mb-4">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Permisos</label>
                <button type="button" @click="toggleAll"
                    class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 transition">
                    {{ allSelected() ? 'Deseleccionar todos' : 'Seleccionar todos' }}
                </button>
            </div>

            <div class="relative mb-4">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input v-model="permissionSearch" type="text" placeholder="Buscar permisos..."
                    class="w-full pl-9 pr-3 py-2 text-sm rounded-xl border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500" />
                <button v-if="permissionSearch" @click="permissionSearch = ''" type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <SearchX class="w-4 h-4" />
                </button>
            </div>

            <div v-if="filteredGroups.length === 0 && permissionSearch" class="text-center py-8 text-gray-400 dark:text-gray-500">
                <SearchX class="w-10 h-10 mx-auto mb-2 opacity-40" />
                <p class="text-sm">No se encontraron permisos para "{{ permissionSearch }}"</p>
            </div>
            <div v-else class="space-y-4">
                <div v-for="group in filteredGroups" :key="group.name"
                    class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <component :is="group.icon" class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ group.name }}</span>
                            <span class="text-xs text-gray-400">({{ group.permissions.length }})</span>
                        </div>
                        <button type="button" @click="toggleGroup(group.permissions)"
                            class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 transition">
                            {{ groupAllSelected(group.permissions) ? 'Deseleccionar' : 'Seleccionar' }}
                        </button>
                    </div>
                    <div class="p-3 flex flex-wrap gap-2">
                        <button type="button" v-for="permission in group.permissions" :key="permission.id"
                            @click="togglePermission(permission.name)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition"
                            :class="colorClass(permission.name, form.permissions?.includes(permission.name))">
                            <ShieldCheck v-if="form.permissions?.includes(permission.name)" class="w-3 h-3" />
                            {{ permission.name }}
                        </button>
                    </div>
                </div>
            </div>

            <p v-if="form.errors.permissions" class="text-red-500 text-sm mt-2">{{ form.errors.permissions }}</p>
        </div>

        <div class="flex justify-end pt-2">
            <PrimaryButton :disabled="form.processing">
                <Shield class="w-4 h-4 mr-1.5" /> {{ submitLabel }}
            </PrimaryButton>
        </div>
    </form>
</template>
