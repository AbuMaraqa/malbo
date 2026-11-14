<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ref, useId, watch } from 'vue';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

/**
 * Checkout's "Remind me about my cart" (unticked until the buyer ticks it). While ticked, the email
 * and phone typed above are kept on the cart, saved as the buyer leaves the fields (the parent calls
 * `save()` on focusout); unticking takes them back. Nothing is sent before the box is ever ticked.
 */
const props = defineProps<{ email: string; phone: string }>();

const id = useId();
const remind = ref(false);
const http = useHttp({ remind: false, email: null as string | null, phone: null as string | null });
let lastSent = '';

function save(): void {
    const payload = { remind: remind.value, email: props.email.trim() || null, phone: props.phone.trim() || null };

    // Never agreed: nothing to keep, nothing to take back.
    if (!remind.value && lastSent === '') return;

    const key = JSON.stringify(remind.value ? payload : { remind: false });
    if (key === lastSent) return;
    lastSent = key;

    http.remind = payload.remind;
    http.email = payload.remind ? payload.email : null;
    http.phone = payload.remind ? payload.phone : null;
    // A typo is not this box's business: the order form reports it. Try again on the next change.
    http.post(route('store.cart.contact')).catch(() => (lastSent = ''));
}

watch(remind, save);

defineExpose({ save });
</script>

<template>
    <div class="sm:col-span-2">
        <label :for="id" class="flex cursor-pointer items-start gap-2">
            <input :id="id" v-model="remind" type="checkbox" class="mt-1 size-4 accent-(--color-store)" :aria-describedby="`${id}-hint`" />
            <span>
                <span class="font-semibold">{{ t('Remind me about my cart') }}</span>
                <span :id="`${id}-hint`" class="block text-sm text-(--ink-soft)">{{ t('If you do not finish your order, we may send you a reminder by email or text message.') }}</span>
            </span>
        </label>
    </div>
</template>
