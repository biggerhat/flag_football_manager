import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Keeps the screen awake while the page is open (sideline use). */
export function useWakeLock() {
    const active = ref(false);
    const supported =
        typeof navigator !== 'undefined' && 'wakeLock' in navigator;
    let sentinel: WakeLockSentinel | null = null;

    async function acquire() {
        if (!supported) {
            return;
        }

        try {
            sentinel = await navigator.wakeLock.request('screen');
            active.value = true;
            sentinel.addEventListener('release', () => {
                active.value = false;
            });
        } catch {
            active.value = false;
        }
    }

    async function release() {
        await sentinel?.release();
        sentinel = null;
        active.value = false;
    }

    function onVisible() {
        if (document.visibilityState === 'visible' && wanted) {
            void acquire();
        }
    }

    let wanted = false;

    async function toggle() {
        wanted = !active.value;
        await (wanted ? acquire() : release());
    }

    onMounted(() => {
        wanted = true;
        void acquire();
        document.addEventListener('visibilitychange', onVisible);
    });

    onBeforeUnmount(() => {
        wanted = false;
        document.removeEventListener('visibilitychange', onVisible);
        void release();
    });

    return { active, supported, toggle };
}
