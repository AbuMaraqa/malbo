<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import PermissionGroupField from './PermissionGroupField.vue';
import type { PermissionGroup, RoleRow } from './types';

const props = defineProps<{ role: RoleRow | null; permissionGroups: PermissionGroup[] }>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm<{ name: string; permissions: number[] }>({ name: '', permissions: [] });

watch(
    open,
    (isOpen) => {
        if (!isOpen) return;
        form.clearErrors();
        form.name = props.role?.name ?? '';
        form.permissions = [...(props.role?.permissions ?? [])];
    },
    { immediate: true },
);

const allIds = computed(() => props.permissionGroups.flatMap((group) => group.permissions.map((permission) => permission.id)));
const allChosen = computed(() => allIds.value.length > 0 && allIds.value.every((id) => form.permissions.includes(id)));

function toggleAll(): void {
    form.permissions = allChosen.value ? [] : [...allIds.value];
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (props.role) {
        form.put(route('roles.update', props.role.id), options);
    } else {
        form.post(route('roles.store'), options);
    }
}
</script>

<template>
    <Modal v-model:open="open" :title="role ? t('Edit Role') : t('Create Role')" width="max-w-4xl">
        <form class="space-y-5" @submit.prevent="submit">
            <div class="grid items-end gap-4 md:grid-cols-2">
                <TextInput v-model="form.name" :label="t('Role Name')" required :maxlength="50" :disabled="role?.protected" :error="form.errors.name" />
                <label class="flex items-center gap-2 pb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <input type="checkbox" class="size-4 rounded" :checked="allChosen" @change="toggleAll" />
                    {{ t('Select All Permissions') }}
                </label>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ t('Assign Permissions') }}</h3>
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ t('Permissions are grouped by feature. Open only the group you want to edit.') }}</p>
                <p v-if="form.errors.permissions" class="mb-3 text-sm text-danger" role="alert">{{ form.errors.permissions }}</p>
                <div class="grid items-start gap-3 md:grid-cols-2">
                    <PermissionGroupField v-for="group in permissionGroups" :key="group.key" v-model="form.permissions" :group="group" />
                </div>
            </div>
        </form>
        <template #footer>
            <Button @click="open = false">{{ t('Cancel') }}</Button>
            <Button variant="primary" :loading="form.processing" @click="submit">{{ t('Save') }}</Button>
        </template>
    </Modal>
</template>
