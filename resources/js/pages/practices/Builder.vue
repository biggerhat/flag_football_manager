<script setup lang="ts">
import { useCanEdit } from '@/composables/useCanEdit';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Plus, Printer, Save, Timer } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActivityLibrary from '@/components/practice/ActivityLibrary.vue';
import AttendanceList from '@/components/practice/AttendanceList.vue';
import PracticeDetails from '@/components/practice/PracticeDetails.vue';
import SegmentCard from '@/components/practice/SegmentCard.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { uid } from '@/lib/diagram';
import type { PlayRecord } from '@/lib/diagram';
import { index, run, store, update } from '@/routes/practices';
import { practice as printPractice } from '@/routes/print';
import type {
    DragPayload,
    Drill,
    PracticeRecord,
    RosterPlayer,
    Segment,
} from '@/types/practice';

const canEdit = useCanEdit();
const props = defineProps<{
    practice: PracticeRecord | null;
    drills: Drill[];
    plays: PlayRecord[];
    roster: RosterPlayer[];
    present: number[];
    defaultDate: string | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Practices', href: index() }] },
});

const form = useForm({
    name: props.practice?.name ?? 'Practice',
    date: props.practice?.date?.slice(0, 10) ?? props.defaultDate ?? '',
    start_time: props.practice?.start_time?.slice(0, 5) ?? '',
    duration: props.practice?.duration ?? 60,
    location: props.practice?.location ?? '',
    objectives: props.practice?.objectives ?? '',
    notes: props.practice?.notes ?? '',
    review_notes: props.practice?.review_notes ?? '',
    present: [...props.present],
    segments: [] as Omit<Segment, 'key'>[],
});

const segments = ref<Segment[]>(
    (props.practice?.segments ?? []).map((s) => ({
        key: uid('s'),
        title: s.title,
        duration: s.duration,
        notes: s.notes,
        drill_id: s.drill_id,
        play_id: s.play_id,
    })),
);

const playById = computed(() => new Map(props.plays.map((p) => [p.id, p])));
const drillById = computed(() => new Map(props.drills.map((d) => [d.id, d])));

const total = computed(() =>
    segments.value.reduce((sum, s) => sum + (Number(s.duration) || 0), 0),
);
const remaining = computed(() => form.duration - total.value);

/** Start offset in minutes for each segment, derived from sequential durations. */
const starts = computed(() => {
    let at = 0;

    return segments.value.map((s) => {
        const start = at;
        at += Number(s.duration) || 0;

        return start;
    });
});

const clock = (minutes: number): string =>
    `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}`;

const warnings = computed(() => {
    const list: string[] = [];

    if (remaining.value < 0) {
        list.push(
            `Schedule runs ${-remaining.value} min over the planned ${form.duration} min.`,
        );
    }

    if (segments.value.some((s) => !s.title.trim())) {
        list.push('Some activities have no title.');
    }

    return list;
});

function addDrill(drill: Drill, at = segments.value.length) {
    segments.value.splice(at, 0, {
        key: uid('s'),
        title: drill.name,
        duration: drill.duration,
        notes: null,
        drill_id: drill.id,
        play_id: null,
    });
}

function addPlay(play: PlayRecord, at = segments.value.length) {
    segments.value.splice(at, 0, {
        key: uid('s'),
        title: `Run: ${play.name}`,
        duration: 10,
        notes: null,
        drill_id: null,
        play_id: play.id,
    });
}

function addBlank() {
    segments.value.push({
        key: uid('s'),
        title: 'New activity',
        duration: 10,
        notes: null,
        drill_id: null,
        play_id: null,
    });
}

function move(from: number, to: number) {
    if (to < 0 || to >= segments.value.length || from === to) {
        return;
    }

    const [item] = segments.value.splice(from, 1);
    segments.value.splice(to, 0, item);
}

// Reordering uses pointer events on the grip, so it works with a finger as well as a mouse.
const dropIndex = ref<number | null>(null);
const cards = new Map<number, HTMLElement>();
const reordering = ref<number | null>(null);

function setCard(index: number, el: unknown) {
    const node = (el as { $el?: unknown } | null)?.$el ?? el;

    if (node instanceof HTMLElement) {
        cards.set(index, node);
    } else {
        cards.delete(index);
    }
}

function startReorder(event: PointerEvent, index: number) {
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    reordering.value = index;
    dropIndex.value = index;
}

function reorderTo(event: PointerEvent) {
    if (reordering.value === null) {
        return;
    }

    let target = segments.value.length - 1;

    for (const [index, el] of [...cards.entries()].sort(
        (a, b) => a[0] - b[0],
    )) {
        const box = el.getBoundingClientRect();

        if (event.clientY < box.top + box.height / 2) {
            target = index;
            break;
        }
    }

    dropIndex.value = target;
}

function endReorder(event: PointerEvent) {
    if (reordering.value !== null && dropIndex.value !== null) {
        move(reordering.value, dropIndex.value);
    }

    reordering.value = null;
    dropIndex.value = null;
    (event.currentTarget as HTMLElement).releasePointerCapture?.(
        event.pointerId,
    );
}

/** Library items are dropped on the timeline; a play dropped on a card attaches to it. */
function drop(event: DragEvent, at: number, onCard: boolean) {
    dropIndex.value = null;
    const raw = event.dataTransfer?.getData('application/json');

    if (!raw) {
        return;
    }

    const payload = JSON.parse(raw) as DragPayload;

    if (payload.type === 'drill') {
        const drill = drillById.value.get(payload.id);

        if (drill) {
            addDrill(drill, at);
        }
    } else {
        const play = playById.value.get(payload.id);

        if (!play) {
            return;
        }

        if (onCard && segments.value[at]) {
            segments.value[at].play_id = play.id;
        } else {
            addPlay(play, at);
        }
    }
}

function save() {
    form.transform((data) => ({
        ...data,
        date: data.date || null,
        start_time: data.start_time || null,
        segments: segments.value.map(({ key, ...rest }) => ({
            ...rest,
            duration: Number(rest.duration) || 1,
        })),
    })).submit(
        props.practice ? 'put' : 'post',
        props.practice ? update.url(props.practice.id) : store.url(),
    );
}
</script>

<template>
    <Head :title="practice ? 'Edit practice' : 'New practice'" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">
                {{ practice ? 'Edit practice' : 'New practice' }}
                <Badge
                    v-if="practice?.is_template"
                    variant="secondary"
                    class="ml-2"
                    >Template</Badge
                >
            </h1>
            <div class="flex gap-2">
                <Button
                    v-if="
                        practice &&
                        !practice.is_template &&
                        practice.segments.length
                    "
                    size="sm"
                    as-child
                >
                    <Link :href="run(practice.id)"><Timer /> Run practice</Link>
                </Button>
                <Button v-if="practice" variant="outline" size="sm" as-child>
                    <a :href="printPractice.url(practice.id)" target="_blank"
                        ><Printer /> Print</a
                    >
                </Button>
                <Button
                    v-if="canEdit"
                    size="sm"
                    :disabled="form.processing"
                    @click="save"
                    ><Save /> Save practice</Button
                >
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)_300px]">
            <PracticeDetails
                v-model:name="form.name"
                v-model:date="form.date"
                v-model:start-time="form.start_time"
                v-model:duration="form.duration"
                v-model:location="form.location"
                v-model:objectives="form.objectives"
                v-model:notes="form.notes"
                v-model:review-notes="form.review_notes"
                :errors="form.errors"
            />

            <AttendanceList
                v-if="roster.length"
                v-model="form.present"
                :roster="roster"
                :can-edit="canEdit"
            />

            <!-- timeline -->
            <div class="flex flex-col gap-2">
                <div
                    class="flex items-center justify-between rounded-xl border px-3 py-2 text-sm"
                >
                    <span class="font-medium"
                        >Total {{ total }} / {{ form.duration }} min</span
                    >
                    <span
                        :class="
                            remaining < 0
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        "
                    >
                        {{
                            remaining >= 0
                                ? `${remaining} min left`
                                : `${-remaining} min over`
                        }}
                    </span>
                </div>
                <div
                    v-for="w in warnings"
                    :key="w"
                    class="flex items-center gap-2 rounded-xl border border-warning/50 bg-warning/10 px-3 py-2 text-sm"
                >
                    <AlertTriangle class="size-4 text-warning" /> {{ w }}
                </div>
                <InputError :message="form.errors.segments" />

                <p
                    v-if="!segments.length"
                    class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                    @dragover.prevent
                    @drop.prevent="drop($event, 0, false)"
                >
                    Drag drills and plays here, or use the + buttons in the
                    library.
                </p>

                <SegmentCard
                    v-for="(segment, i) in segments"
                    :key="segment.key"
                    :ref="(el) => setCard(i, el)"
                    v-model:title="segment.title"
                    v-model:duration="segment.duration"
                    v-model:notes="segment.notes"
                    :segment="segment"
                    :start="clock(starts[i])"
                    :first="i === 0"
                    :last="i === segments.length - 1"
                    :drill="
                        segment.drill_id
                            ? (drillById.get(segment.drill_id) ?? null)
                            : null
                    "
                    :play="
                        segment.play_id
                            ? (playById.get(segment.play_id) ?? null)
                            : null
                    "
                    :class="[
                        dropIndex === i ? 'border-primary bg-primary/5' : '',
                        reordering === i ? 'opacity-50' : '',
                    ]"
                    @dragover.prevent="dropIndex = i"
                    @dragleave="dropIndex = null"
                    @drop.prevent.stop="drop($event, i, true)"
                    @move-up="move(i, i - 1)"
                    @move-down="move(i, i + 1)"
                    @remove="segments.splice(i, 1)"
                    @detach-play="segment.play_id = null"
                    @reorder-start="startReorder($event, i)"
                    @reorder-move="reorderTo"
                    @reorder-end="endReorder"
                />

                <div
                    v-if="segments.length"
                    class="rounded-xl border border-dashed p-3 text-center text-xs text-muted-foreground"
                    :class="
                        dropIndex === segments.length
                            ? 'border-primary bg-primary/5'
                            : ''
                    "
                    @dragover.prevent="dropIndex = segments.length"
                    @dragleave="dropIndex = null"
                    @drop.prevent="drop($event, segments.length, false)"
                >
                    Drop here to add to the end
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="self-start"
                    @click="addBlank"
                    ><Plus /> Blank activity</Button
                >
            </div>

            <ActivityLibrary
                :drills="drills"
                :plays="plays"
                @add-drill="addDrill($event)"
                @add-play="addPlay($event)"
            />
        </div>
    </div>
</template>
