import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import { useDialog } from '@/composables/useDialog';

/**
 * Asks before leaving a page with unsaved edits, both for in-app navigation
 * and for closing or reloading the tab. Wrap your own saving requests in
 * `allow()` so they are not mistaken for leaving.
 */
export function useUnsavedGuard(isDirty: () => boolean) {
    let bypass = false;
    const { confirm } = useDialog();

    // The dialog is asynchronous, so cancel the visit now and replay it if the coach chooses to leave.
    const stop = router.on('before', (event) => {
        if (bypass || !isDirty()) {
            return;
        }

        const { url, method, data, replace, preserveScroll, preserveState } =
            event.detail.visit;

        void confirm({
            title: 'Leave without saving?',
            description: 'You have unsaved changes that will be lost.',
            confirmText: 'Leave',
            destructive: true,
        }).then((leave) => {
            if (leave) {
                allow(() =>
                    router.visit(url, {
                        method,
                        data,
                        replace,
                        preserveScroll,
                        preserveState,
                    }),
                );
            }
        });

        return false;
    });

    const onUnload = (event: BeforeUnloadEvent) => {
        if (isDirty()) {
            event.preventDefault();
        }
    };

    onMounted(() => window.addEventListener('beforeunload', onUnload));
    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', onUnload);
        stop();
    });

    function allow<T>(request: () => T): T {
        bypass = true;

        try {
            return request();
        } finally {
            setTimeout(() => (bypass = false), 0);
        }
    }

    return { allow };
}
