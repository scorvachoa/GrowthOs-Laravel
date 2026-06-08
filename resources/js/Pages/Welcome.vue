<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { Youtube, BarChart3, Wand2, Users, Settings, ChevronRight } from 'lucide-vue-next'

defineProps({ canLogin: Boolean, canRegister: Boolean })
</script>

<template>
    <Head title="GrowthOS" />
    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-indigo-950 to-gray-900">
        <header class="flex items-center justify-between px-6 py-5 max-w-7xl mx-auto">
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
        </header>

        <main class="max-w-7xl mx-auto px-6">
            <section class="pt-24 pb-16 text-center">
                <h1 class="text-5xl md:text-6xl font-bold text-white leading-tight">
                    Gestiona tu<br />
                    <span class="bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">producción de contenido</span>
                </h1>
                <p class="mt-6 text-lg text-gray-400 max-w-2xl mx-auto">
                    Planifica, organiza y da seguimiento a tus videos, tareas e ideas.
                    GrowthOS centraliza toda tu producción audiovisual en un solo lugar.
                </p>
                <div class="mt-10 flex items-center justify-center gap-4">
                    <Link :href="route('register')" class="px-6 py-3 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-500 transition flex items-center gap-2 shadow-lg shadow-indigo-600/25">
                        Comenzar <ChevronRight class="w-4 h-4" />
                    </Link>
                    <a href="#features" class="px-6 py-3 rounded-xl border border-white/20 text-white/80 font-medium hover:bg-white/5 transition">Conocer más</a>
                </div>
            </section>

            <section id="features" class="py-20">
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="p-6 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/20 flex items-center justify-center mb-4">
                            <Youtube class="w-5 h-5 text-indigo-400" />
                        </div>
                        <h3 class="text-white font-semibold mb-2">Planificación visual</h3>
                        <p class="text-sm text-gray-400">Calendario semanal y mensual con drag & drop. Bloques de tiempo y vista por día.</p>
                    </div>
                    <div class="p-6 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition">
                        <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center mb-4">
                            <BarChart3 class="w-5 h-5 text-purple-400" />
                        </div>
                        <h3 class="text-white font-semibold mb-2">Dashboard en tiempo real</h3>
                        <p class="text-sm text-gray-400">Métricas de producción, tareas pendientes, completadas y atrasadas.</p>
                    </div>
                    <div class="p-6 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center mb-4">
                            <Wand2 class="w-5 h-5 text-emerald-400" />
                        </div>
                        <h3 class="text-white font-semibold mb-2">IA generativa</h3>
                        <p class="text-sm text-gray-400">Genera scripts, copy, frases clave y audio con Gemini y ElevenLabs.</p>
                    </div>
                    <div class="p-6 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center mb-4">
                            <Users class="w-5 h-5 text-amber-400" />
                        </div>
                        <h3 class="text-white font-semibold mb-2">Gestión de equipo</h3>
                        <p class="text-sm text-gray-400">Roles y permisos por organización. Cada empresa con sus propios datos.</p>
                    </div>
                    <div class="p-6 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition">
                        <div class="w-10 h-10 rounded-xl bg-rose-500/20 flex items-center justify-center mb-4">
                            <Settings class="w-5 h-5 text-rose-400" />
                        </div>
                        <h3 class="text-white font-semibold mb-2">Canales y reportes</h3>
                        <p class="text-sm text-gray-400">Administra canales de YouTube, exporta reportes en PDF y da seguimiento.</p>
                    </div>
                </div>
            </section>

            <section class="py-20 text-center border-t border-white/10">
                <h2 class="text-3xl font-bold text-white mb-4">¿Listo para empezar?</h2>
                <p class="text-gray-400 mb-8">Solicita tu código de invitación y únete a GrowthOS.</p>
                <Link :href="route('register')" class="px-6 py-3 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-500 transition inline-flex items-center gap-2 shadow-lg shadow-indigo-600/25">
                    Registrarse <ChevronRight class="w-4 h-4" />
                </Link>
            </section>
        </main>

        <footer class="border-t border-white/10 py-8 text-center text-sm text-gray-500">
            GrowthOS &mdash; Plataforma de gestión de contenido audiovisual
        </footer>
    </div>
</template>
