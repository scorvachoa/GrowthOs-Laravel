<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    LayoutDashboard, Users, Shield, CalendarDays, ClipboardList,
    Lightbulb, FileClock, Youtube, Settings, Building2, Sparkles,
    BookOpen, ChevronRight, ArrowRight, Umbrella, CalendarClock, HardDrive
} from 'lucide-vue-next'

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
        ],
    },
    {
        id: 'tasks',
        icon: ClipboardList,
        title: 'Tareas',
        content: [
            'Historial completo de tareas de video con filtros por fecha, estado, canal y usuario.',
            'Cada tarea tiene: título, script, copy, estado, canal asignado, rango horario y enlace a YouTube.',
            'Estados: Pendiente, Script Listo, Editando, Revisión, Programado, Publicado, Cancelado.',
            'Las tareas pueden tener sesiones de trabajo (días de continuación) con sus propios rangos horarios y estados. Las sesiones se gestionan desde la vista de detalle de la tarea.',
            'Soporta traducciones por idioma: cada tarea puede tener título, script, copy y youtube_url en varios idiomas. Al ver una tarea, solo se muestran las pestañas de idiomas que tienen contenido.',
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
    'Usa el panel lateral izquierdo para navegar entre las secciones. El orden sigue el flujo de trabajo: Dashboard, Planificación, Tareas, YouTube, Ideas, IA, Historial, Usuarios, Roles, Vacaciones, Permisos, Empresa, Configuración.',
    'El topbar superior muestra tu empresa activa, el botón de Backup y tu perfil. Los Super Admins pueden cambiar de empresa desde el nombre de la empresa.',
    'Los permisos determinan qué secciones y acciones están disponibles. Contacta a un Super Admin si necesitas acceso a algo.',
    'Todas las acciones importantes muestran notificaciones de éxito/error en la parte superior de la pantalla.',
]
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-8">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900">
                    <BookOpen class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Manual de Usuario</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Guía completa de uso del sistema GrowthOS</p>
                </div>
            </div>

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
                <div v-for="section in sections" :key="section.id"
                    class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
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
    </AppLayout>
</template>
