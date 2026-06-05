<script setup>
import { computed } from 'vue'
import TextInput from '@/Components/Forms/TextInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import { Shield, ShieldCheck, LayoutDashboard, Users, Shield as ShieldIcon, ClipboardList, BarChart3, Youtube, Sparkles, Lightbulb, Building2, Download, Eye, FileDown, Upload, Settings } from 'lucide-vue-next'

const props = defineProps({
    form: Object,
    permissions: Array,
    submitLabel: { type: String, default: 'Guardar' },
})

const emit = defineEmits(['submit'])

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
        'generate ai': 'AI Generator',
        'view ai history': 'AI Generator',
        'download ai': 'AI Generator',
        'view empresa': 'Empresa',
        'create empresa': 'Empresa',
        'edit empresa': 'Empresa',
        'delete empresa': 'Empresa',

        'view configuracion': 'Configuracion',
        'edit configuracion': 'Configuracion',
    }

    const groupOrder = ['Dashboard', 'Usuarios', 'Roles', 'Planificación', 'Tareas', 'Ideas', 'Reportes', 'YouTube', 'AI Generator', 'Empresa', 'Configuracion']
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

            <div class="space-y-4">
                <div v-for="group in groups" :key="group.name"
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
                    <div class="p-3 grid grid-cols-1 md:grid-cols-2 gap-2">
                        <label v-for="permission in group.permissions" :key="permission.id"
                            class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 p-3 rounded-xl cursor-pointer border border-gray-100 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600 transition"
                            :class="{ 'border-indigo-400 dark:border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20': form.permissions?.includes(permission.name) }">
                            <input type="checkbox" :checked="form.permissions?.includes(permission.name)"
                                @change="togglePermission(permission.name)" class="sr-only" />
                            <div class="w-5 h-5 rounded-md border-2 flex items-center justify-center transition flex-shrink-0"
                                :class="form.permissions?.includes(permission.name)
                                    ? 'bg-indigo-600 border-indigo-600'
                                    : 'border-gray-300 dark:border-gray-600'">
                                <ShieldCheck v-if="form.permissions?.includes(permission.name)" class="w-3.5 h-3.5 text-white" />
                            </div>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ permission.name }}</span>
                        </label>
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
