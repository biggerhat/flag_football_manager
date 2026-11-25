import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { restore } from '@/routes';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        const { undo } = data;

        toast[data.type](data.message, {
            ...(undo && {
                duration: 10000,
                action: {
                    label: 'Undo',
                    onClick: () =>
                        router.post(
                            restore.url({ kind: undo.kind, id: undo.id }),
                            {},
                            { preserveScroll: true },
                        ),
                },
            }),
        });
    });
}
