import '../css/app.css';
import './bootstrap';

import axios from 'axios';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Refresh CSRF token from every Inertia response
router.on('success', (event) => {
    const token = event.page.props.csrf_token
    if (token) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = token
        const meta = document.head.querySelector('meta[name="csrf-token"]')
        if (meta) meta.content = token
    }
})

// Reload page on 419 (CSRF token mismatch)
router.on('invalid', (response) => {
    if (response?.status === 419) window.location.reload()
})
window.axios.interceptors.response.use(
    r => r,
    e => { if (e.response?.status === 419) window.location.reload(); return Promise.reject(e) }
)

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
