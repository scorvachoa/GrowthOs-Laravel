import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

export function useAbsenceActions(baseUrl) {
    const showApprove = ref(false)
    const showReject = ref(false)
    const showDelete = ref(false)
    const targetItem = ref(null)

    function confirmApprove(item) {
        targetItem.value = item
        showApprove.value = true
    }

    function executeApprove() {
        if (!targetItem.value) return
        router.patch(`${baseUrl}/${targetItem.value.id}/approve`, {}, {
            preserveScroll: true,
            onSuccess: () => { showApprove.value = false; targetItem.value = null },
        })
    }

    function confirmReject(item) {
        targetItem.value = item
        showReject.value = true
    }

    function executeReject() {
        if (!targetItem.value) return
        router.patch(`${baseUrl}/${targetItem.value.id}/reject`, {}, {
            preserveScroll: true,
            onSuccess: () => { showReject.value = false; targetItem.value = null },
        })
    }

    function confirmDelete(item) {
        targetItem.value = item
        showDelete.value = true
    }

    function executeDelete() {
        if (!targetItem.value) return
        router.delete(`${baseUrl}/${targetItem.value.id}`, {
            onSuccess: () => { showDelete.value = false; targetItem.value = null },
        })
    }

    return {
        showApprove,
        showReject,
        showDelete,
        targetItem,
        confirmApprove,
        executeApprove,
        confirmReject,
        executeReject,
        confirmDelete,
        executeDelete,
    }
}
