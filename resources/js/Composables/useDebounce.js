import { watch } from 'vue'

export function useDebounce(source, callback, delay = 400) {
    let timer = null

    return watch(source, (...args) => {
        clearTimeout(timer)
        timer = setTimeout(() => callback(...args), delay)
    })
}
