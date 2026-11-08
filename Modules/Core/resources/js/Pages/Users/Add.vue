<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import UserFields, { type UserForm } from './UserFields.vue';

defineOptions({ layout: AdminLayout });
defineProps<{ roleOptions: Option[]; branchOptions: Option[] }>();

const form = useForm<UserForm>({
    name: '',
    email: '',
    phone: '',
    password: '',
    roles: [],
    branch_id: null,
});
</script>

<template>
    <PageHeader :title="t('Add User')" :parents="[{ title: t('Users List'), href: route('users.index') }]" />
    <form @submit.prevent="form.post(route('users.store'))">
        <UserFields :form="form" :role-options="roleOptions" :branch-options="branchOptions" />
        <FormActions :processing="form.processing" :cancel-href="route('users.index')" :submit-label="t('Add User')" />
    </form>
</template>
