<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download, Printer } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watchEffect } from 'vue';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import { Button } from '@/components/ui/button';
import type { Diagram, PlayRecord } from '@/lib/diagram';

type Segment = {
    id: number;
    title: string;
    duration: number;
    notes: string | null;
    drill: {
        name: string;
        players: number | null;
        equipment: string | null;
        instructions: string | null;
        coaching_points: string | null;
        diagram: Diagram | null;
    } | null;
    play: PlayRecord | null;
};

const props = defineProps<{
    practice: {
        name: string;
        date: string | null;
        start_time: string | null;
        duration: number;
        location: string | null;
        objectives: string | null;
        notes: string | null;
        review_notes: string | null;
        segments: Segment[];
    };
    team: string;
}>();

const color = ref(true);
const showDetails = ref(true);
const showDiagrams = ref(true);
const showNotes = ref(true);
const largeText = ref(false);

/** One tap to a sideline sheet: times and titles only. */
function compact() {
    showDetails.value = false;
    showDiagrams.value = false;
    showNotes.value = false;
}

function everything() {
    showDetails.value = true;
    showDiagrams.value = true;
    showNotes.value = true;
}

const pageStyle = document.createElement('style');
document.head.appendChild(pageStyle);
watchEffect(() => {
    pageStyle.textContent = '@page { size: letter portrait; margin: 0.5in; }';
});
onBeforeUnmount(() => pageStyle.remove());

const startMinutes = computed(() => {
    const [h, m] = (props.practice.start_time ?? '').split(':').map(Number);

    return Number.isFinite(h) && Number.isFinite(m) ? h * 60 + m : null;
});

/** Wall-clock time (3:30 PM) when the practice has a start time, otherwise minutes into practice (0:15). */
const clock = (minutes: number): string => {
    if (startMinutes.value === null) {
        return `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}`;
    }

    const at = (startMinutes.value + minutes) % (24 * 60);
    const hour = Math.floor(at / 60);

    return `${hour % 12 || 12}:${String(at % 60).padStart(2, '0')} ${hour < 12 ? 'AM' : 'PM'}`;
};

const rows = computed(() => {
    let at = 0;

    return props.practice.segments.map((segment) => {
        const start = at;
        at += segment.duration;

        return { segment, start, end: at };
    });
});

const total = computed(() => rows.value.at(-1)?.end ?? 0);

const equipment = computed(() => {
    const items = new Set<string>();

    props.practice.segments.forEach((s) =>
        s.drill?.equipment
            ?.split(',')
            .map((e) => e.trim())
            .filter(Boolean)
            .forEach((e) => items.add(e)),
    );

    return [...items];
});

function print() {
    window.print();
}

function downloadPdf() {
    window.location.href = `/pdf?to=${encodeURIComponent(window.location.pathname + window.location.search)}`;
}
</script>

<template>
    <Head :title="`Print ${practice.name}`" />

    <div class="min-h-screen bg-white text-black">
        <div
            class="flex flex-wrap items-center gap-4 border-b bg-neutral-100 p-3 text-sm print:hidden"
        >
            <label class="flex items-center gap-1"
                ><input v-model="showDetails" type="checkbox" />
                Instructions</label
            >
            <label class="flex items-center gap-1"
                ><input v-model="showDiagrams" type="checkbox" /> Play
                diagrams</label
            >
            <label class="flex items-center gap-1"
                ><input v-model="showNotes" type="checkbox" /> Notes</label
            >
            <label class="flex items-center gap-1"
                ><input v-model="color" type="checkbox" /> Color diagrams</label
            >
            <label class="flex items-center gap-1"
                ><input v-model="largeText" type="checkbox" /> Large text</label
            >
            <span class="flex gap-1">
                <Button variant="outline" size="sm" @click="compact"
                    >Compact</Button
                >
                <Button variant="outline" size="sm" @click="everything"
                    >Everything</Button
                >
            </span>
            <span class="ml-auto flex gap-2">
                <Button variant="outline" size="sm" @click="downloadPdf"
                    ><Download /> Download PDF</Button
                >
                <Button size="sm" @click="print"><Printer /> Print</Button>
            </span>
        </div>

        <main
            class="mx-auto flex max-w-[8.5in] flex-col gap-4 p-6 print:max-w-none print:p-0"
            :class="largeText ? 'text-[1.25em]' : ''"
        >
            <header class="border-b-2 border-black pb-2">
                <p class="text-xs text-neutral-600">{{ team }}</p>
                <h1 class="text-2xl font-bold">{{ practice.name }}</h1>
                <p class="text-sm text-neutral-700">
                    <template v-if="practice.date">{{
                        practice.date
                    }}</template>
                    <template v-if="practice.start_time">
                        · {{ practice.start_time.slice(0, 5) }}</template
                    >
                    <template v-if="practice.location">
                        · {{ practice.location }}</template
                    >
                    · {{ total }} of {{ practice.duration }} min planned
                </p>
            </header>

            <section v-if="practice.objectives">
                <h2 class="text-sm font-bold uppercase">Objectives</h2>
                <p class="text-sm whitespace-pre-line">
                    {{ practice.objectives }}
                </p>
            </section>

            <section v-if="equipment.length">
                <h2 class="text-sm font-bold uppercase">Equipment</h2>
                <p class="text-sm">{{ equipment.join(', ') }}</p>
            </section>

            <section class="flex flex-col">
                <div
                    v-for="row in rows"
                    :key="row.segment.id"
                    class="flex break-inside-avoid gap-3 border-b border-neutral-300 py-2"
                >
                    <div
                        class="shrink-0 font-mono text-sm"
                        :class="startMinutes === null ? 'w-20' : 'w-40'"
                    >
                        {{ clock(row.start) }}–{{ clock(row.end) }}
                        <div class="text-xs text-neutral-600">
                            {{ row.segment.duration }} min
                        </div>
                    </div>
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="font-bold">{{ row.segment.title }}</div>
                        <p
                            v-if="
                                showDetails && row.segment.drill?.instructions
                            "
                            class="whitespace-pre-line"
                        >
                            {{ row.segment.drill.instructions }}
                        </p>
                        <p
                            v-if="
                                showDetails &&
                                row.segment.drill?.coaching_points
                            "
                            class="text-neutral-700"
                        >
                            <span class="font-semibold">Coaching points:</span>
                            {{ row.segment.drill.coaching_points }}
                        </p>
                        <p
                            v-if="row.segment.drill?.equipment"
                            class="text-xs text-neutral-600"
                        >
                            Equipment: {{ row.segment.drill.equipment }}
                        </p>
                        <p
                            v-if="showNotes && row.segment.notes"
                            class="text-neutral-700 italic"
                        >
                            {{ row.segment.notes }}
                        </p>
                    </div>
                    <div
                        v-if="showDiagrams && row.segment.play"
                        class="w-28 shrink-0 text-center text-xs"
                    >
                        <div
                            class="overflow-hidden rounded border border-neutral-400"
                        >
                            <DiagramCanvas
                                :diagram="row.segment.play.diagram"
                                :theme="color ? 'paper' : 'bw'"
                            />
                        </div>
                        {{ row.segment.play.name }}
                    </div>
                    <div
                        v-else-if="showDiagrams && row.segment.drill?.diagram"
                        class="w-28 shrink-0 text-center text-xs"
                    >
                        <div
                            class="overflow-hidden rounded border border-neutral-400"
                        >
                            <DiagramCanvas
                                :diagram="row.segment.drill.diagram"
                                :theme="color ? 'paper' : 'bw'"
                                :show-labels="false"
                            />
                        </div>
                        Drill setup
                    </div>
                </div>
            </section>

            <section v-if="showNotes && practice.notes">
                <h2 class="text-sm font-bold uppercase">Coach notes</h2>
                <p class="text-sm whitespace-pre-line">{{ practice.notes }}</p>
            </section>
        </main>
    </div>
</template>
