<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

/** The merchant's account in brief, with the way to its ledger. */
defineProps<{ finance: { balance_label: string; ledger_url: string } }>();
</script>

<template>
    <Card :title="t('Account')">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-muted">{{ t('Balance due') }}</p>
                <p class="text-xl font-semibold text-ink" dir="ltr">{{ finance.balance_label }}</p>
            </div>
            <Button :href="finance.ledger_url">{{ t('Open the ledger') }}</Button>
        </div>
    </Card>
</template>
