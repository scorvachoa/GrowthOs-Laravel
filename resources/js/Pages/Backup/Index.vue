<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import ConfirmDelete from '@/Components/Modals/ConfirmDelete.vue'
import { Download, Upload, HardDrive, Database, AlertTriangle, Clock, Trash2 } from 'lucide-vue-next'

const page = usePage()

const props = defineProps({
    table_sizes: Object,
    is_super_admin: Boolean,
    scheduled_backups: Array,
    backup_schedule: Object,
    companies: Array,
    current_scope: String,
})

const processing = ref(false)
const restoring = ref(false)
const fileInput = ref(null)
const selectedFile = ref(null)
const showRestoreConfirm = ref(false)
const showDeleteConfirm = ref(false)
const deletingFilename = ref('')

const totalRecords = computed(() =>
    Object.values(props.table_sizes || {}).reduce((a, b) => a + b, 0)
)

const can = (perm) => page.props.auth?.user?.permissions?.includes(perm)

const dayLabels = {
    monday: 'los lunes', tuesday: 'los martes', wednesday: 'los miércoles',
    thursday: 'los jueves', friday: 'los viernes', saturday: 'los sábados', sunday: 'los domingos',
}

const tableLabels = {
    organizations: 'Empresas', users: 'Usuarios', roles: 'Roles', permissions: 'Permisos',
    channels: 'Canales', video_tasks: 'Tareas de video', extra_tasks: 'Tareas extra',
    ideas: 'Ideas', generated_videos: 'Videos generados', report_histories: 'Reportes',
    day_observations: 'Observaciones', vacations: 'Vacaciones', work_sessions: 'Sesiones de trabajo',
    time_offs: 'Permisos', role_has_permissions: 'Permisos por rol', model_has_roles: 'Roles por usuario',
    model_has_permissions: 'Permisos por usuario',
}

function changeScope(scope) {
    router.get(route('backup.index', { scope }), {}, { preserveState: false, preserveScroll: true })
}

function exportBackup() {
    processing.value = true
    const scope = props.current_scope || 'all'
    window.location.href = route('backup.export', { scope })
    setTimeout(() => { processing.value = false }, 3000)
}

function handleFileSelect(e) {
    const file = e.target.files[0]
    if (file) {
        selectedFile.value = file
        showRestoreConfirm.value = true
    }
}

function confirmRestore() {
    showRestoreConfirm.value = false
    if (!selectedFile.value) return
    restoring.value = true
    const form = new FormData()
    form.append('backup_file', selectedFile.value)

    router.post(route('backup.import'), form, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            restoring.value = false
            selectedFile.value = null
            showRestoreConfirm.value = false
            if (fileInput.value) fileInput.value.value = ''
        },
    })
}

function cancelRestore() {
    showRestoreConfirm.value = false
    selectedFile.value = null
    if (fileInput.value) fileInput.value.value = ''
}

function downloadScheduled(filename) {
    window.open(route('backup.scheduled.download', filename), '_blank')
}

function deleteScheduled(filename) {
    deletingFilename.value = filename
    showDeleteConfirm.value = true
}

function confirmDelete() {
    const filename = deletingFilename.value
    showDeleteConfirm.value = false
    deletingFilename.value = ''
    if (!filename) return
    router.delete(route('backup.scheduled.delete', filename), {
        preserveState: true,
        preserveScroll: true,
    })
}
</script>

<template>
    <AppLayout title="Backups">
        <div class="space-y-8">

            <!-- Header -->
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center shrink-0">
                    <HardDrive class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Backup de datos</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ is_super_admin ? 'Respaldo global del sistema' : 'Respaldo de los datos de tu empresa' }}
                    </p>
                </div>
                <div v-if="is_super_admin && companies?.length" class="ml-auto">
                    <select :value="current_scope" @change="changeScope($event.target.value)"
                        class="rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm font-medium py-2 pl-3 pr-8">
                        <option value="all">Todas las empresas</option>
                        <option v-for="c in companies" :key="c.id" :value="'org_' + c.id">{{ c.name }}</option>
                    </select>
                </div>
                <span v-else
                    class="ml-auto px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-xs font-semibold text-emerald-700 dark:text-emerald-300 shrink-0">
                    Mi empresa
                </span>
            </div>

            <!-- Stats -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <Database class="w-4 h-4 text-indigo-500" />
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Registros en el sistema</h3>
                    <span class="text-xs text-gray-400 ml-auto">{{ totalRecords }} total</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                    <div v-for="(count, table) in table_sizes" :key="table"
                        class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2 text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ tableLabels[table] || table.replace(/_/g, ' ') }}</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ count }}</p>
                    </div>
                </div>
            </div>

            <!-- Export + Restore side by side on desktop -->
            <div class="grid md:grid-cols-2 gap-6">

                <!-- Export manual -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center shrink-0">
                                <Download class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Exportar backup</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    Descarga un archivo JSON con todos los datos actuales del sistema.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <PrimaryButton @click="exportBackup" :disabled="processing" class="w-full justify-center">
                                <Download class="w-4 h-4 mr-2" />
                                {{ processing ? 'Generando...' : 'Descargar backup' }}
                            </PrimaryButton>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-3 text-center">
                            Scope: {{ current_scope === 'all' ? 'Todas las empresas' : 'Empresa actual' }}
                        </p>
                    </div>
                </div>

                <!-- Restore -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                                <Upload class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Restaurar backup</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    Selecciona un archivo JSON exportado desde este sistema.
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 flex items-start gap-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                            <AlertTriangle class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                            <p class="text-xs text-amber-700 dark:text-amber-300">
                                Se sobrescribirán los registros existentes. Los usuarios existentes NO se eliminarán, solo se actualizarán.
                            </p>
                        </div>

                        <div class="mt-4">
                            <label
                                class="flex items-center gap-3 px-4 py-3 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 cursor-pointer hover:border-indigo-400 dark:hover:border-indigo-500 transition group">
                                <Upload class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition" />
                                <span class="text-sm text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition truncate">
                                    {{ selectedFile ? selectedFile.name : 'Seleccionar archivo JSON...' }}
                                </span>
                                <input ref="fileInput" type="file" accept=".json" @change="handleFileSelect" class="hidden" />
                            </label>
                        </div>

                        <!-- Restore confirmation -->
                        <div v-if="showRestoreConfirm" class="mt-4 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                            <div class="flex items-start gap-3">
                                <AlertTriangle class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-red-800 dark:text-red-200">Confirmar restauración</p>
                                    <p class="text-xs text-red-600 dark:text-red-400 mt-1">
                                        Se importarán los datos de <strong>{{ selectedFile?.name }}</strong>. Esta acción no se puede deshacer.
                                    </p>
                                    <div class="flex gap-2 mt-3">
                                        <PrimaryButton @click="confirmRestore" :disabled="restoring"
                                            class="!bg-red-600 hover:!bg-red-700 text-white text-xs px-3 py-1.5">
                                            {{ restoring ? 'Restaurando...' : 'Si, restaurar' }}
                                        </PrimaryButton>
                                        <button @click="cancelRestore"
                                            class="text-xs px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="restoring" class="mt-4 flex items-center gap-2 text-sm text-indigo-600 dark:text-indigo-400">
                            <div class="animate-spin w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full"></div>
                            Restaurando datos...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scheduled backups -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <Clock class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Backups guardados</h3>
                    <span v-if="scheduled_backups?.length" class="text-xs text-gray-400">({{ scheduled_backups.length }})</span>
                </div>

                <div v-if="!scheduled_backups?.length" class="px-6 py-10 text-center">
                    <Clock class="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">No hay backups guardados aun.</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Exporta un backup o espera a que se genere uno programado.</p>
                </div>

                <div v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div v-for="b in scheduled_backups" :key="b.filename"
                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ b.filename }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ b.created_at }} &middot; {{ b.size_formatted }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0 ml-4">
                            <button @click="downloadScheduled(b.filename)"
                                class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400"
                                title="Descargar">
                                <Download class="w-4 h-4" />
                            </button>
                            <button v-if="can('create backup')" @click="deleteScheduled(b.filename)"
                                class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition text-gray-500 hover:text-red-600 dark:hover:text-red-400"
                                title="Eliminar">
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info: schedule -->
            <div v-if="can('configure backup')"
                class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-xl p-4 text-sm text-indigo-700 dark:text-indigo-300 flex items-start gap-3">
                <Clock class="w-5 h-5 shrink-0 mt-0.5" />
                <div>
                    <p class="font-medium">Backup automático programado</p>
                    <p class="mt-0.5 opacity-80">El sistema genera un backup completo {{ dayLabels[props.backup_schedule?.day] || 'los domingos' }} a las {{ props.backup_schedule?.time || '03:00' }}. Los archivos se almacenan en el servidor y puedes descargarlos desde aquí.</p>
                </div>
            </div>
        </div>

        <ConfirmDelete
            :show="showDeleteConfirm"
            title="Eliminar backup"
            message="Este backup se eliminará permanentemente del servidor."
            @confirm="confirmDelete"
            @close="showDeleteConfirm = false"
        />
    </AppLayout>
</template>
