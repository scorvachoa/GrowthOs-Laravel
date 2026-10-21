import { reactive, readonly } from 'vue'

const state = reactive({
    show: false,
    title: 'Error',
    message: '',
    goBack: false,
})

export function useErrorModal() {
    function open({ title = 'Error', message = '', goBack = false } = {}) {
        state.title = title
        state.message = message
        state.goBack = goBack
        state.show = true
    }

    function close() {
        const shouldGoBack = state.goBack
        state.show = false
        state.goBack = false
        if (shouldGoBack && window.history.length > 1) {
            window.history.back()
        }
    }

    return { state: readonly(state), open, close }
}
