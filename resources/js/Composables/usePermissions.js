import { usePage } from '@inertiajs/vue3'

export function usePermissions() {
    const page = usePage()

    const can = (perm) => (page.props.auth?.user?.permissions ?? []).includes(perm)

    return { can }
}
