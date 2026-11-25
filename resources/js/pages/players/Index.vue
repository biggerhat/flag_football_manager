<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { Users } from '@lucide/vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Save, Trash2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Pencil, Search } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCanEdit } from '@/composables/useCanEdit';
import { useDialog } from '@/composables/useDialog';
import { destroy, index, store, update } from '@/routes/players';

type Player = {
    id: number;
    name: string;
    number: string | null;
    position: string | null;
    notes: string | null;
    active: boolean;
    practices_count: number;
};

const props = defineProps<{
    players: Player[];
    positionNames: string[];
    practiceCount: number;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Roster', href: index() }] },
});

const canEdit = useCanEdit();
const { confirm } = useDialog();

const form = useForm({ name: '', number: '', position: '', notes: '' });

type Draft = {
    name: string;
    number: string;
    position: string;
    notes: string;
    active: boolean;
};

const toDraft = (player: Player): Draft => ({
    name: player.name,
    number: player.number ?? '',
    position: player.position ?? '',
    notes: player.notes ?? '',
    active: player.active,
});

// Rows are edited in place and saved one at a time.
const drafts = reactive<Record<number, Draft>>({});

watch(
    () => props.players,
    (players) => {
        for (const player of players) {
            drafts[player.id] = toDraft(player);
        }
    },
    { immediate: true },
);

const OFFENSE = new Set([
    'QB',
    'C',
    'RB',
    'X',
    'Y',
    'Z',
    'SL',
    'WR',
    'TE',
    'HB',
    'FB',
    'OL',
    'OT',
    'OG',
    'G',
    'T',
]);
const DEFENSE = new Set([
    'LB',
    'CB',
    'S',
    'DE',
    'DT',
    'DL',
    'NT',
    'FS',
    'SS',
    'MLB',
    'OLB',
    'DB',
]);
const SPECIAL = new Set(['K', 'P', 'LS', 'KR', 'PR']);

function unitOf(player: Player): string {
    const pos = (player.position ?? '').toUpperCase();

    return OFFENSE.has(pos)
        ? 'Offense'
        : DEFENSE.has(pos)
          ? 'Defense'
          : SPECIAL.has(pos)
            ? 'Special teams'
            : 'Other';
}

const attendancePct = (p: Player) =>
    props.practiceCount
        ? Math.round((p.practices_count / props.practiceCount) * 100)
        : 0;
// Flag active players who have been to under half of the practices (once there are a few to judge by).
const lowAttendance = (p: Player) =>
    p.active && props.practiceCount >= 3 && attendancePct(p) < 50;
const lowCount = computed(() => props.players.filter(lowAttendance).length);
const onlyLow = ref(false);
const search = ref('');
const editing = ref<Player | null>(null);
const editOpen = computed({
    get: () => editing.value !== null,
    set: (open: boolean) => {
        if (!open) {
            editing.value = null;
        }
    },
});

const grouped = computed(() => {
    const q = search.value.trim().toLowerCase();
    const groups = new Map<string, Player[]>();

    for (const unit of ['Offense', 'Defense', 'Special teams', 'Other']) {
        groups.set(unit, []);
    }

    for (const player of props.players) {
        const hay =
            `${player.name} ${player.number ?? ''} ${player.position ?? ''}`.toLowerCase();

        if (
            (!q || hay.includes(q)) &&
            (!onlyLow.value || lowAttendance(player))
        ) {
            groups.get(unitOf(player))!.push(player);
        }
    }

    return [...groups].filter(([, list]) => list.length);
});

function saveEdit() {
    if (editing.value) {
        save(editing.value);
        editing.value = null;
    }
}

function dirty(player: Player): boolean {
    const draft = drafts[player.id];
    const saved = toDraft(player);

    return (
        !!draft &&
        (Object.keys(saved) as (keyof Draft)[]).some(
            (key) => draft[key] !== saved[key],
        )
    );
}

function add() {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function save(player: Player) {
    router.put(
        update.url(player.id),
        { ...drafts[player.id] },
        { preserveScroll: true },
    );
}

async function remove(player: Player) {
    if (
        await confirm({
            title: `Remove ${player.name}?`,
            description: 'They are also removed from practice attendance.',
            confirmText: 'Remove',
            destructive: true,
        })
    ) {
        editing.value = null;
        router.delete(destroy.url(player.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Roster" />

    <div class="flex max-w-4xl flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold">Roster</h1>
            <p class="text-sm text-muted-foreground">
                Give a player a position (like QB or X) and printed plays can
                show their name beside that assignment. Practices can track who
                attended.
            </p>
        </div>

        <form
            v-if="canEdit"
            class="flex flex-wrap items-start gap-2 rounded-xl border p-3"
            @submit.prevent="add"
        >
            <div class="grid gap-1">
                <Input
                    v-model="form.name"
                    placeholder="Name"
                    maxlength="80"
                    aria-label="Player name"
                    required
                />
                <InputError :message="form.errors.name" />
            </div>
            <Input
                v-model="form.number"
                class="w-20"
                placeholder="#"
                maxlength="4"
                aria-label="Jersey number"
            />
            <Input
                v-model="form.position"
                class="w-28"
                placeholder="Position"
                maxlength="8"
                list="position-names"
                aria-label="Position"
            />
            <Button type="submit" :disabled="form.processing">
                <Plus /> Add player
            </Button>
        </form>
        <datalist id="position-names">
            <option v-for="name in positionNames" :key="name" :value="name" />
        </datalist>

        <EmptyState
            v-if="!players.length"
            :icon="Users"
            title="No players yet"
            description="Add your first player above to start tracking positions and attendance."
        />

        <div v-else class="flex flex-col gap-4">
            <div class="relative max-w-sm">
                <Search
                    class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-8"
                    placeholder="Filter by name, number or position"
                    aria-label="Filter players"
                />
            </div>

            <label v-if="lowCount" class="flex items-center gap-2 text-sm">
                <input v-model="onlyLow" type="checkbox" />
                Show only low attendance ({{ lowCount }})
            </label>

            <p v-if="!grouped.length" class="text-sm text-muted-foreground">
                No players match.
            </p>

            <section
                v-for="[unit, list] in grouped"
                :key="unit"
                class="flex flex-col gap-1"
            >
                <h2 class="text-sm font-medium text-muted-foreground">
                    {{ unit }} · {{ list.length }}
                </h2>
                <ul class="divide-y rounded-xl border">
                    <li
                        v-for="player in list"
                        :key="player.id"
                        class="flex items-center gap-3 p-3"
                        :class="player.active ? '' : 'opacity-60'"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                            >{{ player.number || '–' }}</span
                        >
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">
                                {{ player.name }}
                                <Badge v-if="!player.active" variant="outline"
                                    >Inactive</Badge
                                >
                            </div>
                            <div
                                v-if="player.notes"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ player.notes }}
                            </div>
                        </div>
                        <Badge v-if="player.position" variant="secondary">{{
                            player.position
                        }}</Badge>
                        <div
                            v-if="practiceCount"
                            class="hidden w-28 flex-col gap-1 sm:flex"
                            :title="`${player.practices_count} of ${practiceCount} practices`"
                        >
                            <div class="flex justify-between text-xs">
                                <span class="text-muted-foreground"
                                    >Attended</span
                                >
                                <span
                                    :class="
                                        lowAttendance(player)
                                            ? 'font-medium text-warning'
                                            : ''
                                    "
                                    >{{ attendancePct(player) }}%</span
                                >
                            </div>
                            <div
                                class="h-1.5 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :class="
                                        lowAttendance(player)
                                            ? 'bg-warning'
                                            : 'bg-chart-1'
                                    "
                                    :style="{
                                        width: `${attendancePct(player)}%`,
                                    }"
                                />
                            </div>
                        </div>
                        <Button
                            v-if="canEdit"
                            size="icon-sm"
                            variant="ghost"
                            :aria-label="`Edit ${player.name}`"
                            @click="editing = player"
                        >
                            <Pencil />
                        </Button>
                    </li>
                </ul>
            </section>
        </div>

        <Dialog v-model:open="editOpen">
            <DialogContent
                v-if="editing && drafts[editing.id]"
                class="sm:max-w-md"
            >
                <DialogHeader>
                    <DialogTitle>Edit player</DialogTitle>
                    <DialogDescription
                        >Changes apply to printed plays and
                        attendance.</DialogDescription
                    >
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="saveEdit">
                    <Input
                        v-model="drafts[editing.id].name"
                        aria-label="Name"
                        placeholder="Name"
                        required
                    />
                    <div class="flex gap-2">
                        <Input
                            v-model="drafts[editing.id].number"
                            class="w-24"
                            aria-label="Number"
                            placeholder="#"
                        />
                        <Input
                            v-model="drafts[editing.id].position"
                            class="flex-1"
                            aria-label="Position"
                            placeholder="Position"
                            list="position-names"
                        />
                    </div>
                    <Input
                        v-model="drafts[editing.id].notes"
                        aria-label="Notes"
                        placeholder="Notes"
                    />
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="drafts[editing.id].active"
                            type="checkbox"
                        />
                        Active
                    </label>
                    <DialogFooter class="gap-2 sm:justify-between">
                        <Button
                            type="button"
                            variant="ghost"
                            class="text-destructive"
                            @click="remove(editing)"
                        >
                            <Trash2 /> Remove
                        </Button>
                        <Button type="submit" :disabled="!dirty(editing)"
                            ><Save /> Save</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
