import { ref } from 'vue'

function legacyCopy(text) {
    const ta = document.createElement('textarea')
    ta.value = text
    ta.style.position = 'fixed'
    ta.style.top = '0'
    ta.style.left = '0'
    ta.style.opacity = '0'
    document.body.appendChild(ta)
    ta.focus()
    ta.select()
    try {
        ta.setSelectionRange(0, text.length)
    } catch {
        // navegador sin soporte de setSelectionRange (no crítico)
    }
    let ok = false
    try {
        ok = document.execCommand('copy')
    } catch {
        ok = false
    }
    ta.remove()
    return ok
}

export async function writeClipboard(text) {
    if (window.isSecureContext && navigator.clipboard) {
        try {
            await navigator.clipboard.writeText(text)
            return true
        } catch {
            // permiso denegado u otro fallo: probamos el fallback
        }
    }
    return legacyCopy(text)
}

export function useCopyFeedback() {
    const copiedKey = ref(null)
    let timer = null

    async function copy(text, key) {
        const ok = await writeClipboard(text)
        if (!ok) return
        copiedKey.value = key
        clearTimeout(timer)
        timer = setTimeout(() => { copiedKey.value = null }, 1500)
    }

    return { copiedKey, copy }
}
