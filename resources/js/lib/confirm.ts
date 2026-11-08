import { reactive } from 'vue';

export interface ConfirmOptions {
    title: string;
    description?: string;
    confirmLabel?: string;
    /** The user must type this text before confirming (for actions that cannot be undone). */
    typeToConfirm?: string;
}

export const confirmState = reactive<{
    open: boolean;
    options: ConfirmOptions | null;
    resolve: ((confirmed: boolean) => void) | null;
}>({ open: false, options: null, resolve: null });

/** Ask before a destructive action: `if (await confirm({ title })) { ... }`. */
export function confirm(options: ConfirmOptions): Promise<boolean> {
    return new Promise((resolve) => {
        confirmState.options = options;
        confirmState.resolve = resolve;
        confirmState.open = true;
    });
}
