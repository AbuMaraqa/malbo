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

const props = defineProps<{ user: Omit<UserForm, 'password'> & { id: number }; roleOptions: Option[]; branchOptions: Option[] }>();

const { id: _id, ...values } = props.user;
const form = useForm<UserForm>({ ...values, password: '' });
</script>

<template>
    <PageHeader :title="t('Edit User')" :parents="[{ title: t('Users List'), href: route('users.index') }]" />
    <form @submit.prevent="form.put(route('users.update', user.id))">
        <UserFields :form="form" :role-options="roleOptions" :branch-options="branchOptions" editing />
        <FormActions :processing="form.processing" :cancel-href="route('users.index')" :submit-label="t('Update')" />
    </form>
</template>
