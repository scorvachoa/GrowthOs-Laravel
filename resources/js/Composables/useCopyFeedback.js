import { ref } from 'vue'

export function useCopyFeedback() {
    const copiedKey = ref(null)
    let timer = null

    async function copy(text, key) {
        try {
            await navigator.clipboard.writeText(text)
        } catch {
            return
        }
        copiedKey.value = key
        clearTimeout(timer)
        timer = setTimeout(() => { copiedKey.value = null }, 1500)
    }

    return { copiedKey, copy }
}
