<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import { t } from '@/lib/i18n';
import { Letter, Phone } from '@/lib/icons';

export interface ContactMessageRow {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    subject: string | null;
    message: string;
    is_read: boolean;
    received: string | null;
    received_at: string | null;
}

/** One customer message, opened from the inbox. */
defineProps<{ message: ContactMessageRow }>();

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Modal v-model:open="open" :title="message.subject || t('Message from :name', { name: message.name })">
        <div class="space-y-5">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted">{{ t('Name') }}</dt>
                    <dd class="mt-1 font-semibold">{{ message.name }}</dd>
                </div>
                <div>
                    <dt class="text-muted">{{ t('Received') }}</dt>
                    <dd class="mt-1">{{ message.received_at }}</dd>
                </div>
                <div v-if="message.phone">
                    <dt class="text-muted">{{ t('Phone') }}</dt>
                    <dd class="mt-1"><a :href="`tel:${message.phone}`" dir="ltr" class="font-semibold text-brand-600 dark:text-brand-400">{{ message.phone }}</a></dd>
                </div>
                <div v-if="message.email">
                    <dt class="text-muted">{{ t('Email') }}</dt>
                    <dd class="mt-1 break-all"><a :href="`mailto:${message.email}`" class="font-semibold text-brand-600 dark:text-brand-400">{{ message.email }}</a></dd>
                </div>
            </dl>

            <p class="rounded-lg bg-surface p-4 text-[15px] leading-7 whitespace-pre-line">{{ message.message }}</p>

            <div class="flex flex-wrap gap-2">
                <Button v-if="message.phone" :href="`tel:${message.phone}`" external><Phone class="size-4" />{{ t('Call') }}</Button>
                <Button v-if="message.email" :href="`mailto:${message.email}`" external><Letter class="size-4" />{{ t('Reply by email') }}</Button>
            </div>
        </div>
    </Modal>
</template>
