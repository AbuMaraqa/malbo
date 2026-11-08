<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import MultiSelect from '@/Components/form/MultiSelect.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

export interface UserForm {
    name: string;
    email: string;
    phone: string | null;
    password: string;
    roles: number[];
    branch_id: number | null;
}

const props = defineProps<{ form: InertiaForm<UserForm>; roleOptions: Option[]; branchOptions: Option[]; editing?: boolean }>();

/** A failure on one chosen role arrives as `roles.0`. */
const rolesError = computed(() => {
    const errors = props.form.errors as Record<string, string | undefined>;
    return errors.roles ?? Object.entries(errors).find(([key]) => key.startsWith('roles.'))?.[1];
});
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-3">
        <Card class="lg:col-span-2" :title="t('User Information')">
            <div class="grid gap-4 md:grid-cols-2">
                <TextInput v-model="form.name" :label="t('Name')" required :maxlength="255" :error="form.errors.name" />
                <TextInput v-model="form.email" :label="t('Email')" type="email" dir="ltr" required :maxlength="255" autocomplete="off" :error="form.errors.email" />
                <TextInput v-model="form.phone" :label="t('Phone')" type="tel" dir="ltr" :maxlength="255" :error="form.errors.phone" />
                <TextInput
                    v-model="form.password"
                    :label="t('Password')"
                    type="password"
                    dir="ltr"
                    :required="!editing"
                    autocomplete="new-password"
                    :error="form.errors.password"
                    :hint="editing ? t('Leave blank to keep the current password.') : undefined"
                />
            </div>
        </Card>

        <div class="space-y-4">
            <Card :title="t('Roles')">
                <MultiSelect v-model="form.roles" :options="roleOptions" required :placeholder="t('Select Roles')" :error="rolesError" />
            </Card>
            <Card :title="t('Branches')">
                <MultiSelect v-model="form.branch_id" single :options="branchOptions" :placeholder="t('Select Branch')" :error="form.errors.branch_id" />
            </Card>
        </div>
    </div>
</template>
