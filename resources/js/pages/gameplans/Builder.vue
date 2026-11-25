<script setup lang="ts">
import { useCanEdit } from '@/composables/useCanEdit';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    Clipboard,
    Download,
    ListOrdered,
    Plus,
    Printer,
    Save,
    Watch,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PlayRecord } from '@/lib/diagram';
import { offlineSupported, savePagesOffline } from '@/lib/offline';
import { gameday, index, store, update } from '@/routes/gameplans';
import { plays as printPlays } from '@/routes/print';

type GamePlanRecord = {
    id: number;
    name: string;
    opponent: string | null;
    date: string | null;
    notes: string | null;
    location: string | null;
    kickoff: string | null;
    score_for: number | null;
    score_against: number | null;
    play_ids: number[];
};

const canEdit = useCanEdit();
const props = defineProps<{
    gamePlan: GamePlanRecord | null;
    plays: PlayRecord[];
    playbooks: { id: number; name: string }[];
    defaultDate: string | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Game plans', href: index() }] },
});

const form = useForm({
    name: props.gamePlan?.name ?? 'Game plan',
    opponent: props.gamePlan?.opponent ?? '',
    date: props.gamePlan?.date?.slice(0, 10) ?? props.defaultDate ?? '',
    location: props.gamePlan?.location ?? '',
    kickoff: props.gamePlan?.kickoff?.slice(0, 5) ?? '',
    score_for: props.gamePlan?.score_for ?? ('' as number | ''),
    score_against: props.gamePlan?.score_against ?? ('' as number | ''),
    notes: props.gamePlan?.notes ?? '',
    play_ids: [...(props.gamePlan?.play_ids ?? [])],
});

const playById = computed(() => new Map(props.plays.map((p) => [p.id, p])));
const chosen = computed(
    () =>
        form.play_ids
            .map((id) => playById.value.get(id))
            .filter(Boolean) as PlayRecord[],
);

const search = ref('');
const playbookFilter = ref<number | ''>('');
const available = computed(() =>
    props.plays.filter(
        (p) =>
            !form.play_ids.includes(p.id) &&
            (playbookFilter.value === '' ||
                p.playbook_id === playbookFilter.value) &&
            p.name.toLowerCase().includes(search.value.toLowerCase()),
    ),
);

function move(from: number, to: number) {
    if (to < 0 || to >= form.play_ids.length) {
        return;
    }

    const [id] = form.play_ids.splice(from, 1);
    form.play_ids.splice(to, 0, id);
}

const printQuery = computed(() => form.play_ids.join(','));

/** Keeps the game day page and printouts readable at the field without a signal. */
async function saveOffline() {
    const query = (style: string) =>
        printPlays.url({
            query: { ids: printQuery.value, style, grouped: '0' },
        });
    const saved = await savePagesOffline([
        gameday.url(props.gamePlan!.id),
        query('callsheet'),
        query('wristband'),
    ]);

    toast[saved ? 'success' : 'error'](
        saved ? 'Saved for offline use.' : 'Could not save for offline use.',
    );
}

function save() {
    form.transform((data) => ({
        ...data,
        date: data.date || null,
        opponent: data.opponent || null,
        location: data.location || null,
        kickoff: data.kickoff || null,
        score_for: data.score_for === '' ? null : data.score_for,
        score_against: data.score_against === '' ? null : data.score_against,
    })).submit(
        props.gamePlan ? 'put' : 'post',
        props.gamePlan ? update.url(props.gamePlan.id) : store.url(),
    );
}

const textareaClass =
    'border-input bg-background w-full rounded-md border px-3 py-2 text-sm shadow-xs';
</script>

<template>
    <Head :title="gamePlan ? 'Edit game plan' : 'New game plan'" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">
                {{ gamePlan ? 'Edit game plan' : 'New game plan' }}
            </h1>
            <div class="flex flex-wrap gap-2">
                <template v-if="gamePlan && form.play_ids.length">
                    <Button
                        v-if="offlineSupported()"
                        variant="outline"
                        size="sm"
                        @click="saveOffline"
                        ><Download /> Save offline</Button
                    >
                    <Button size="sm" as-child>
                        <Link :href="gameday(gamePlan.id)"
                            ><Clipboard /> Game day</Link
                        >
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a
                            :href="
                                printPlays.url({
                                    query: {
                                        ids: printQuery,
                                        style: 'callsheet',
                                        grouped: '0',
                                    },
                                })
                            "
                            target="_blank"
                            ><ListOrdered /> Call sheet</a
                        >
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a
                            :href="
                                printPlays.url({
                                    query: {
                                        ids: printQuery,
                                        style: 'wristband',
                                        grouped: '0',
                                    },
                                })
                            "
                            target="_blank"
                            ><Watch /> Wristband</a
                        >
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a
                            :href="
                                printPlays.url({ query: { ids: printQuery } })
                            "
                            target="_blank"
                            ><Printer /> Play sheets</a
                        >
                    </Button>
                </template>
                <Button
                    v-if="canEdit"
                    size="sm"
                    :disabled="form.processing"
                    @click="save"
                    ><Save /> Save game plan</Button
                >
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)_300px]">
            <div class="flex flex-col gap-3 rounded-xl border p-3">
                <div class="grid gap-2">
                    <Label for="name">Title</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="opponent">Opponent</Label>
                    <Input id="opponent" v-model="form.opponent" />
                </div>
                <div class="grid gap-2">
                    <Label for="date">Game date</Label>
                    <Input id="date" v-model="form.date" type="date" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-2">
                        <Label for="kickoff">Kickoff</Label>
                        <Input
                            id="kickoff"
                            v-model="form.kickoff"
                            type="time"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="location">Field</Label>
                        <Input id="location" v-model="form.location" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-2">
                        <Label for="score-for">Our score</Label>
                        <Input
                            id="score-for"
                            v-model="form.score_for"
                            type="number"
                            min="0"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="score-against">Their score</Label>
                        <Input
                            id="score-against"
                            v-model="form.score_against"
                            type="number"
                            min="0"
                        />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="notes">Scouting notes</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="6"
                        :class="textareaClass"
                    />
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <p
                    v-if="!chosen.length"
                    class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Add plays from the list. They are called in this order.
                </p>
                <InputError :message="form.errors.play_ids" />
                <div
                    v-for="(play, i) in chosen"
                    :key="play.id"
                    class="flex items-center gap-3 rounded-xl border p-2"
                >
                    <span
                        class="w-6 text-right text-lg font-bold text-muted-foreground"
                        >{{ i + 1 }}</span
                    >
                    <div class="w-16 shrink-0 overflow-hidden rounded border">
                        <DiagramCanvas
                            :diagram="play.diagram"
                            :show-labels="false"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ play.name }}</div>
                        <div class="text-xs text-muted-foreground capitalize">
                            {{ play.category }}
                            <template v-if="play.formation">
                                · {{ play.formation }}</template
                            >
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            title="Move up"
                            :disabled="i === 0"
                            @click="move(i, i - 1)"
                            ><ArrowUp
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            title="Move down"
                            :disabled="i === chosen.length - 1"
                            @click="move(i, i + 1)"
                            ><ArrowDown
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            title="Remove"
                            @click="form.play_ids.splice(i, 1)"
                            ><X
                        /></Button>
                    </div>
                </div>
            </div>

            <div class="flex max-h-[70vh] flex-col gap-2 rounded-xl border p-3">
                <select
                    v-if="playbooks.length > 1"
                    v-model="playbookFilter"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                    aria-label="Filter by playbook"
                >
                    <option value="">All playbooks</option>
                    <option v-for="p in playbooks" :key="p.id" :value="p.id">
                        {{ p.name }}
                    </option>
                </select>
                <Input v-model="search" placeholder="Search plays" />
                <div class="flex flex-col gap-1 overflow-y-auto">
                    <div
                        v-for="play in available"
                        :key="play.id"
                        class="flex items-center justify-between gap-2 rounded-lg border p-2 text-sm"
                    >
                        <div class="min-w-0">
                            <div class="truncate font-medium">
                                {{ play.name }}
                            </div>
                            <div
                                class="text-xs text-muted-foreground capitalize"
                            >
                                {{ play.category }}
                            </div>
                        </div>
                        <Button
                            variant="outline"
                            size="icon-sm"
                            title="Add to game plan"
                            @click="form.play_ids.push(play.id)"
                            ><Plus
                        /></Button>
                    </div>
                    <p
                        v-if="!available.length"
                        class="text-sm text-muted-foreground"
                    >
                        No more plays.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
