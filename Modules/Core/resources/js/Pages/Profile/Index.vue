<script setup lang="ts">
import type { ExistingImage } from '@/Components/form/ImageUpload.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

import PasswordForm from './parts/PasswordForm.vue';
import PersonalInformationForm from './parts/PersonalInformationForm.vue';
import ProfileHeader from './parts/ProfileHeader.vue';
import TwoFactorPanel, { type TwoFactorState } from './parts/TwoFactorPanel.vue';

defineOptions({ layout: AdminLayout });

defineProps<{
    profile: {
        name: string;
        email: string;
        avatar_url: string;
        cover_url: string | null;
        avatar: ExistingImage[];
        cover_photo: ExistingImage[];
        roles: string[];
    };
    twoFactor: TwoFactorState | null;
}>();
</script>

<template>
    <PageHeader :title="t('Profile')" />
    <div class="space-y-4">
        <ProfileHeader :name="profile.name" :email="profile.email" :avatar-url="profile.avatar_url" :cover-url="profile.cover_url" :roles="profile.roles" />
        <div class="grid items-start gap-4 lg:grid-cols-2">
            <PersonalInformationForm :name="profile.name" :email="profile.email" :avatar="profile.avatar" :cover-photo="profile.cover_photo" />
            <div class="space-y-4">
                <PasswordForm />
                <TwoFactorPanel v-if="twoFactor" :state="twoFactor" />
            </div>
        </div>
    </div>
</template>
