import { onBeforeUnmount, ref } from 'vue';
import { PREVIEW_SPEED, pathLength } from '@/lib/diagram';
import type { Diagram } from '@/lib/diagram';

/** Seconds the finished play rests on screen before the preview returns to the start. */
const HOLD_SECONDS = 0.8;

/**
 * Drives the animated preview: `elapsed` is seconds since the play started, or null when idle.
 * Everyone moves at the same speed, so longer routes simply take longer.
 */
export function usePlayPreview(diagram: () => Diagram) {
    const elapsed = ref<number | null>(null);
    let frame = 0;
    let startedAt = 0;
    let duration = 0;

    function tick(now: number) {
        const seconds = (now - startedAt) / 1000;

        if (seconds > duration + HOLD_SECONDS) {
            stop();

            return;
        }

        elapsed.value = seconds;
        frame = requestAnimationFrame(tick);
    }

    function start() {
        const longest = Math.max(
            0,
            ...diagram()
                .routes.filter((r) => r.kind !== 'handoff')
                .map((r) => pathLength(r.points)),
        );

        if (!longest) {
            return;
        }

        duration = longest / PREVIEW_SPEED;
        startedAt = performance.now();
        elapsed.value = 0;
        frame = requestAnimationFrame(tick);
    }

    function stop() {
        cancelAnimationFrame(frame);
        elapsed.value = null;
    }

    onBeforeUnmount(stop);

    return { elapsed, start, stop };
}
