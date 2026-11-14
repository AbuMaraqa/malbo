<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';

import TextareaInput from '@/Components/form/TextareaInput.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { Check, ExternalLink, Trash2, X } from '@/lib/icons';
import { route } from '@/lib/route';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    review: {
        id: number;
        product: { name: string; url: string | null };
        author: string;
        email: string | null;
        rating: number;
        title: string | null;
        body: string;
        status: 'pending' | 'approved' | 'rejected';
        status_label: string;
        staff_reply: string | null;
        replied_at: string | null;
        order: { number: string; url: string } | null;
        photos: { id: number; url: string }[];
        created_at: string | null;
    };
}>();

const tones = { pending: 'warning', approved: 'success', rejected: 'danger' } as const;
const reply = useForm({ staff_reply: props.review.staff_reply ?? '' });

function setStatus(status: 'approved' | 'rejected' | 'pending'): void {
    router.put(route('reviews.status', props.review.id), { status }, { preserveScroll: true });
}

function saveReply(): void {
    reply.put(route('reviews.reply', props.review.id), { preserveScroll: true });
}

async function removePhoto(id: number): Promise<void> {
    if (await confirm({ title: t('Remove this photo?'), description: t('This cannot be undone.') })) {
        router.delete(route('reviews.photos.destroy', { review: props.review.id, media: id }), { preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Review #:id', { id: review.id })" :parents="[{ title: t('Reviews'), href: route('reviews.index') }]" />

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="grid content-start gap-6">
            <Card :title="review.title ?? t('Review')">
                <template #actions>
                    <Badge :tone="tones[review.status]" dot>{{ review.status_label }}</Badge>
                </template>
                <p class="text-xl text-warning-500" :aria-label="t(':rating out of 5 stars', { rating: review.rating })">
                    {{ '★'.repeat(review.rating) }}<span class="text-gray-300 dark:text-gray-600">{{ '★'.repeat(5 - review.rating) }}</span>
                </p>
                <p class="mt-3 leading-7 whitespace-pre-line text-ink">{{ review.body }}</p>

                <div v-if="review.photos.length" class="mt-5 flex flex-wrap gap-3">
                    <figure v-for="photo in review.photos" :key="photo.id" class="relative">
                        <a :href="photo.url" target="_blank" rel="noopener"><img :src="photo.url" alt="" class="size-28 rounded-lg border border-line object-cover" /></a>
                        <button
                            type="button"
                            class="absolute end-1 top-1 grid size-7 cursor-pointer place-items-center rounded-full bg-white/90 text-error-600 shadow hover:bg-white dark:bg-gray-900/90 dark:text-error-400"
                            :aria-label="t('Remove this photo?')"
                            @click="removePhoto(photo.id)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </figure>
                </div>

                <template #footer>
                    <Button v-if="review.status !== 'approved'" variant="primary" @click="setStatus('approved')"><Check class="size-4" />{{ t('Approve') }}</Button>
                    <Button v-if="review.status !== 'rejected'" @click="setStatus('rejected')"><X class="size-4" />{{ t('Reject') }}</Button>
                </template>
            </Card>

            <Card :title="t('Reply from the store')" :description="t('Shown under the review once it is approved. Leave empty for no reply.')">
                <form class="grid gap-3" @submit.prevent="saveReply">
                    <TextareaInput v-model="reply.staff_reply" :rows="4" :maxlength="2000" :error="reply.errors.staff_reply" />
                    <p v-if="review.replied_at" class="text-sm text-muted">{{ t('Replied on :date', { date: review.replied_at }) }}</p>
                    <Button type="submit" variant="primary" class="justify-self-start" :loading="reply.processing">{{ t('Save reply') }}</Button>
                </form>
            </Card>
        </div>

        <Card :title="t('Details')">
            <dl class="grid gap-3 text-sm">
                <div>
                    <dt class="text-muted">{{ t('Product') }}</dt>
                    <dd class="font-medium text-ink">
                        <a v-if="review.product.url" :href="review.product.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 hover:text-brand-600 dark:hover:text-brand-400">{{ review.product.name }}<ExternalLink class="size-3.5" /></a>
                        <template v-else>{{ review.product.name }}</template>
                    </dd>
                </div>
                <div>
                    <dt class="text-muted">{{ t('Customer') }}</dt>
                    <dd class="font-medium text-ink">{{ review.author }}</dd>
                    <dd v-if="review.email" class="text-muted" dir="ltr">{{ review.email }}</dd>
                </div>
                <div v-if="review.order">
                    <dt class="text-muted">{{ t('Verified purchase') }}</dt>
                    <dd><Button size="sm" :href="review.order.url">{{ t('Order :number', { number: review.order.number }) }}</Button></dd>
                </div>
                <div>
                    <dt class="text-muted">{{ t('Received') }}</dt>
                    <dd class="text-ink">{{ review.created_at }}</dd>
                </div>
            </dl>
        </Card>
    </div>
</template>
