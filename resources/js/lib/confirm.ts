import { reactive } from 'vue';

export interface ConfirmOptions {
    title: string;
    description?: string;
    confirmLabel?: string;
    /** `danger` (the default) for destructive actions, `primary` for a plain go-ahead. */
    tone?: 'danger' | 'primary';
    /** The user must type this text before confirming (for actions that cannot be undone). */
    typeToConfirm?: string;
}

export const confirmState = reactive<{
    open: boolean;
    options: ConfirmOptions | null;
    resolve: ((confirmed: boolean) => void) | null;
}>({ open: false, options: null, resolve: null });

/** Ask before an action: `if (await confirm({ title })) { ... }`. Destructive by default. */
export function confirm(options: ConfirmOptions): Promise<boolean> {
    return new Promise((resolve) => {
        confirmState.options = options;
        confirmState.resolve = resolve;
        confirmState.open = true;
    });
}
