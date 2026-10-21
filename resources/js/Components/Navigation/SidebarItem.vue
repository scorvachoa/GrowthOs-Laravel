<script setup>
import { inject, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ChevronDown } from 'lucide-vue-next'

const props = defineProps({
    item: Object,
    active: { type: Boolean, default: false },
    activeRoute: { type: String, default: '' },
    expanded: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle', 'close'])

const collapsed = inject('sidebarCollapsed')

const hasChildren = computed(() => (props.item.children?.length ?? 0) > 0)

const hasActiveChild = computed(() =>
    hasChildren.value && props.item.children.some(child => child.route === props.activeRoute)
)

// Clics que burbujean al raíz: cierran el drawer móvil salvo cuando
// provienen de la fila de un grupo (que solo alterna el submenú).
const onRootClick = (event) => {
    if (event.target.closest('[data-parent-row]')) return
    emit('close')
}
</script>

<template>
    <div @click="onRootClick">
        <!-- Fila de grupo: enlaza al hub y alterna el submenú -->
        <template v-if="hasChildren">
            <div
                data-parent-row
                class="flex items-center rounded-xl transition"
                :class="[
                    collapsed
                        ? 'justify-center p-3'
                        : 'gap-1 px-4 py-3',
                    active
                        ? 'bg-indigo-600 text-white'
                        : hasActiveChild
                            ? 'text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-gray-800'
                            : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
                ]"
                @click="emit('toggle')"
            >
                <Link
                    :href="item.route"
                    class="flex items-center gap-3 min-w-0 flex-1"
                    :title="collapsed ? item.title : undefined"
                    :aria-current="active ? 'page' : undefined"
                >
                    <component
                        :is="item.icon"
                        class="w-5 h-5 shrink-0"
                    />

                    <span v-if="!collapsed" class="font-medium truncate">
                        {{ item.title }}
                    </span>
                </Link>

                <button
                    v-if="!collapsed"
                    type="button"
                    class="p-1 shrink-0 rounded-md hover:bg-black/5 dark:hover:bg-white/10 transition"
                    :aria-expanded="expanded"
                    :aria-label="expanded ? `Colapsar ${item.title}` : `Expandir ${item.title}`"
                    @click.stop="emit('toggle')"
                >
                    <ChevronDown
                        class="w-4 h-4 transition-transform duration-200"
                        :class="expanded ? 'rotate-180' : ''"
                    />
                </button>
            </div>
        </template>

        <!-- Entrada simple -->
        <Link
            v-else
            :href="item.route"
            class="flex items-center rounded-xl transition"
            :class="[
                collapsed
                    ? 'justify-center p-3'
                    : 'gap-3 px-4 py-3',
                active
                    ? 'bg-indigo-600 text-white'
                    : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
            ]"
            :title="collapsed ? item.title : undefined"
            :aria-current="active ? 'page' : undefined"
        >
            <component
                :is="item.icon"
                class="w-5 h-5 shrink-0"
            />

            <span v-if="!collapsed" class="font-medium truncate">
                {{ item.title }}
            </span>
        </Link>

        <!-- Submenú desplegable -->
        <template v-if="hasChildren && expanded && !collapsed">
            <Link
                v-for="child in item.children"
                :key="child.route"
                :href="child.route"
                class="flex items-center rounded-xl transition text-sm mt-1 pl-11 pr-4 py-2.5"
                :class="child.route === activeRoute
                    ? 'bg-indigo-600 text-white font-medium'
                    : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'"
                :aria-current="child.route === activeRoute ? 'page' : undefined"
            >
                <span class="truncate">{{ child.title }}</span>
            </Link>
        </template>
    </div>
</template>
