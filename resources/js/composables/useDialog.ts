import { ref } from 'vue';

export type DialogRequest =
    | {
          kind: 'confirm';
          title: string;
          description?: string;
          confirmText: string;
          destructive: boolean;
          resolve: (value: boolean) => void;
      }
    | {
          kind: 'prompt';
          title: string;
          description?: string;
          confirmText: string;
          defaultValue: string;
          resolve: (value: string | null) => void;
      };

/** The dialog currently on screen; rendered by AppDialogHost. */
export const activeDialog = ref<DialogRequest | null>(null);

function cancelActive() {
    const current = activeDialog.value;

    if (current?.kind === 'confirm') {
        current.resolve(false);
    } else if (current) {
        current.resolve(null);
    }
}

/**
 * In-app replacements for window.confirm / window.prompt. Each returns a
 * promise, so call sites read the same: `if (await confirm({...})) { ... }`.
 */
export function useDialog() {
    function confirm(options: {
        title: string;
        description?: string;
        confirmText?: string;
        destructive?: boolean;
    }): Promise<boolean> {
        cancelActive();

        return new Promise((resolve) => {
            activeDialog.value = {
                kind: 'confirm',
                title: options.title,
                description: options.description,
                confirmText: options.confirmText ?? 'Confirm',
                destructive: options.destructive ?? false,
                resolve,
            };
        });
    }

    function prompt(options: {
        title: string;
        description?: string;
        defaultValue?: string;
        confirmText?: string;
    }): Promise<string | null> {
        cancelActive();

        return new Promise((resolve) => {
            activeDialog.value = {
                kind: 'prompt',
                title: options.title,
                description: options.description,
                confirmText: options.confirmText ?? 'Save',
                defaultValue: options.defaultValue ?? '',
                resolve,
            };
        });
    }

    return { confirm, prompt };
}
