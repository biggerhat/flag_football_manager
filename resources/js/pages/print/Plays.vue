<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download, Printer } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch, watchEffect } from 'vue';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import PlaySheet from '@/components/play/PlaySheet.vue';
import { Button } from '@/components/ui/button';
import type { SheetItem } from '@/lib/diagram';

type PerPage = 1 | 2 | 4 | 6 | 8;
type Style = 'sheets' | 'callsheet' | 'wristband';
type Look = 'color' | 'bw' | 'dark';

const props = defineProps<{
    kind: 'play' | 'formation';
    plays: SheetItem[];
    team: string;
    lineup: Record<string, string>;
}>();

const isFormation = props.kind === 'formation';

// Options are remembered per kind of printout, so the next print starts where you left off.
const storageKey = `print-options:${props.kind}`;
const query = new URLSearchParams(window.location.search);
const readJson = (raw: string | null): Record<string, unknown> => {
    try {
        return JSON.parse(raw ?? '{}');
    } catch {
        return {};
    }
};
// Options passed in the URL (PDF downloads) win over what this browser remembers.
const saved: Record<string, unknown> = (() => {
    let stored: string | null = null;

    try {
        stored = localStorage.getItem(storageKey);
    } catch {
        // Storage can be unavailable; fall back to defaults.
    }

    return { ...readJson(stored), ...readJson(query.get('opts')) };
})();
const remembered = <T>(key: string, fallback: T): T =>
    key in saved ? (saved[key] as T) : fallback;

const requestedStyle =
    (saved.style as string | undefined) ?? query.get('style');

const style = ref<Style>(
    !isFormation &&
        (requestedStyle === 'callsheet' || requestedStyle === 'wristband')
        ? requestedStyle
        : 'sheets',
);
const wristband = ref<12 | 16 | 20 | 24>(remembered('wristband', 20));
const look = ref<Look>(
    remembered<Look>('look', remembered('color', true) ? 'color' : 'bw'),
);
const largeText = ref(remembered('largeText', false));
// Game plans keep their calling order instead of grouping by category.
const grouped = ref(query.get('grouped') !== '0');
const perPage = ref<PerPage>(
    remembered<PerPage>('perPage', props.plays.length > 1 ? 4 : 1),
);
const landscape = ref(remembered('landscape', false));
const paper = ref<'letter' | 'a4'>(remembered('paper', 'letter'));
const showNotes = ref(remembered('showNotes', true));
const showAssignments = ref(remembered('showAssignments', !isFormation));
const showFormation = ref(remembered('showFormation', true));
const showDetails = ref(remembered('showDetails', false));
const showRouteLabels = ref(remembered('showRouteLabels', true));
const showNames = ref(remembered('showNames', false));
const showPositions = ref(remembered('showPositions', true));
const showThumbnails = ref(remembered('showThumbnails', true));
const showTeam = ref(remembered('showTeam', true));
const teamName = ref(remembered('teamName', props.team));

function currentOptions() {
    return {
        perPage: perPage.value,
        landscape: landscape.value,
        paper: paper.value,
        look: look.value,
        wristband: wristband.value,
        largeText: largeText.value,
        showNotes: showNotes.value,
        showAssignments: showAssignments.value,
        showFormation: showFormation.value,
        showDetails: showDetails.value,
        showRouteLabels: showRouteLabels.value,
        showPositions: showPositions.value,
        showNames: showNames.value,
        showThumbnails: showThumbnails.value,
        showTeam: showTeam.value,
        teamName: teamName.value,
    };
}

watch(
    [
        perPage,
        landscape,
        paper,
        look,
        wristband,
        largeText,
        showNotes,
        showAssignments,
        showFormation,
        showDetails,
        showRouteLabels,
        showPositions,
        showNames,
        showThumbnails,
        showTeam,
        teamName,
    ],
    () => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(currentOptions()));
        } catch {
            // Storage can be unavailable (private windows); settings just won't persist.
        }
    },
);

/** One tap to a clean, player-friendly sheet: name, diagram and assignments only. */
function minimal() {
    showNotes.value = false;
    showFormation.value = false;
    showDetails.value = false;
    showTeam.value = false;
    showRouteLabels.value = false;
    showAssignments.value = !isFormation;
}

function everything() {
    showNotes.value = true;
    showFormation.value = true;
    showDetails.value = true;
    showTeam.value = true;
    showRouteLabels.value = true;
    showAssignments.value = !isFormation;
}

const pages = computed(() => {
    const chunks: SheetItem[][] = [];

    for (let i = 0; i < props.plays.length; i += perPage.value) {
        chunks.push(props.plays.slice(i, i + perPage.value));
    }

    return chunks;
});

/** Grid shape (columns x rows) for each plays-per-page choice. Cells are equal portions of the page. */
const gridLayouts: Record<
    PerPage,
    { portrait: [number, number]; landscape: [number, number] }
> = {
    1: { portrait: [1, 1], landscape: [1, 1] },
    2: { portrait: [1, 2], landscape: [2, 1] },
    4: { portrait: [2, 2], landscape: [2, 2] },
    6: { portrait: [2, 3], landscape: [3, 2] },
    8: { portrait: [2, 4], landscape: [4, 2] },
};

const grid = computed(
    () =>
        gridLayouts[perPage.value][landscape.value ? 'landscape' : 'portrait'],
);

const PAPER_IN = { letter: [8.5, 11], a4: [8.268, 11.693] } as const;
const MARGIN_IN = 0.5;

/** Printable area of one page in inches (a hair short so a page never spills onto a blank one). */
const area = computed(() => {
    const [w, h] = PAPER_IN[paper.value];
    const [pw, ph] = landscape.value ? [h, w] : [w, h];

    return { w: pw - MARGIN_IN * 2, h: ph - MARGIN_IN * 2 - 0.04 };
});

const cellWide = computed(() => {
    const [cols, rows] = grid.value;
    const headerAllowance = showTeam.value ? 0.3 : 0;

    return (
        area.value.w / cols > ((area.value.h - headerAllowance) / rows) * 1.15
    );
});

const size = computed(
    () =>
        ({ 1: 'xl', 2: 'lg', 4: 'md', 6: 'sm', 8: 'sm' } as const)[
            perPage.value
        ],
);

/** Call sheet: numbered plays grouped by category, so a play can be called by name or number. */
const categoryOrder = ['run', 'pass', 'trick', 'defense', 'other'];
const callGroups = computed(() => {
    let n = 0;
    const groups = !grouped.value
        ? [
              {
                  category: 'Game plan',
                  items: props.plays.map((play) => ({ play, number: 0 })),
              },
          ]
        : categoryOrder
              .map((category) => ({
                  category,
                  items: props.plays
                      .filter((p) => (p.category ?? 'other') === category)
                      .map((play) => ({ play, number: 0 })),
              }))
              .filter((g) => g.items.length);

    groups.forEach((g) => g.items.forEach((item) => (item.number = ++n)));

    return groups;
});

/** Wristband cards: tiny numbered plays, numbered to match the call sheet. */
const wristGrids: Record<
    number,
    { portrait: [number, number]; landscape: [number, number] }
> = {
    12: { portrait: [3, 4], landscape: [4, 3] },
    16: { portrait: [4, 4], landscape: [4, 4] },
    20: { portrait: [4, 5], landscape: [5, 4] },
    24: { portrait: [4, 6], landscape: [6, 4] },
};
const wristGrid = computed(
    () =>
        wristGrids[wristband.value][landscape.value ? 'landscape' : 'portrait'],
);
const wristPages = computed(() => {
    const numbered = callGroups.value.flatMap((g) => g.items);
    const pages: (typeof numbered)[] = [];

    for (let i = 0; i < numbered.length; i += wristband.value) {
        pages.push(numbered.slice(i, i + wristband.value));
    }

    return pages;
});

const pageStyle = document.createElement('style');
document.head.appendChild(pageStyle);
watchEffect(() => {
    pageStyle.textContent = `@page { size: ${paper.value} ${landscape.value ? 'landscape' : 'portrait'}; margin: 0.5in; }`;
});
// A dark printout paints the whole sheet, margins included.
watchEffect(() => {
    document.documentElement.style.backgroundColor =
        look.value === 'dark' ? '#000' : '';
});
onBeforeUnmount(() => {
    pageStyle.remove();
    document.documentElement.style.backgroundColor = '';
});

const options = computed(() =>
    [
        {
            label: 'Assignments',
            model: showAssignments,
            show: !isFormation && style.value === 'sheets',
        },
        {
            label: 'Route names',
            model: showRouteLabels,
            show: !isFormation && style.value === 'sheets',
        },
        {
            label: 'Player names',
            model: showNames,
            show:
                !isFormation &&
                style.value === 'sheets' &&
                Object.keys(props.lineup).length > 0,
        },
        {
            label: 'Run / difficulty',
            model: showDetails,
            show: !isFormation && style.value === 'sheets',
        },
        {
            label: 'Formation',
            model: showFormation,
            show: !isFormation && style.value !== 'wristband',
        },
        {
            label: isFormation ? 'Description' : 'Notes',
            model: showNotes,
            show: style.value === 'sheets',
        },
        {
            label: 'Position labels',
            model: showPositions,
            show: style.value === 'wristband',
        },
        {
            label: 'Diagrams',
            model: showThumbnails,
            show: style.value === 'callsheet',
        },
        {
            label: 'Group by category',
            model: grouped,
            show: style.value !== 'sheets',
        },
        {
            label: 'Team name',
            model: showTeam,
            show: style.value !== 'wristband',
        },
        {
            label: 'Large text',
            model: largeText,
            show: style.value !== 'wristband',
        },
        { label: 'Landscape', model: landscape, show: true },
    ].filter((o) => o.show),
);

function print() {
    window.print();
}

/** Ask the server to render this exact printout (with these options) to a PDF file. */
function downloadPdf() {
    const params = new URLSearchParams(window.location.search);
    params.set(
        'opts',
        JSON.stringify({ ...currentOptions(), style: style.value }),
    );
    window.location.href = `/pdf?to=${encodeURIComponent(`${window.location.pathname}?${params}`)}`;
}

const theme = computed(
    () => ({ color: 'paper', bw: 'bw', dark: 'dark' } as const)[look.value],
);
const pageSurface = computed(() =>
    look.value === 'dark'
        ? 'bg-black text-white [print-color-adjust:exact]'
        : 'bg-white text-black',
);

const segment = 'border-input rounded-md border px-2 py-1 text-sm';
</script>

<template>
    <Head :title="isFormation ? 'Print formations' : 'Print plays'" />

    <div class="min-h-screen" :class="pageSurface">
        <div
            class="flex flex-wrap items-center gap-4 border-b bg-neutral-100 p-3 text-sm print:hidden"
        >
            <label v-if="!isFormation" class="flex items-center gap-1">
                Style
                <select v-model="style" :class="segment">
                    <option value="sheets">Play diagrams</option>
                    <option value="callsheet">Call sheet</option>
                    <option value="wristband">Wristband cards</option>
                </select>
            </label>
            <label v-if="style === 'wristband'" class="flex items-center gap-1">
                Cards per page
                <select v-model.number="wristband" :class="segment">
                    <option :value="12">12</option>
                    <option :value="16">16</option>
                    <option :value="20">20</option>
                    <option :value="24">24</option>
                </select>
            </label>
            <label class="flex items-center gap-1">
                Look
                <select v-model="look" :class="segment">
                    <option value="color">Color</option>
                    <option value="bw">Black &amp; white</option>
                    <option value="dark">Dark</option>
                </select>
            </label>
            <label v-if="style === 'sheets'" class="flex items-center gap-1">
                Plays per page
                <select v-model.number="perPage" :class="segment">
                    <option :value="1">1</option>
                    <option :value="2">2</option>
                    <option :value="4">4</option>
                    <option :value="6">6</option>
                    <option :value="8">8</option>
                </select>
            </label>
            <label class="flex items-center gap-1">
                Paper
                <select v-model="paper" :class="segment">
                    <option value="letter">Letter</option>
                    <option value="a4">A4</option>
                </select>
            </label>
            <label
                v-for="o in options"
                :key="o.label"
                class="flex items-center gap-1"
            >
                <input v-model="o.model.value" type="checkbox" /> {{ o.label }}
            </label>
            <input
                v-if="showTeam"
                v-model="teamName"
                :class="[segment, 'w-40']"
                placeholder="Team name"
                aria-label="Team name"
            />
            <span v-if="style === 'sheets'" class="flex gap-1">
                <Button variant="outline" size="sm" @click="minimal"
                    >Minimal</Button
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

        <p v-if="!plays.length" class="p-8 text-center text-neutral-600">
            Nothing selected.
        </p>

        <!-- call sheet -->
        <section
            v-if="style === 'callsheet' && plays.length"
            class="mx-auto my-4 border shadow print:m-0 print:border-0 print:shadow-none"
            :class="[pageSurface, largeText ? 'text-[1.3em]' : '']"
            :style="{ width: `${area.w}in` }"
        >
            <div
                v-if="showTeam"
                class="flex justify-between border-b border-neutral-400 pb-1 text-xs text-neutral-600"
            >
                <span>{{ teamName }}</span>
                <span>Call sheet</span>
            </div>
            <div class="mt-2 columns-2 gap-6">
                <div
                    v-for="group in callGroups"
                    :key="group.category"
                    class="mb-3 break-inside-avoid"
                >
                    <h2
                        class="mb-1 border-b-2 border-black text-sm font-bold tracking-wide uppercase"
                    >
                        {{ group.category }}
                    </h2>
                    <div
                        v-for="item in group.items"
                        :key="item.play.id"
                        class="flex break-inside-avoid items-center gap-2 border-b border-neutral-200 py-1"
                    >
                        <span
                            class="w-7 shrink-0 text-right text-lg font-bold text-neutral-500"
                            >{{ item.number }}</span
                        >
                        <div class="min-w-0 flex-1">
                            <div class="text-lg leading-tight font-bold">
                                {{ item.play.name }}
                            </div>
                            <div
                                v-if="showFormation && item.play.formation"
                                class="text-xs text-neutral-600"
                            >
                                {{ item.play.formation }}
                            </div>
                        </div>
                        <div
                            v-if="showThumbnails"
                            class="w-14 shrink-0 overflow-hidden rounded border border-neutral-400"
                        >
                            <DiagramCanvas
                                :diagram="item.play.diagram"
                                :theme="theme"
                                :show-labels="false"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- wristband cards -->
        <template v-else-if="style === 'wristband' && plays.length">
            <section
                v-for="(page, pageIndex) in wristPages"
                :key="pageIndex"
                class="mx-auto my-4 grid border shadow print:m-0 print:border-0 print:shadow-none"
                :class="[
                    pageSurface,
                    pageIndex < wristPages.length - 1
                        ? 'print:break-after-page'
                        : '',
                ]"
                :style="{
                    width: `${area.w}in`,
                    height: `${area.h}in`,
                    gridTemplateColumns: `repeat(${wristGrid[0]}, minmax(0, 1fr))`,
                    gridTemplateRows: `repeat(${wristGrid[1]}, minmax(0, 1fr))`,
                }"
            >
                <div
                    v-for="item in page"
                    :key="item.play.id"
                    class="flex min-h-0 flex-col overflow-hidden border border-dashed border-neutral-400 p-1"
                >
                    <div
                        class="flex shrink-0 items-baseline gap-1 leading-tight"
                    >
                        <span class="text-sm font-black">{{
                            item.number
                        }}</span>
                        <span class="truncate text-[9px] font-bold">{{
                            item.play.name
                        }}</span>
                    </div>
                    <div class="relative min-h-0 flex-1">
                        <div class="absolute inset-0">
                            <DiagramCanvas
                                :diagram="item.play.diagram"
                                :theme="theme"
                                :show-labels="showPositions"
                                fit
                            />
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <!-- diagram sheets -->
        <template v-else>
            <section
                v-for="(page, pageIndex) in pages"
                :key="pageIndex"
                class="mx-auto my-4 flex flex-col gap-2 border p-0 shadow print:m-0 print:border-0 print:shadow-none"
                :class="[
                    pageSurface,
                    pageIndex < pages.length - 1
                        ? 'print:break-after-page'
                        : '',
                ]"
                :style="{ width: `${area.w}in`, height: `${area.h}in` }"
            >
                <div
                    v-if="showTeam"
                    class="flex shrink-0 justify-between border-b border-neutral-400 pb-1 text-xs text-neutral-600"
                >
                    <span>{{ teamName }}</span>
                    <span>Page {{ pageIndex + 1 }} of {{ pages.length }}</span>
                </div>
                <div
                    class="grid min-h-0 flex-1 gap-4"
                    :style="{
                        gridTemplateColumns: `repeat(${grid[0]}, minmax(0, 1fr))`,
                        gridTemplateRows: `repeat(${grid[1]}, minmax(0, 1fr))`,
                    }"
                >
                    <PlaySheet
                        v-for="play in page"
                        :key="play.id"
                        :play="play"
                        :theme="theme"
                        :large="largeText"
                        :dark="look === 'dark'"
                        :size="size"
                        :wide="cellWide"
                        :show-notes="showNotes"
                        :show-assignments="showAssignments"
                        :show-formation="showFormation"
                        :show-details="showDetails"
                        :show-route-labels="showRouteLabels && !isFormation"
                        :names="showNames ? lineup : undefined"
                    />
                </div>
            </section>
        </template>
    </div>
</template>
