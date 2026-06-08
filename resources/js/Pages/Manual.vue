<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    LayoutDashboard, Users, Shield, CalendarDays, ClipboardList,
    Lightbulb, FileClock, Youtube, Settings, Building2, Sparkles,
    BookOpen, ChevronRight, ArrowRight
} from 'lucide-vue-next'

const sections = [
    {
        id: 'dashboard',
        icon: LayoutDashboard,
        title: 'Dashboard',
        content: [
            'Resumen visual del rendimiento del equipo. Muestra metricas clave como tareas completadas, videos programados, y actividad reciente.',
            'Los graficos se actualizan en tiempo real al cambiar de mes o rango de fechas. Usa los filtros superiores para ajustar la vista.',
        ],
    },
    {
        id: 'ai-generator',
        icon: Sparkles,
        title: 'AI Generator',
        content: [
            'Genera guiones, copys y frases para videos usando inteligencia artificial (Gemini).',
            'Escribe una idea en el campo de texto y haz clic en "Generar Guion". El sistema creara un guion optimizado para YouTube Shorts (45-60s).',
            'Luego puedes generar Copy (titulo, descripcion, CTA, hashtags, tags SEO) y Frases (para overlays en pantalla).',
            'Usa el boton "Exportar TXT" para descargar todo el contenido generado o "Usar en Planificador" para crear una tarea en el calendario.',
            'El historial guarda tus ultimas 5 generaciones. Accede a "Historial" para ver todas.',
        ],
    },
    {
        id: 'users',
        icon: Users,
        title: 'Usuarios',
        content: [
            'Gestion de usuarios del sistema. Puedes crear, editar y eliminar usuarios.',
            'Cada usuario tiene un rol que determina sus permisos. Los Super Admins tienen acceso global.',
            'Los usuarios regulares estan vinculados a una empresa y solo ven datos de esa empresa.',
        ],
    },
    {
        id: 'roles',
        icon: Shield,
        title: 'Roles y Permisos',
        content: [
            'Define roles con permisos especificos para controlar el acceso a cada seccion del sistema.',
            'Los permisos disponibles incluyen: ver/crear/editar/eliminar para cada modulo (Dashboard, Usuarios, Roles, Planificacion, Tareas, Ideas, Reportes, YouTube, AI, Empresa, Configuracion).',
            'El rol "Super Admin" tiene todos los permisos y no puede ser modificado desde esta pantalla.',
            'Puedes seleccionar permisos individuales o por grupo. Usa el buscador para filtrar permisos rapidamente.',
        ],
    },
    {
        id: 'planning',
        icon: CalendarDays,
        title: 'Planificacion',
        content: [
            'Calendario mensual y semanal para planificar tareas de video y tareas extra.',
            'Vista mensual: cada dia muestra barras apiladas (indigo = tareas de video, amber = tareas extra). Haz clic en un dia para ver el detalle en el panel lateral.',
            'Vista semanal: grilla horaria con las tareas ubicadas segun su hora de inicio y duracion. Las tareas que se superponen se muestran lado a lado.',
            'Las tareas extra aparecen con borde punteado. Los feriados se muestran en rojo.',
            'Usa los botones "<" y ">" para navegar entre meses/semanas. Exporta a PDF con el boton de descarga.',
        ],
    },
    {
        id: 'tasks',
        icon: ClipboardList,
        title: 'Tareas',
        content: [
            'Historial completo de tareas de video con filtros por fecha, estado, canal y usuario.',
            'Cada tarea tiene: titulo, script, copy, estado, canal asignado, rango horario y enlace a YouTube.',
            'Los estados disponibles son: Pendiente, Script Listo, Editando, Revision, Programado, Publicado, Cancelado.',
        ],
    },
    {
        id: 'ideas',
        icon: Lightbulb,
        title: 'Ideas',
        content: [
            'Banco de ideas para videos. Puedes crear, editar, importar y exportar ideas.',
            'Cada idea tiene un titulo, descripcion y estado. Las ideas se pueden convertir directamente en tareas desde el planificador.',
        ],
    },
    {
        id: 'history',
        icon: FileClock,
        title: 'Historial de Reportes',
        content: [
            'Registro de todos los reportes generados y descargados. Incluye reportes diarios, semanales y mensuales.',
            'Cada entrada muestra el tipo de reporte, fecha de generacion, filtros aplicados y el archivo PDF.',
        ],
    },
    {
        id: 'youtube',
        icon: Youtube,
        title: 'YouTube',
        content: [
            'Gestion de canales de YouTube vinculados. Muestra los videos de cada canal con su estado de publicacion.',
            'Los canales se sincronizan automaticamente con la API de YouTube. Puedes ver estadisticas y detalles de cada video.',
        ],
    },
    {
        id: 'config',
        icon: Settings,
        title: 'Configuracion',
        content: [
            'Configuracion global del sistema para cada usuario.',
            'Horario laboral: define tu hora de inicio y fin de jornada, duracion de bloques (1h, 2h), y si usas bloques fijos o personalizados.',
            'Dias laborables: selecciona los dias de la semana que trabajas. Los dias no laborables se marcan en el calendario.',
            'Todas las configuraciones se aplican globalmente en el planificador, AI Generator y demas modulos.',
        ],
    },
    {
        id: 'company',
        icon: Building2,
        title: 'Empresa',
        content: [
            'Gestion de la empresa/organizacion. Configura el nombre, color primario y otros datos de tu organizacion.',
            'Los usuarios regulares solo ven los datos de su empresa. Los Super Admins pueden cambiar entre empresas.',
        ],
    },
]

const generalTips = [
    'Usa el panel lateral izquierdo para navegar entre las secciones. Puedes colapsarlo con el boton "<" en la parte inferior.',
    'El topbar superior muestra tu empresa activa y tu perfil. Los Super Admins pueden cambiar de empresa desde ahi.',
    'Los permisos determinan que secciones y acciones estan disponibles para cada usuario. Contacta a un Super Admin si necesitas acceder a algo.',
    'Todas las acciones importantes muestran notificaciones de exito/error en la parte superior de la pantalla.',
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
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Guia completa de uso del sistema GrowthOS</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">Introduccion</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                    GrowthOS es un sistema de gestion de contenido para creadores de videos y equipos de marketing.
                    Permite planificar, generar y dar seguimiento a la produccion de videos de principio a fin,
                    integrando inteligencia artificial para la creacion de guiones y copys.
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
