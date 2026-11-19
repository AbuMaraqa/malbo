<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, useId } from 'vue';

import { t } from '@/lib/i18n';
import { Check } from '@/lib/icons';

import StoreField from './StoreField.vue';
import { btnDark, fieldError, fieldLabel } from './styles';

/**
 * A verified buyer's review: stars, a title, the text and up to four photos. The browser checks
 * what it can (the server checks it all again, photos by their content); posting again replaces the
 * buyer's review, which then waits for the store's approval.
 */
const props = defineProps<{
    submitUrl: string;
    review: { rating: number; title: string | null; body: string } | null;
}>();

const MAX_PHOTOS = 4;
const MAX_BYTES = 5 * 1024 * 1024;
const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const id = useId();
const form = useForm({
    rating: props.review?.rating ?? 0,
    title: props.review?.title ?? '',
    body: props.review?.body ?? '',
    photos: [] as File[],
});
const done = ref(false);
const leftOut = ref(false);
const missing = ref<{ rating?: string; body?: string }>({});
const length = computed(() => form.body.length);
// The server also answers on `review` (not a verified buyer) and `photos.N`, fields the form does not have.
const errors = computed(() => form.errors as Record<string, string | undefined>);

function choose(event: Event): void {
    const files = Array.from((event.target as HTMLInputElement).files ?? []);
    const kept = files.filter((file) => TYPES.includes(file.type) && file.size <= MAX_BYTES).slice(0, MAX_PHOTOS);

    leftOut.value = kept.length < files.length;
    form.photos = kept;
}

function submit(): void {
    missing.value = {
        ...(form.rating < 1 || form.rating > 5 ? { rating: t('Choose a rating') } : {}),
        ...(form.body.trim().length < 3 ? { body: t('Write a few words about the product') } : {}),
    };

    if (Object.keys(missing.value).length || form.processing) return;

    form.post(props.submitUrl, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            done.value = true;
            form.reset('photos');
        },
    });
}
</script>

<template>
    <p v-if="done" role="status" class="flex items-center gap-2 rounded-lg border border-line bg-(--shell) p-5 font-semibold text-[#15803d]">
        <Check class="size-5 shrink-0" aria-hidden="true" />
        {{ t('Thank you. Your review will appear once we check it.') }}
    </p>
    <form v-else class="grid gap-5 rounded-lg border border-line bg-(--shell) p-6" novalidate @submit.prevent="submit">
        <h3 class="text-lg font-bold">{{ review ? t('Edit your review') : t('Write a review') }}</h3>
        <p v-if="review" class="-mt-3 text-sm text-(--ink-soft)">{{ t('Sending it again replaces your review, which waits for our approval again.') }}</p>

        <fieldset>
            <legend :class="fieldLabel">{{ t('Your rating') }} <span class="text-(--danger)" aria-hidden="true">*</span></legend>
            <div class="flex flex-row-reverse justify-end gap-1" dir="ltr">
                <template v-for="star in [5, 4, 3, 2, 1]" :key="star">
                    <input :id="`${id}-star-${star}`" v-model="form.rating" type="radio" name="rating" :value="star" class="peer sr-only" />
                    <label
                        :for="`${id}-star-${star}`"
                        class="cursor-pointer text-3xl leading-none text-(--ink-18) peer-checked:text-(--gold) peer-focus-visible:outline-2 peer-focus-visible:outline-store hover:text-(--gold)"
                        :class="{ 'text-(--gold)': form.rating >= star }"
                    >
                        <span aria-hidden="true">★</span>
                        <span class="sr-only">{{ t(':count stars', { count: star }) }}</span>
                    </label>
                </template>
            </div>
            <p v-if="missing.rating || form.errors.rating" :class="fieldError">{{ missing.rating ?? form.errors.rating }}</p>
        </fieldset>

        <StoreField v-model="form.title" :label="t('Title')" :hint="t('optional')" :maxlength="120" :error="form.errors.title" />
        <div>
            <StoreField v-model="form.body" :label="t('Your review')" required :rows="5" :maxlength="2000" :error="missing.body ?? form.errors.body ?? errors.review" />
            <p class="mt-1 text-end text-xs text-muted" aria-live="polite">{{ length }} / 2000</p>
        </div>

        <div>
            <label :for="`${id}-photos`" :class="fieldLabel">{{ t('Photos') }} <span class="font-normal text-muted">({{ t('up to 4, JPG, PNG or WebP, 5 MB each') }})</span></label>
            <input :id="`${id}-photos`" type="file" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-sm" @change="choose" />
            <p v-if="leftOut" class="mt-1 text-sm text-(--ink-soft)">{{ t('Some photos were left out: at most 4, JPG, PNG or WebP, 5 MB each.') }}</p>
            <p v-for="(message, key) in errors" v-show="String(key).startsWith('photos')" :key="key" :class="fieldError">{{ message }}</p>
        </div>

        <button type="submit" class="justify-self-start" :class="btnDark" :disabled="form.processing">{{ form.processing ? t('Sending…') : t('Send review') }}</button>
    </form>
</template>
