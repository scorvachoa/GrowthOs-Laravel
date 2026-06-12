import { ref } from 'vue'

const isDark = ref(false)
let initialized = false
let mediaQuery = null

function applyTheme(value) {
    if (value) {
        document.documentElement.classList.add('dark')
    } else {
        document.documentElement.classList.remove('dark')
    }
    isDark.value = value
}

function toggleDark() {
    const newValue = !isDark.value
    localStorage.setItem('theme', newValue ? 'dark' : 'light')
    applyTheme(newValue)
}

function setDark(value) {
    localStorage.setItem('theme', value ? 'dark' : 'light')
    applyTheme(value)
}

export function useTheme() {
    if (!initialized) {
        initialized = true
        const stored = localStorage.getItem('theme')
        if (stored === 'dark') {
            applyTheme(true)
        } else if (stored === 'light') {
            applyTheme(false)
        } else {
            applyTheme(window.matchMedia('(prefers-color-scheme: dark)').matches)
        }

        mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
        mediaQuery.addEventListener('change', (e) => {
            if (!localStorage.getItem('theme')) {
                applyTheme(e.matches)
            }
        })
    }

    return { isDark, toggleDark, setDark }
}
