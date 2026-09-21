<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { Youtube, BarChart3, Wand2, Users, ChevronRight, Calendar, FileText, Lightbulb, Shield, Zap, Clock, CheckCircle, Globe, Github, Brain, Share2, Search } from 'lucide-vue-next'

defineProps({ canLogin: Boolean, canRegister: Boolean })

const colorMap = {
    indigo: { bg: 'bg-indigo-500/20', hover: 'group-hover:bg-indigo-500/30', text: 'text-indigo-400' },
    purple: { bg: 'bg-purple-500/20', hover: 'group-hover:bg-purple-500/30', text: 'text-purple-400' },
    emerald: { bg: 'bg-emerald-500/20', hover: 'group-hover:bg-emerald-500/30', text: 'text-emerald-400' },
    amber: { bg: 'bg-amber-500/20', hover: 'group-hover:bg-amber-500/30', text: 'text-amber-400' },
    rose: { bg: 'bg-rose-500/20', hover: 'group-hover:bg-rose-500/30', text: 'text-rose-400' },
    cyan: { bg: 'bg-cyan-500/20', hover: 'group-hover:bg-cyan-500/30', text: 'text-cyan-400' },
    orange: { bg: 'bg-orange-500/20', hover: 'group-hover:bg-orange-500/30', text: 'text-orange-400' },
    teal: { bg: 'bg-teal-500/20', hover: 'group-hover:bg-teal-500/30', text: 'text-teal-400' },
    violet: { bg: 'bg-violet-500/20', hover: 'group-hover:bg-violet-500/30', text: 'text-violet-400' },
}

const features = [
    {
        icon: Calendar,
        title: 'Planificación visual',
        description: 'Calendario mensual y semanal con bloques de tiempo configurables. Gestiona tareas, sesiones multi-día, ausencias y tareas pendientes. Búsqueda integrada para encontrar tareas al instante.',
        color: 'indigo',
    },
    {
        icon: Brain,
        title: 'IA generativa',
        description: 'Genera scripts, copy, frases clave y locuciones con Gemini 2.5 Flash y ElevenLabs. Rotación automática de API keys, prompts optimizados con hook-desarrollo-cierre.',
        color: 'emerald',
    },
    {
        icon: Youtube,
        title: 'Gestión de contenido',
        description: 'Administra videos de idea a publicación. Controla estados: guion, edición, revisión, programación y publicación. Estadísticas de canales vía YouTube API.',
        color: 'rose',
    },
    {
        icon: Lightbulb,
        title: 'Banco de ideas',
        description: 'Captura y organiza ideas por canal. Selección múltiple, edición en masa, importa/exporta TXT, marca las usadas y filtros por estado.',
        color: 'amber',
    },
    {
        icon: BarChart3,
        title: 'Dashboard en tiempo real',
        description: 'KPIs reales con StatCards: tareas, publicaciones, sesiones del día. Gráficos Chart.js, exportación PDF y filtros por período.',
        color: 'purple',
    },
    {
        icon: Share2,
        title: 'Compartir tareas',
        description: 'Comparte tareas con tu equipo asignando roles de editor o lector. Gestión directa desde el calendario o la vista de detalle.',
        color: 'cyan',
    },
    {
        icon: FileText,
        title: 'Reportes PDF',
        description: 'Exporta reportes diarios, semanales, mensuales o anuales. Incluye tareas, sesiones, tareas extra, observaciones y links de YouTube.',
        color: 'orange',
    },
    {
        icon: Users,
        title: 'Multi-empresa y RBAC',
        description: '53 permisos granulares en 16 módulos. Roles por organización, datos aislados. Super Admin puede gestionar todas las empresas.',
        color: 'violet',
    },
    {
        icon: Clock,
        title: 'Tareas extra y sesiones',
        description: 'Registra actividades fuera del flujo: reuniones, capacitaciones. Sesiones de trabajo multi-día con gestión desde el calendario.',
        color: 'teal',
    },
]

const steps = [
    { number: '01', title: 'Crea tu cuenta', description: 'Regístrate y configura tu empresa con canales y bloques de tiempo.' },
    { number: '02', title: 'Planifica contenido', description: 'Crea tareas en el calendario, asígnale horarios y estados de producción.' },
    { number: '03', title: 'Genera con IA', description: 'Usa Gemini para scripts, copy y frases. Exporta reportes y comparte con tu equipo.' },
]
</script>

<template>
    <Head title="GrowthOS" />
    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-indigo-950 to-gray-900">
        <header class="fixed top-0 left-0 right-0 z-50 bg-gray-900/80 backdrop-blur-md border-b border-white/10">
            <div class="flex items-center justify-between px-6 py-4 max-w-7xl mx-auto">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm">G</div>
                    <span class="text-white font-bold text-lg">GrowthOS</span>
                </div>
                <nav class="flex items-center gap-4">
                    <Link v-if="$page.props.auth?.user" :href="route('dashboard')" class="px-4 py-2 rounded-lg bg-white/10 text-white text-sm hover:bg-white/20 transition">Dashboard</Link>
                    <template v-else>
                        <Link :href="route('login')" class="px-4 py-2 text-white/80 text-sm hover:text-white transition">Iniciar sesión</Link>
                        <Link :href="route('register')" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500 transition">Registrarse</Link>
                    </template>
                </nav>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-6">
            <!-- Hero -->
            <section class="pt-24 pb-20 text-center">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-sm mb-8">
                    <Zap class="w-4 h-4" />
                    Plataforma de gestión de contenido audiovisual
                </div>
                <h1 class="text-5xl md:text-7xl font-bold text-white leading-tight">
                    Gestiona tu<br />
                    <span class="bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400 bg-clip-text text-transparent">producción de contenido</span>
                </h1>
                <p class="mt-6 text-lg md:text-xl text-gray-400 max-w-3xl mx-auto leading-relaxed">
                    Planifica, organiza y da seguimiento a tus videos, tareas e ideas.
                    Genera scripts con inteligencia artificial, comparte tareas con tu equipo
                    y exporta reportes detallados — todo en una sola plataforma.
                </p>
                <div class="mt-10 flex items-center justify-center gap-4">
                    <Link :href="route('register')" class="px-8 py-4 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-500 transition flex items-center gap-2 shadow-lg shadow-indigo-600/25 text-lg">
                        Comenzar gratis <ChevronRight class="w-5 h-5" />
                    </Link>
                    <a href="#features" class="px-8 py-4 rounded-xl border border-white/20 text-white/80 font-medium hover:bg-white/5 transition text-lg">
                        Conocer más
                    </a>
                </div>

                <!-- Stats -->
                <div class="mt-16 grid grid-cols-2 md:grid-cols-4 gap-6 max-w-3xl mx-auto">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white">14+</div>
                        <div class="text-sm text-gray-500 mt-1">Módulos</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white">53</div>
                        <div class="text-sm text-gray-500 mt-1">Permisos</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white">24/7</div>
                        <div class="text-sm text-gray-500 mt-1">Disponible</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white">IA</div>
                        <div class="text-sm text-gray-500 mt-1">Integrada</div>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section id="features" class="py-20">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Todo lo que necesitas</h2>
                    <p class="text-gray-400 max-w-2xl mx-auto">Herramientas diseñadas para equipos de contenido que buscan eficiencia, organización y automatización.</p>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="feature in features" :key="feature.title"
                        class="group p-6 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all duration-300 hover:border-white/20">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 transition-colors"
                            :class="[colorMap[feature.color].bg, colorMap[feature.color].hover]">
                            <component :is="feature.icon" class="w-6 h-6" :class="colorMap[feature.color].text" />
                        </div>
                        <h3 class="text-white font-semibold text-lg mb-2">{{ feature.title }}</h3>
                        <p class="text-sm text-gray-400 leading-relaxed">{{ feature.description }}</p>
                    </div>
                </div>
            </section>

            <!-- How it works -->
            <section class="py-20 border-t border-white/10">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">¿Cómo funciona?</h2>
                    <p class="text-gray-400 max-w-2xl mx-auto">Tres pasos simples para empezar a gestionar tu contenido.</p>
                </div>
                <div class="grid md:grid-cols-3 gap-8">
                    <div v-for="step in steps" :key="step.number" class="text-center">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 border border-white/10 flex items-center justify-center mx-auto mb-6">
                            <span class="text-2xl font-bold bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">{{ step.number }}</span>
                        </div>
                        <h3 class="text-white font-semibold text-lg mb-2">{{ step.title }}</h3>
                        <p class="text-sm text-gray-400">{{ step.description }}</p>
                    </div>
                </div>
            </section>

            <!-- Workflow -->
            <section class="py-20 border-t border-white/10">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <h2 class="text-3xl md:text-4xl font-bold text-white mb-6">Flujo de trabajo completo</h2>
                        <p class="text-gray-400 mb-8 leading-relaxed">
                            Desde la captura de una idea hasta la publicación del video, GrowthOS te acompaña en cada paso.
                            Genera scripts con IA, registra tareas extra, gestiona sesiones multi-día y exporta reportes.
                        </p>
                        <ul class="space-y-4">
                            <li class="flex items-start gap-3">
                                <CheckCircle class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" />
                                <span class="text-gray-300">Calendario visual con búsqueda de tareas y bloques configurables</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <CheckCircle class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" />
                                <span class="text-gray-300">Generación de scripts y copy con Gemini 2.5 Flash (rotación multi-key)</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <CheckCircle class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" />
                                <span class="text-gray-300">Compartir tareas con roles de editor y lector</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <CheckCircle class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" />
                                <span class="text-gray-300">Sesiones de trabajo multi-día con edición desde el calendario</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <CheckCircle class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" />
                                <span class="text-gray-300">Tareas pendientes: mueve y restaura tareas con un clic</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <CheckCircle class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" />
                                <span class="text-gray-300">Historial de cambios por tarea con timeline expandible</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <CheckCircle class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" />
                                <span class="text-gray-300">Reportes PDF con logo corporativo y datos detallados</span>
                            </li>
                        </ul>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-0 bg-gradient-to-r from-indigo-500/10 to-purple-500/10 rounded-3xl blur-3xl"></div>
                        <div class="relative bg-white/5 border border-white/10 rounded-3xl p-8 backdrop-blur-sm">
                            <div class="space-y-4">
                                <div class="flex items-center gap-3 p-3 rounded-xl bg-white/5">
                                    <div class="w-10 h-10 rounded-lg bg-indigo-500/20 flex items-center justify-center">
                                        <Calendar class="w-5 h-5 text-indigo-400" />
                                    </div>
                                    <div>
                                        <div class="text-white font-medium text-sm">Lunes 15</div>
                                        <div class="text-gray-500 text-xs">3 tareas programadas</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 rounded-xl bg-white/5">
                                    <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                                        <Brain class="w-5 h-5 text-emerald-400" />
                                    </div>
                                    <div>
                                        <div class="text-white font-medium text-sm">Script generado con IA</div>
                                        <div class="text-gray-500 text-xs">Gemini 2.5 Flash</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 rounded-xl bg-white/5">
                                    <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center">
                                        <Share2 class="w-5 h-5 text-purple-400" />
                                    </div>
                                    <div>
                                        <div class="text-white font-medium text-sm">Tarea compartida</div>
                                        <div class="text-gray-500 text-xs">Rol: Editor</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 rounded-xl bg-white/5">
                                    <div class="w-10 h-10 rounded-lg bg-rose-500/20 flex items-center justify-center">
                                        <Youtube class="w-5 h-5 text-rose-400" />
                                    </div>
                                    <div>
                                        <div class="text-white font-medium text-sm">Video publicado</div>
                                        <div class="text-gray-500 text-xs">YouTube &bull; Canal Principal</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="py-20 text-center border-t border-white/10">
                <div class="max-w-2xl mx-auto">
                    <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">¿Listo para optimizar tu producción?</h2>
                    <p class="text-gray-400 mb-8">Únete a equipos que ya están gestionando su contenido de forma más eficiente con IA integrada.</p>
                    <Link :href="route('register')" class="px-8 py-4 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-500 transition inline-flex items-center gap-2 shadow-lg shadow-indigo-600/25 text-lg">
                        Crear cuenta <ChevronRight class="w-5 h-5" />
                    </Link>
                </div>
            </section>

            <!-- About -->
            <section class="py-20 border-t border-white/10">
                <div class="max-w-4xl mx-auto">
                    <div class="grid lg:grid-cols-2 gap-12 items-center">
                        <div>
                            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 text-gray-400 text-sm mb-6">
                                <Github class="w-4 h-4" />
                                Código abierto
                            </div>
                            <h2 class="text-3xl md:text-4xl font-bold text-white mb-6">¿Por qué GrowthOS?</h2>
                            <p class="text-gray-400 leading-relaxed mb-6">
                                GrowthOS nació de la necesidad de centralizar toda la producción de contenido audiovisual en una sola plataforma.
                                Como creadores de contenido, sabemos lo complicado que es gestionar múltiples canales, coordinar equipos
                                y mantener un flujo de trabajo eficiente.
                            </p>
                            <p class="text-gray-400 leading-relaxed mb-8">
                                Esta plataforma fue diseñada para resolver problemas reales: planificar contenido de forma visual,
                                generar scripts con IA, gestionar ideas por canal y tener reportes detallados de productividad.
                            </p>
                            <div class="flex items-center gap-4">
                                <a href="https://github.com/scorvachoa/GrowthOs-Laravel" target="_blank"
                                    class="px-6 py-3 rounded-xl bg-white/10 border border-white/20 text-white font-medium hover:bg-white/20 transition flex items-center gap-2">
                                    <Github class="w-5 h-5" />
                                    Ver en GitHub
                                </a>
                            </div>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-0 bg-gradient-to-r from-indigo-500/10 to-purple-500/10 rounded-3xl blur-3xl"></div>
                            <div class="relative bg-white/5 border border-white/10 rounded-3xl p-8 backdrop-blur-sm">
                                <div class="space-y-6">
                                    <div class="flex items-start gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-indigo-500/20 flex items-center justify-center flex-shrink-0">
                                            <Zap class="w-5 h-5 text-indigo-400" />
                                        </div>
                                        <div>
                                            <h4 class="text-white font-semibold mb-1">Laravel 12 + Vue 3</h4>
                                            <p class="text-sm text-gray-400">Backend robusto con Inertia.js para una SPA sin API REST duplicada</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center flex-shrink-0">
                                            <Brain class="w-5 h-5 text-emerald-400" />
                                        </div>
                                        <div>
                                            <h4 class="text-white font-semibold mb-1">IA con rotación multi-key</h4>
                                            <p class="text-sm text-gray-400">Gemini 2.5 Flash con hasta 5 API keys y ElevenLabs para locuciones</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center flex-shrink-0">
                                            <Shield class="w-5 h-5 text-purple-400" />
                                        </div>
                                        <div>
                                            <h4 class="text-white font-semibold mb-1">Multi-organización</h4>
                                            <p class="text-sm text-gray-400">53 permisos granulares, 3 roles por defecto, datos aislados por empresa</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/10 py-8 text-center text-sm text-gray-500">
            <p>GrowthOS &mdash; Plataforma de gestión de contenido audiovisual</p>
            <p class="mt-2">Creado por <a href="https://github.com/scorvachoa" target="_blank" class="text-indigo-400 hover:text-indigo-300 transition">scorvachoa</a></p>
            <p class="mt-1">Licencia Elastic License 2.0 — Uso gratuito, prohibido ofrecer como servicio</p>
        </footer>
    </div>
</template>
