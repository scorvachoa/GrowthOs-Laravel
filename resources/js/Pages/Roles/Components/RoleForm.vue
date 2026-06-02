<script setup>
import TextInput from '@/Components/Forms/TextInput.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'

const props = defineProps({
    form: Object,
    permissions: Array,
    submitLabel: {
        type: String,
        default: 'Save',
    },
})

const emit = defineEmits([
    'submit',
])

const togglePermission = (permission) => {

    const exists =
        (props.form.permissions || []).includes(permission)

    if (exists) {
        props.form.permissions =
            props.form.permissions.filter(
                p => p !== permission
            )

        return
    }

    props.form.permissions = [
        ...(props.form.permissions || []),
        permission,
    ]
}
</script>

<template>
    <form
        @submit.prevent="emit('submit')"
        class="space-y-6"
    >

        <TextInput
            v-model="form.name"
            label="Role Name"
            :error="form.errors.name"
        />

        <div>

            <label class="block mb-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                Permissions
            </label>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <label
                    v-for="permission in permissions"
                    :key="permission.id"
                    class="flex items-center gap-3 bg-gray-50 dark:bg-gray-800 p-4 rounded-xl cursor-pointer"
                >

                    <input
                        type="checkbox"

                        :checked="
                            form.permissions.includes(
                                permission.name
                            )
                        "

                        @change="
                            togglePermission(
                                permission.name
                            )
                        "
                    >

                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ permission.name }}
                    </span>

                </label>

            </div>

            <p
                v-if="form.errors.permissions"
                class="text-red-500 text-sm mt-2"
            >
                {{ form.errors.permissions }}
            </p>

        </div>

        <PrimaryButton
            :disabled="form.processing"
        >
            {{ submitLabel }}
        </PrimaryButton>

    </form>
</template>