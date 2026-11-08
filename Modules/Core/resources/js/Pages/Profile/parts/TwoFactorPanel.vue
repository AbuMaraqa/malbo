<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import TwoFactorPasswordForm from './TwoFactorPasswordForm.vue';
import TwoFactorSetup from './TwoFactorSetup.vue';

export interface TwoFactorState {
    enabled: boolean;
    qrCodeSvg: string | null;
    secretKey: string | null;
    recoveryCodes: string[] | null;
}

defineProps<{ state: TwoFactorState }>();
</script>

<template>
    <Card :title="t('Two-Factor Authentication')">
        <template #actions>
            <Badge :tone="state.enabled ? 'success' : 'neutral'">{{ state.enabled ? t('On') : t('Off') }}</Badge>
        </template>

        <div class="space-y-5">
            <template v-if="state.enabled">
                <div v-if="state.recoveryCodes" class="space-y-2">
                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ t('Save these recovery codes. Each one works once, and they are shown only now.') }}</p>
                    <ul class="grid gap-1 rounded-lg bg-gray-100 p-3 font-mono text-sm sm:grid-cols-2 dark:bg-white/5" dir="ltr">
                        <li v-for="code in state.recoveryCodes" :key="code">{{ code }}</li>
                    </ul>
                </div>
                <div class="space-y-2">
                    <h3 class="text-sm font-medium text-gray-800 dark:text-white/90">{{ t('New recovery codes') }}</h3>
                    <TwoFactorPasswordForm :url="route('profile.two-factor.recovery-codes')" :label="t('Generate')" />
                </div>
                <div class="space-y-2">
                    <h3 class="text-sm font-medium text-gray-800 dark:text-white/90">{{ t('Turn off two-factor authentication') }}</h3>
                    <TwoFactorPasswordForm :url="route('profile.two-factor.disable')" method="delete" :label="t('Turn off')" danger />
                </div>
            </template>

            <template v-else-if="state.qrCodeSvg">
                <TwoFactorSetup :qr-code-svg="state.qrCodeSvg" :secret-key="state.secretKey" />
                <div class="space-y-2">
                    <h3 class="text-sm font-medium text-gray-800 dark:text-white/90">{{ t('Cancel the setup') }}</h3>
                    <TwoFactorPasswordForm :url="route('profile.two-factor.disable')" method="delete" :label="t('Cancel')" danger />
                </div>
            </template>

            <template v-else>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Protect your account with a code from an authenticator app when you log in.') }}</p>
                <TwoFactorPasswordForm :url="route('profile.two-factor.enable')" :label="t('Turn on')" />
            </template>
        </div>
    </Card>
</template>
