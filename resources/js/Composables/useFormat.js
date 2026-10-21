/**
 * Formateo de fechas unificado (es-PE). Sustituye los formatDate locales
 * repetidos por página.
 */
export function useFormat() {
    /** '2025-10-12' | ISO → '12/10/2025' */
    function formatDate(value) {
        const d = toDate(value)
        if (!d) return '-'
        return d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' })
    }

    /** ISO → '12 oct 2025, 5:40 p.m.' */
    function formatDateTime(value) {
        const d = toDate(value)
        if (!d) return '-'
        return d.toLocaleDateString('es-PE', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        })
    }

    /** ISO → '12 oct 2025' */
    function formatShortDate(value) {
        const d = toDate(value)
        if (!d) return '-'
        return d.toLocaleDateString('es-PE', { day: 'numeric', month: 'short', year: 'numeric' })
    }

    /** ISO → '12 oct' (gráficos) */
    function formatChartDate(value) {
        const d = toDate(value)
        if (!d) return '-'
        return d.toLocaleDateString('es-PE', { day: 'numeric', month: 'short' }).replace('.', '')
    }

    return { formatDate, formatDateTime, formatShortDate, formatChartDate }
}

function toDate(value) {
    if (!value) return null
    const d = value instanceof Date ? value : new Date(value)
    return isNaN(d.getTime()) ? null : d
}
