import '../css/app.css';
import './bootstrap';

import axios from 'axios';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { useErrorModal } from './Composables/useErrorModal';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const { open: openErrorModal } = useErrorModal();

function errorInfoForStatus(status) {
    switch (status) {
        case 401:
            return { title: 'Sesión expirada', message: 'Tu sesión ha caducado. Inicia sesión de nuevo.', goBack: false };
        case 403:
            return { title: 'Acceso denegado', message: 'No tienes permisos para realizar esta acción.', goBack: true };
        case 404:
            return { title: 'No encontrado', message: 'El recurso solicitado no existe o fue eliminado.', goBack: true };
        case 429:
            return { title: 'Demasiadas solicitudes', message: 'Has hecho demasiados intentos. Espera un momento y vuelve a intentarlo.', goBack: false };
        case 500:
        case 502:
        case 503:
            return { title: 'Error del servidor', message: 'Ocurrió un error en el servidor. Intenta de nuevo; si persiste, contacta al administrador.', goBack: false };
        default:
            return { title: 'Algo salió mal', message: 'La operación no se pudo completar. Intenta de nuevo.', goBack: false };
    }
}

// Refresh CSRF token from every Inertia response
router.on('success', (event) => {
    const token = event.page.props.csrf_token
    if (token) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = token
        const meta = document.head.querySelector('meta[name="csrf-token"]')
        if (meta) meta.content = token
    }
})

// Respuestas no-Inertia (403, 404, 500…): modal global en vez de HTML crudo
router.on('invalid', (event) => {
    const status = event.detail?.response?.status
    if (status === 419) {
        window.location.reload()
        return false
    }
    openErrorModal(errorInfoForStatus(status))
    return false
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
