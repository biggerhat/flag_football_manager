import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * POST helper for "create a copy" buttons: while one request is in flight further
 * clicks are ignored, so a double tap cannot make two copies.
 */
export function useOnce() {
    const pending = ref(false);

    function post(url: string) {
        if (pending.value) {
            return;
        }

        router.post(
            url,
            {},
            {
                onStart: () => (pending.value = true),
                onFinish: () => (pending.value = false),
            },
        );
    }

    return { pending, post };
}
