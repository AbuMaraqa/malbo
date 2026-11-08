<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import ImageUpload, { type ExistingImage } from '@/Components/form/ImageUpload.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

const props = defineProps<{ name: string; email: string; avatar: ExistingImage[]; coverPhoto: ExistingImage[] }>();

const form = useForm<{
    _method: 'put';
    name: string;
    email: string;
    avatar: File | null;
    cover_photo: File | null;
    remove_avatar: boolean;
    remove_cover_photo: boolean;
}>({
    _method: 'put',
    name: props.name,
    email: props.email,
    avatar: null,
    cover_photo: null,
    remove_avatar: false,
    remove_cover_photo: false,
});

const removedAvatar = ref<(number | string)[]>([]);
const removedCover = ref<(number | string)[]>([]);

watch(removedAvatar, (ids) => (form.remove_avatar = ids.length > 0));
watch(removedCover, (ids) => (form.remove_cover_photo = ids.length > 0));

function submit(): void {
    form.post(route('profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('avatar', 'cover_photo', 'remove_avatar', 'remove_cover_photo');
            removedAvatar.value = [];
            removedCover.value = [];
        },
    });
}
</script>

<template>
    <Card :title="t('Personal Information')">
        <form class="space-y-4" @submit.prevent="submit">
            <TextInput v-model="form.name" :label="t('Name')" required :maxlength="255" autocomplete="name" :error="form.errors.name" />
            <TextInput v-model="form.email" :label="t('Email')" type="email" dir="ltr" required :maxlength="255" autocomplete="email" :error="form.errors.email" />
            <div class="grid gap-4 sm:grid-cols-2">
                <ImageUpload v-model="form.avatar" v-model:removed="removedAvatar" :label="t('Avatar')" :existing="avatar" :error="form.errors.avatar" />
                <ImageUpload v-model="form.cover_photo" v-model:removed="removedCover" :label="t('Cover Photo')" :existing="coverPhoto" :error="form.errors.cover_photo" />
            </div>
            <Button type="submit" variant="primary" :loading="form.processing">{{ t('Save') }}</Button>
        </form>
    </Card>
</template>
