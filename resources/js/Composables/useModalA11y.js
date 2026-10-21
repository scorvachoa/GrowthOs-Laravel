import { nextTick, onMounted, onUnmounted, watch } from 'vue'

/**
 * A11y para modales: Escape, focus trap (Tab/Shift+Tab), restauración del
 * foco al cerrar y atributos ARIA sobre el contenedor del diálogo.
 *
 * Uso:
 *   const dialogRef = ref(null)
 *   useModalA11y(dialogRef, () => props.show, () => emit('close'))
 *   <div ref="dialogRef" role="dialog" aria-modal="true" :aria-label="title">
 */
export function useModalA11y(dialogRef, isOpen, onClose) {
    let previouslyFocused = null

    const FOCUSABLE = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',')

    function focusables() {
        if (!dialogRef.value) return []
        return Array.from(dialogRef.value.querySelectorAll(FOCUSABLE))
            .filter((el) => el.offsetParent !== null || el === document.activeElement)
    }

    function handleKeydown(e) {
        if (!isOpen()) return

        if (e.key === 'Escape') {
            e.stopPropagation()
            onClose()
            return
        }

        if (e.key !== 'Tab') return

        const items = focusables()
        if (items.length === 0) {
            e.preventDefault()
            dialogRef.value?.focus()
            return
        }

        const first = items[0]
        const last = items[items.length - 1]
        const active = document.activeElement

        if (e.shiftKey && (active === first || !dialogRef.value.contains(active))) {
            e.preventDefault()
            last.focus()
        } else if (!e.shiftKey && (active === last || !dialogRef.value.contains(active))) {
            e.preventDefault()
            first.focus()
        }
    }

    function trapFocus() {
        const items = focusables()
        if (items.length > 0) {
            items[0].focus()
        } else {
            dialogRef.value?.focus()
        }
    }

    onMounted(() => {
        document.addEventListener('keydown', handleKeydown, true)
        if (isOpen()) {
            previouslyFocused = document.activeElement
            nextTick(trapFocus)
        }
    })

    onUnmounted(() => {
        document.removeEventListener('keydown', handleKeydown, true)
    })

    watch(isOpen, (open) => {
        if (open) {
            previouslyFocused = document.activeElement
            nextTick(trapFocus)
        } else if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
            previouslyFocused.focus()
            previouslyFocused = null
        }
    })
}
