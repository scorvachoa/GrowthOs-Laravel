<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    LayoutDashboard, Users, Shield, CalendarDays,
    Lightbulb, FileClock, Youtube, Settings, Building2, Sparkles,
    BookOpen, ChevronRight, ArrowRight, Umbrella, CalendarClock, HardDrive
} from 'lucide-vue-next'

const activeSection = ref('')
const scrollContainer = ref(null)

const sections = [
    {
        id: 'dashboard',
        icon: LayoutDashboard,
        title: 'Dashboard',
        content: [
            'Resumen visual del rendimiento del equipo. Muestra métricas clave como tareas completadas, videos programados, y actividad reciente.',
            'Las cards se adaptan según los permisos del usuario: si no tienes permiso "view users", las secciones de usuarios se ocultan automáticamente sin dejar espacios vacíos.',
            'Incluye un gráfico de rendimiento circular con el porcentaje de completitud del periodo y las tareas del día.',
        ],
    },
    {
        id: 'planning',
        icon: CalendarDays,
        title: 'Planificación',
        content: [
            'Calendario mensual y semanal para planificar tareas de video y tareas extra.',
            'Vista mensual: cada día muestra barras apiladas (indigo = tareas de video, amber = tareas extra). Las tareas extra se muestran como barras individuales una al lado de la otra para ver cuántas hay.',
            'Vista semanal: grilla horaria con las tareas ubicadas según su hora de inicio y duración. Las tareas superpuestas se muestran lado a lado.',
            'Las sesiones de trabajo (días de continuación de una tarea) aparecen en el calendario semanal aunque la tarea original esté en otra semana.',
            'Las tareas extra aparecen con borde punteado (teal en oficina, naranja fuera de oficina). Los feriados se muestran en rojo.',
            'Tareas pendientes: las tareas que no están listas para programar se pueden mover a "Pendientes" usando el icono de reloj en el sidebar del día. Estas tareas desaparecen del calendario y se muestran en la vista dedicada de Pendientes.',
            'Vista Pendientes: accede desde el botón "Pendientes" en la barra de navegación del calendario. Muestra todas las tareas pendientes en formato de tarjetas (4 columnas en desktop).',
            'Restaurar tarea pendiente: haz clic en "Restaurar" para seleccionar una nueva fecha y bloque horario. La validación verifica que el bloque esté libre y que sea un día laborable.',
            'Compartir desde el sidebar: cada tarea tiene un icono de compartir que abre un modal para seleccionar usuarios y asignar roles (editor/lector).',
            'Gestionar sesiones desde el sidebar: las sesiones muestran botones de editar (modal inline) y eliminar directamente desde el calendario.',
        ],
    },
    {
        id: 'youtube',
        icon: Youtube,
        title: 'YouTube',
        content: [
            'Gestión de canales de YouTube vinculados. Muestra los videos de cada canal con su estado de publicación.',
            'Gráfico de línea con Chart.js que muestra la tendencia de publicaciones en el tiempo, con soporte para modo oscuro.',
            'Los canales se sincronizan con la API de YouTube. Puedes ver estadísticas y detalles de cada video.',
        ],
    },
    {
        id: 'ideas',
        icon: Lightbulb,
        title: 'Ideas',
        content: [
            'Banco de ideas para videos. Puedes crear, editar, importar y exportar ideas.',
            'Cada idea tiene contenido, canal sugerido, etiquetas y prioridad. Las ideas se pueden marcar como usadas y filtrar por canal.',
        ],
    },
    {
        id: 'ai-generator',
        icon: Sparkles,
        title: 'Generador IA',
        content: [
            'Genera guiones, copys y frases para videos usando inteligencia artificial (Gemini).',
            'Escribe una idea y genera un guion optimizado para YouTube Shorts (45-60s). Luego puedes generar Copy (título, descripción, CTA, hashtags, tags SEO) y Frases.',
            'Usa "Exportar TXT" para descargar todo o "Usar en Planificador" para crear una tarea en el calendario.',
            'El historial guarda las últimas 5 generaciones. Accede a "Historial" desde el botón en la parte superior.',
        ],
    },
    {
        id: 'history',
        icon: FileClock,
        title: 'Historial de Reportes',
        content: [
            'Registro de todos los reportes generados y descargados. Incluye reportes diarios, semanales, mensuales y anuales.',
            'Cada entrada muestra el tipo de reporte, fecha de generación, filtros aplicados y el archivo PDF para descargar.',
        ],
    },
    {
        id: 'users',
        icon: Users,
        title: 'Usuarios',
        content: [
            'Gestión de usuarios del sistema. Puedes crear, editar y eliminar usuarios.',
            'Cada usuario tiene un rol que determina sus permisos. Los Super Admins tienen acceso global y pueden cambiar entre empresas.',
            'Los usuarios regulares están vinculados a una empresa y solo ven datos de esa empresa.',
        ],
    },
    {
        id: 'roles',
        icon: Shield,
        title: 'Roles y Permisos',
        content: [
            'Define roles con permisos específicos para controlar el acceso a cada sección del sistema.',
            'Los permisos disponibles incluyen: ver/crear/editar/eliminar para cada modulo.',
            'El rol "Super Admin" tiene todos los permisos (organization_id = null, alcance global).',
            'Los permisos de configuración son granulares: "configure work hours", "configure youtube", "configure dashboard", "configure backup".',
        ],
    },
    {
        id: 'vacations',
        icon: Umbrella,
        title: 'Vacaciones',
        content: [
            'Gestión de solicitudes de vacaciones. Los usuarios pueden solicitar vacaciones y los administradores pueden aprobar o rechazar.',
            'Las vacaciones aprobadas se muestran en el calendario de planificación como ausencias.',
            'Usa el icono de ojo para ver los detalles completos de una solicitud en un modal.',
        ],
    },
    {
        id: 'timeoff',
        icon: CalendarClock,
        title: 'Permisos',
        content: [
            'Solicitud de permisos personales, médicos, trámites u otros. Incluye hora de inicio y fin opcional.',
            'Puedes marcar "Todo el dia" para que tome automaticamente el horario laboral configurado (inicio y fin de jornada).',
            'Los administradores pueden aprobar o rechazar solicitudes desde la misma pantalla.',
            'Usa el icono de ojo para ver los detalles completos de una solicitud en un modal.',
        ],
    },
    {
        id: 'company',
        icon: Building2,
        title: 'Empresa',
        content: [
            'Gestión de la empresa/organización. Configura el nombre, color primario, logo y canales de YouTube.',
            'Los usuarios regulares solo ven los datos de su empresa. Los Super Admins pueden cambiar entre empresas desde el topbar.',
            'Al iniciar sesión como Super Admin sin empresa activa, se muestra un selector de empresa.',
        ],
    },
    {
        id: 'config',
        icon: Settings,
        title: 'Configuración',
        content: [
            'Configuración global del sistema para cada usuario. Cada sección se muestra según el permiso del usuario.',
            'Horario laboral: define hora de inicio y fin de jornada, duración de bloques (1h, 2h), días laborables y bloques fijos o personalizados.',
            'YouTube: configuración del gráfico y máximo de videos recientes a mostrar.',
            'Dashboard: vista por defecto (semana/mes/año) y alcance de reporte predeterminado.',
            'Idiomas: configura los idiomas disponibles para traducir título, script, copy y enlace de YouTube en las tareas de video. El español siempre está presente.',
            'Backup: programación de backup automático (hora y día de la semana). Solo visible con permiso "configure backup".',
        ],
    },
    {
        id: 'backup',
        icon: HardDrive,
        title: 'Backup',
        content: [
            'Respaldo y restauración de datos. Accesible desde el icono de disco en el topbar.',
            'Exporta todas las tablas del sistema (incluyendo sesiones de trabajo y permisos) a un archivo JSON descargable, con soporte para grandes volúmenes de datos mediante streaming y chunking.',
            'Restauración: sube un archivo JSON de backup previo para restaurar los datos. El proceso valida que los datos correspondan a la organización correcta.',
            'Backups programados: se generan automáticamente según la configuración de horario en Ajustes. Descarga o elimina backups desde la misma página.',
        ],
    },
]

const generalTips = [
    'Usa el panel lateral izquierdo para navegar entre las secciones. El orden sigue el flujo de trabajo: Panel, Planificación, YouTube, Ideas, Generador IA, Historial, Usuarios, Roles, Vacaciones, Permisos, Empresa, Configuración, Manual.',
    'El topbar superior muestra tu empresa activa, el botón de Backup y tu perfil. Los Super Admins pueden cambiar de empresa desde el nombre de la empresa.',
    'Los permisos determinan qué secciones y acciones están disponibles. Contacta a un Super Admin si necesitas acceso a algo.',
    'Todas las acciones importantes muestran notificaciones de éxito/error en la parte superior de la pantalla.',
]

function scrollTo(id) {
    const el = document.getElementById(id)
    if (el && scrollContainer.value) {
        const containerTop = scrollContainer.value.getBoundingClientRect().top
        const elTop = el.getBoundingClientRect().top
        const offset = elTop - containerTop + scrollContainer.value.scrollTop - 16
        scrollContainer.value.scrollTo({ top: offset, behavior: 'smooth' })
    }
}

function handleScroll() {
    if (!scrollContainer.value) return
    const containerTop = scrollContainer.value.getBoundingClientRect().top
    const offsets = sections.map(s => {
        const el = document.getElementById(s.id)
        if (!el) return null
        const rect = el.getBoundingClientRect()
        const relativeTop = rect.top - containerTop
        return { id: s.id, top: relativeTop }
    }).filter(Boolean)

    const current = offsets.find(o => o.top > -50 && o.top < 250)
    if (current) {
        activeSection.value = current.id
    }
}

onMounted(() => {
    scrollContainer.value?.addEventListener('scroll', handleScroll, { passive: true })
    handleScroll()
})

onUnmounted(() => {
    scrollContainer.value?.removeEventListener('scroll', handleScroll)
})
</script>

<template>
    <AppLayout>
        <div class="flex flex-col h-[calc(100vh-8rem)] sm:h-[calc(100vh-9.5rem)] w-full">
            <div class="flex items-center gap-3 mb-6 shrink-0">
                <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900">
                    <BookOpen class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Manual de Usuario</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Guía completa de uso del sistema GrowthOS</p>
                </div>
            </div>

            <div class="flex gap-8 flex-1 min-h-0 w-full">
                <nav class="hidden lg:block w-56 shrink-0">
                    <div class="sticky top-2 space-y-1 max-h-[calc(100vh-12rem)] overflow-y-auto">
                        <button v-for="section in sections" :key="section.id"
                            @click="scrollTo(section.id)"
                            class="w-full flex items-center gap-2 px-3 py-2 text-sm rounded-lg transition text-left"
                            :class="activeSection === section.id
                                ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-medium'
                                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'">
                            <component :is="section.icon" class="w-4 h-4 shrink-0" />
                            <span class="truncate">{{ section.title }}</span>
                        </button>
                    </div>
                </nav>

                <div ref="scrollContainer" class="flex-1 space-y-6 min-w-0 overflow-y-auto hide-scrollbar pr-1">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">Introducción</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            GrowthOS es un sistema de gestión de contenido para creadores de videos y equipos de marketing.
                            Permite planificar, generar y dar seguimiento a la producción de videos de principio a fin,
                            integrando inteligencia artificial para la creación de guiones y copys.
                        </p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">Consejos Generales</h2>
                        <ul class="space-y-2">
                            <li v-for="(tip, i) in generalTips" :key="i" class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <ArrowRight class="w-4 h-4 mt-0.5 text-indigo-500 shrink-0" />
                                <span>{{ tip }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-4">
                        <div v-for="section in sections" :key="section.id" :id="section.id"
                            class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden scroll-mt-4">
                            <div class="flex items-center gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700">
                                <component :is="section.icon" class="w-5 h-5 text-indigo-500" />
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ section.title }}</h2>
                            </div>
                            <div class="px-6 py-4 space-y-3">
                                <p v-for="(paragraph, pi) in section.content" :key="pi"
                                    class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed"
                                    :class="{ 'ml-4 border-l-2 border-indigo-200 dark:border-indigo-800 pl-4': pi > 0 }">
                                    {{ paragraph }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            ¿Necesitas ayuda adicional? Contacta al administrador del sistema.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
