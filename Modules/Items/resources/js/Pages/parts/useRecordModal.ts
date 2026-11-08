import type { FormDataType } from '@inertiajs/core';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import { route } from '@/lib/route';

/**
 * The form of a table that adds and edits in a modal (tags, labels, brands). `routes` is the route
 * names' prefix (`tags`), `files` sends the form as multipart for a table with an image.
 */
export function useRecordModal<Form extends FormDataType<Form>>(routes: string, blank: () => Form, files = false) {
    const open = ref(false);
    const editingId = ref<number | null>(null);
    const form = useForm<Form>(blank());

    function show(id: number | null, values: Form): void {
        editingId.value = id;
        form.defaults(values);
        form.reset();
        form.clearErrors();
        open.value = true;
    }

    const add = (): void => show(null, blank());
    const edit = (id: number, values: Form): void => show(id, values);

    function submit(): void {
        const options = { preserveScroll: true, forceFormData: files, onSuccess: () => (open.value = false) };

        if (editingId.value === null) {
            form.transform((data) => data).post(route(`${routes}.store`), options);
        } else {
            // A browser cannot send a file with PUT: Laravel reads the real method from _method.
            form.transform((data) => ({ ...data, _method: 'put' })).post(route(`${routes}.update`, editingId.value), options);
        }
    }

    return { open, editingId, form, add, edit, submit };
}
