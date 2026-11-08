<script setup lang="ts">
import Modal from '@/Components/ui/Modal.vue';
import { t } from '@/lib/i18n';

defineProps<{ user: { name: string; email: string; roles: { label: string }[] } }>();

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Modal v-model:open="open" :title="user.name">
        <dl class="space-y-3 text-sm">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ t('Name') }}</dt>
                <dd class="text-gray-800 dark:text-white/90">{{ user.name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ t('Email') }}</dt>
                <dd class="text-gray-800 dark:text-white/90" dir="ltr">{{ user.email }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ t('Roles') }}</dt>
                <dd class="text-gray-800 dark:text-white/90">{{ user.roles.map((role) => role.label).join('، ') }}</dd>
            </div>
        </dl>
    </Modal>
</template>
