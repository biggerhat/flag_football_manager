<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pause, Play, RotateCcw, SkipBack, SkipForward } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useWakeLock } from '@/composables/useWakeLock';
import { edit, index } from '@/routes/practices';

type Segment = {
    id: number;
    title: string;
    duration: number;
    notes: string | null;
};

const props = defineProps<{
    practice: { id: number; name: string; segments: Segment[] };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Practices', href: index() }] },
});

useWakeLock();

const current = ref(0);
// Seconds left in the current activity.
const remaining = ref((props.practice.segments[0]?.duration ?? 0) * 60);
const running = ref(false);
let timer: ReturnType<typeof setInterval> | undefined;
let audio: AudioContext | undefined;

const segment = computed(() => props.practice.segments[current.value]);
const upNext = computed(() => props.practice.segments[current.value + 1]);
const finished = computed(() => !segment.value);
const clock = computed(() => {
    const abs = Math.abs(remaining.value);
    const text = `${Math.floor(abs / 60)}:${String(abs % 60).padStart(2, '0')}`;

    return remaining.value < 0 ? `+${text}` : text;
});
const totalLeft = computed(() => {
    const later = props.practice.segments
        .slice(current.value + 1)
        .reduce((sum, s) => sum + s.duration, 0);

    return Math.max(0, Math.ceil(remaining.value / 60)) + later;
});

function beep() {
    try {
        audio ??= new AudioContext();
        const osc = audio.createOscillator();
        osc.frequency.value = 880;
        osc.connect(audio.destination);
        osc.start();
        osc.stop(audio.currentTime + 0.4);
        navigator.vibrate?.([200, 100, 200]);
    } catch {
        // Sound is a convenience; the screen still changes color.
    }
}

function tick() {
    remaining.value -= 1;

    if (remaining.value === 0) {
        beep();
    }
}

function start() {
    audio ??= new AudioContext();
    running.value = true;
    timer = setInterval(tick, 1000);
}

function pause() {
    running.value = false;
    clearInterval(timer);
}

function go(index: number) {
    current.value = Math.min(
        Math.max(index, 0),
        props.practice.segments.length,
    );
    remaining.value =
        (props.practice.segments[current.value]?.duration ?? 0) * 60;

    if (finished.value) {
        pause();
    }
}

onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <Head :title="`Run – ${practice.name}`" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">{{ practice.name }}</h1>
            <Button variant="outline" size="sm" as-child>
                <Link :href="edit(practice.id)">Back to plan</Link>
            </Button>
        </div>

        <p v-if="!practice.segments.length" class="text-muted-foreground">
            This practice has no activities. Add some in the plan first.
        </p>

        <div v-else-if="finished" class="rounded-xl border p-8 text-center">
            <div class="text-3xl font-semibold">Practice complete</div>
            <Button class="mt-4" variant="outline" @click="go(0)">
                <RotateCcw /> Start over
            </Button>
        </div>

        <template v-else>
            <div
                class="flex flex-col items-center gap-2 rounded-xl border p-8 text-center transition-colors"
                :class="
                    remaining < 0
                        ? 'border-destructive bg-destructive/10'
                        : remaining <= 60
                          ? 'border-warning bg-warning/10'
                          : ''
                "
            >
                <div class="text-sm text-muted-foreground">
                    Activity {{ current + 1 }} of {{ practice.segments.length }}
                </div>
                <h2 class="text-3xl font-semibold">{{ segment.title }}</h2>
                <div
                    class="font-mono text-7xl font-bold tabular-nums sm:text-8xl"
                    role="timer"
                    aria-live="off"
                >
                    {{ clock }}
                </div>
                <p
                    v-if="segment.notes"
                    class="max-w-xl whitespace-pre-line text-muted-foreground"
                >
                    {{ segment.notes }}
                </p>
                <div class="mt-2 flex items-center gap-2">
                    <Button
                        size="lg"
                        variant="outline"
                        aria-label="Previous activity"
                        :disabled="current === 0"
                        @click="go(current - 1)"
                    >
                        <SkipBack />
                    </Button>
                    <Button
                        size="lg"
                        class="min-w-32"
                        @click="running ? pause() : start()"
                    >
                        <template v-if="running"><Pause /> Pause</template>
                        <template v-else><Play /> Start</template>
                    </Button>
                    <Button
                        size="lg"
                        variant="outline"
                        aria-label="Next activity"
                        @click="go(current + 1)"
                    >
                        <SkipForward />
                    </Button>
                </div>
            </div>

            <p class="text-sm text-muted-foreground">
                About {{ totalLeft }} min left.
                <template v-if="upNext"
                    >Up next: <strong>{{ upNext.title }}</strong> ({{
                        upNext.duration
                    }}
                    min)</template
                >
            </p>

            <ol class="divide-y rounded-xl border text-sm">
                <li
                    v-for="(s, i) in practice.segments"
                    :key="s.id"
                    class="flex items-center gap-3 p-3"
                    :class="
                        i === current
                            ? 'bg-primary/10 font-medium'
                            : i < current
                              ? 'text-muted-foreground line-through'
                              : ''
                    "
                >
                    <span class="w-6 text-right text-muted-foreground">{{
                        i + 1
                    }}</span>
                    <span class="flex-1">{{ s.title }}</span>
                    <span>{{ s.duration }} min</span>
                </li>
            </ol>
        </template>
    </div>
</template>
