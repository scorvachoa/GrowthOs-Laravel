export function useTimeAgo() {
    const formatDateFallback = (iso) => {
        if (!iso) return '—'
        const d = new Date(iso)
        if (isNaN(d.getTime())) return '—'
        return d.toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' }) +
            ', ' + d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' })
    }

    const timeAgo = (iso) => {
        if (!iso) return '—'
        const diff = Date.now() - new Date(iso).getTime()
        if (isNaN(diff)) return '—'
        const mins = Math.floor(diff / 60000)
        if (mins < 1) return 'ahora mismo'
        if (mins < 60) return `hace ${mins} min`
        const hours = Math.floor(mins / 60)
        if (hours < 24) return hours === 1 ? 'hace 1 hora' : `hace ${hours} h`
        const days = Math.floor(hours / 24)
        if (days === 1) return 'ayer'
        if (days < 30) return `hace ${days} días`
        return formatDateFallback(iso)
    }

    return { timeAgo }
}
