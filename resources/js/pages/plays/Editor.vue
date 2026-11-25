<script setup lang="ts">
import { useCanEdit } from '@/composables/useCanEdit';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ClipboardPaste,
    Copy,
    Eraser,
    FlipHorizontal2,
    MousePointer2,
    PenLine,
    Play as PlayIcon,
    Plus,
    Redo2,
    Save,
    Square,
    Trash2,
    Type,
    Undo2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import PlayHistory from '@/components/play/PlayHistory.vue';
import PlayToolsPanel from '@/components/play/PlayToolsPanel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useDialog } from '@/composables/useDialog';
import { usePlayEditor } from '@/composables/usePlayEditor';
import { usePlayPreview } from '@/composables/usePlayPreview';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import {
    CATEGORIES,
    DASH_OPTIONS,
    DIFFICULTIES,
    PLAYER_TYPES,
    emptyDiagram,
    ROUTE_TEMPLATES,
    diagramFromFormation,
} from '@/lib/diagram';
import type {
    FormationRecord,
    PlayerType,
    PlayRecord,
    RouteKind,
} from '@/lib/diagram';
import {
    index as playbooksIndex,
    show as showPlaybook,
} from '@/routes/playbooks';
import { create as createPlay, store, update } from '@/routes/plays';
import { store as storeFormation } from '@/routes/formations';
import { plays as printPlays } from '@/routes/print';

const canEdit = useCanEdit();
const props = defineProps<{
    play: PlayRecord | null;
    formations: FormationRecord[];
    playbooks: { id: number; name: string }[];
    playbookId: number | null;
    folders: string[];
    positionNames: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Playbooks', href: playbooksIndex() }],
    },
});

const isOffense = (f: FormationRecord) =>
    f.diagram.players.some((p) => p.type !== 'defender');
const offenseFormations = computed(() => props.formations.filter(isOffense));
const defenseFormations = computed(() =>
    props.formations.filter((f) => !isOffense(f)),
);

/** New plays start from the first offensive formation, not a defensive alignment. */
const startFormation = offenseFormations.value[0] ?? props.formations[0];

// A defense drawn faintly over the field to check matchups. Never saved with the play.
const overlayId = ref('');
const overlay = computed(
    () =>
        props.formations.find((f) => String(f.id) === overlayId.value)
            ?.diagram ?? null,
);

const editor = usePlayEditor(
    props.play?.diagram ??
        (startFormation
            ? diagramFromFormation(startFormation)
            : emptyDiagram()),
);

const { prompt } = useDialog();
const preview = usePlayPreview(() => editor.diagram.value);

const form = useForm({
    name: props.play?.name ?? '',
    formation: props.play
        ? props.play.formation
        : (startFormation?.name ?? null),
    category: props.play?.category ?? 'run',
    difficulty: props.play?.difficulty ?? 'easy',
    beginner_friendly: props.play?.beginner_friendly ?? true,
    notes: props.play?.notes ?? '',
    playbook_id: props.playbookId,
    folder: props.play?.folder ?? '',
    tags: (props.play?.tags ?? []) as string[],
    diagram: editor.diagram.value,
});

const title = computed(() => (props.play ? 'Edit play' : 'New play'));

const tagsText = ref((props.play?.tags ?? []).join(', '));

/** Comma-separated text to a clean, de-duplicated tag list. */
const parsedTags = () => [
    ...new Set(
        tagsText.value
            .split(',')
            .map((t) => t.trim().toLowerCase())
            .filter(Boolean),
    ),
];

const snapshot = () =>
    JSON.stringify([
        form.name,
        form.formation,
        form.category,
        form.difficulty,
        form.beginner_friendly,
        form.notes,
        form.playbook_id,
        form.folder,
        parsedTags(),
        editor.diagram.value,
    ]);
const saved = ref(snapshot());
const { allow } = useUnsavedGuard(() => snapshot() !== saved.value);

function save(asCopy = false) {
    form.diagram = editor.diagram.value;
    form.tags = parsedTags();
    form.transform((data) => ({
        ...data,
        folder: data.folder?.trim() || null,
    }));
    const options = { onSuccess: () => (saved.value = snapshot()) };

    allow(() => {
        if (props.play && !asCopy) {
            form.put(update.url(props.play.id), options);
        } else {
            form.post(store.url(), options);
        }
    });
}

function chooseFormation(id: string) {
    const formation = props.formations.find((f) => f.id === Number(id));

    if (!formation) {
        return;
    }

    form.formation = formation.name;
    editor.applyFormation(formation);
}

/** Keep the current offense as a reusable formation (defenders and routes are left out). */
async function saveAsFormation() {
    const name = await prompt({
        title: 'Save as formation',
        description: 'Name this formation so you can reuse it in other plays.',
        defaultValue: form.formation ?? '',
        confirmText: 'Save formation',
    });

    if (!name?.trim()) {
        return;
    }

    allow(() =>
        router.post(
            storeFormation.url(),
            {
                name: name.trim(),
                description: null,
                stay: true,
                diagram: {
                    ...editor.diagram.value,
                    players: editor.diagram.value.players.filter(
                        (p) => p.type !== 'defender',
                    ),
                    routes: [],
                    annotations: [],
                },
            },
            { preserveState: true, preserveScroll: true, only: ['formations'] },
        ),
    );
}

function onKey(event: KeyboardEvent) {
    const tag = (event.target as HTMLElement).tagName;

    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) {
        return;
    }

    const arrows: Record<string, [number, number]> = {
        ArrowLeft: [-1, 0],
        ArrowRight: [1, 0],
        ArrowUp: [0, -1],
        ArrowDown: [0, 1],
    };
    const mod = event.ctrlKey || event.metaKey;

    if (arrows[event.key] && editor.selection.value) {
        event.preventDefault();
        const [dx, dy] = arrows[event.key];
        editor.nudge(
            dx * (event.shiftKey ? 5 : 1),
            dy * (event.shiftKey ? 5 : 1),
        );

        return;
    }

    if (mod && event.key.toLowerCase() === 'c' && editor.selectedRoute.value) {
        event.preventDefault();
        editor.copyRoute();

        return;
    }

    if (mod && event.key.toLowerCase() === 'v' && editor.clip.value) {
        event.preventDefault();
        editor.pasteRoute();

        return;
    }

    if (
        (event.key === 'Delete' || event.key === 'Backspace') &&
        editor.selection.value
    ) {
        event.preventDefault();
        editor.deleteSelection();
    } else if (event.key === 'Escape' || event.key === 'Enter') {
        editor.finishDrawing();
    } else if (
        (event.ctrlKey || event.metaKey) &&
        event.key.toLowerCase() === 'z'
    ) {
        event.preventDefault();
        if (event.shiftKey) {
            editor.redo();
        } else {
            editor.undo();
        }
    } else if (
        (event.ctrlKey || event.metaKey) &&
        event.key.toLowerCase() === 'y'
    ) {
        event.preventDefault();
        editor.redo();
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

const routeKinds: { key: RouteKind; label: string }[] = [
    { key: 'route', label: 'Route' },
    { key: 'run', label: 'Run' },
    { key: 'handoff', label: 'Handoff' },
    { key: 'block', label: 'Block' },
];
const toolClass = (active: boolean) =>
    active ? 'bg-primary text-primary-foreground' : '';
const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs';
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <Button v-if="playbookId" variant="outline" size="sm" as-child>
                    <Link :href="showPlaybook(playbookId)">
                        <ArrowLeft /> Back to playbook
                    </Link>
                </Button>
                <h1 class="text-xl font-semibold">{{ title }}</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!editor.canUndo.value"
                    @click="editor.undo()"
                >
                    <Undo2 /> Undo
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!editor.canRedo.value"
                    @click="editor.redo()"
                >
                    <Redo2 /> Redo
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :title="
                        preview.elapsed.value === null
                            ? 'Watch the players run their routes'
                            : 'Stop the preview'
                    "
                    @click="
                        preview.elapsed.value === null
                            ? preview.start()
                            : preview.stop()
                    "
                >
                    <Square v-if="preview.elapsed.value !== null" />
                    <PlayIcon v-else />
                    {{ preview.elapsed.value === null ? 'Preview' : 'Stop' }}
                </Button>
                <PlayHistory
                    v-if="play"
                    :play-id="play.id"
                    :can-edit="canEdit"
                />
                <Button v-if="play" variant="outline" size="sm" as-child>
                    <a
                        :href="printPlays.url({ query: { ids: play.id } })"
                        target="_blank"
                        >Print</a
                    >
                </Button>
                <Button
                    v-if="canEdit && play"
                    variant="outline"
                    size="sm"
                    :disabled="form.processing"
                    title="Save as a new play, leaving this one unchanged"
                    @click="save(true)"
                >
                    <Copy /> Save as copy
                </Button>
                <Button
                    v-if="canEdit && play"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        :href="createPlay({ query: { playbook: playbookId } })"
                    >
                        <Plus /> New play
                    </Link>
                </Button>
                <Button
                    v-if="canEdit"
                    size="sm"
                    :disabled="form.processing"
                    @click="save(false)"
                >
                    <Save /> Save play
                </Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)_300px]">
            <PlayToolsPanel :editor="editor" />

            <!-- canvas -->
            <div class="mx-auto w-full max-w-[640px]">
                <div
                    v-if="defenseFormations.length"
                    class="mb-2 flex items-center gap-2 text-sm"
                >
                    <Label for="overlay" class="shrink-0">Show defense</Label>
                    <select
                        id="overlay"
                        v-model="overlayId"
                        :class="selectClass"
                    >
                        <option value="">None</option>
                        <option
                            v-for="f in defenseFormations"
                            :key="f.id"
                            :value="String(f.id)"
                        >
                            {{ f.name }}
                        </option>
                    </select>
                </div>
                <div class="overflow-hidden rounded-xl border">
                    <DiagramCanvas
                        :diagram="editor.diagram.value"
                        :selection="editor.selection.value"
                        :interactive="preview.elapsed.value === null"
                        :playback="preview.elapsed.value"
                        :overlay="overlay"
                        route-labels
                        @choose="(target) => (editor.selection.value = target)"
                        @down="editor.onDown"
                        @move="editor.onMove"
                        @up="editor.onUp"
                    />
                </div>
            </div>

            <!-- properties -->
            <div class="flex flex-col gap-4 rounded-xl border p-3">
                <div class="grid gap-2">
                    <Label for="name">Play name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="Bunch right – quick slant"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="formation">Formation</Label>
                    <select
                        id="formation"
                        :class="selectClass"
                        :value="
                            formations.find((f) => f.name === form.formation)
                                ?.id ?? ''
                        "
                        @change="
                            chooseFormation(
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option v-if="!formations.length" value="">
                            No saved formations
                        </option>
                        <optgroup
                            v-if="offenseFormations.length"
                            label="Offense"
                        >
                            <option
                                v-for="f in offenseFormations"
                                :key="f.id"
                                :value="f.id"
                            >
                                {{ f.name }}
                            </option>
                        </optgroup>
                        <optgroup
                            v-if="defenseFormations.length"
                            label="Defense"
                        >
                            <option
                                v-for="f in defenseFormations"
                                :key="f.id"
                                :value="f.id"
                            >
                                {{ f.name }}
                            </option>
                        </optgroup>
                    </select>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="saveAsFormation"
                    >
                        Save offense as formation
                    </Button>
                    <p class="text-xs text-muted-foreground">
                        Choosing a formation replaces that side's players
                        (offense and/or defense) and clears their routes. Undo
                        if needed.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-2">
                        <Label for="category">Category</Label>
                        <select
                            id="category"
                            v-model="form.category"
                            :class="selectClass"
                        >
                            <option
                                v-for="c in CATEGORIES"
                                :key="c"
                                :value="c"
                                class="capitalize"
                            >
                                {{ c }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="difficulty">Difficulty</Label>
                        <select
                            id="difficulty"
                            v-model="form.difficulty"
                            :class="selectClass"
                        >
                            <option
                                v-for="d in DIFFICULTIES"
                                :key="d"
                                :value="d"
                            >
                                {{ d }}
                            </option>
                        </select>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.beginner_friendly"
                        type="checkbox"
                        class="size-4"
                    />
                    Beginner friendly
                </label>

                <div class="grid gap-2">
                    <Label for="playbook">Playbook</Label>
                    <select
                        id="playbook"
                        v-model="form.playbook_id"
                        :class="selectClass"
                    >
                        <option
                            v-for="p in playbooks"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.playbook_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="folder">Folder</Label>
                    <Input
                        id="folder"
                        v-model="form.folder"
                        list="play-folders"
                        placeholder="Red zone, Week 1 install…"
                        maxlength="40"
                    />
                    <datalist id="play-folders">
                        <option v-for="f in folders" :key="f" :value="f" />
                    </datalist>
                    <InputError :message="form.errors.folder" />
                </div>

                <div class="grid gap-2">
                    <Label for="tags">Tags</Label>
                    <Input
                        id="tags"
                        v-model="tagsText"
                        placeholder="goal line, quick, screen"
                    />
                    <InputError :message="form.errors.tags" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Coaching notes</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="4"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <!-- selection -->
                <div
                    v-if="editor.selectedPlayer.value"
                    class="grid gap-2 border-t pt-3"
                >
                    <Label
                        >Player {{ editor.selectedPlayer.value.label }}</Label
                    >
                    <Input
                        v-model="editor.selectedPlayer.value.label"
                        maxlength="6"
                        list="position-names"
                        @focus="editor.remember()"
                    />
                    <datalist id="position-names">
                        <option
                            v-for="n in positionNames"
                            :key="n"
                            :value="n"
                        />
                    </datalist>
                    <select
                        :class="selectClass"
                        :value="editor.selectedPlayer.value.type"
                        @change="
                            editor.setPlayerType(
                                ($event.target as HTMLSelectElement)
                                    .value as PlayerType,
                            )
                        "
                    >
                        <option
                            v-for="t in PLAYER_TYPES"
                            :key="t.key"
                            :value="t.key"
                        >
                            {{ t.label }}
                        </option>
                    </select>
                </div>
                <div
                    v-if="editor.selectedRoute.value"
                    class="grid gap-2 border-t pt-3"
                >
                    <Label>Selected route</Label>
                    <Input
                        :model-value="editor.selectedRoute.value.name ?? ''"
                        placeholder="Route name"
                        @focus="editor.remember()"
                        @update:model-value="
                            editor.selectedRoute.value!.name = String($event)
                        "
                    />
                    <div class="grid grid-cols-2 gap-1">
                        <Button
                            v-for="k in routeKinds"
                            :key="k.key"
                            variant="outline"
                            size="sm"
                            :class="
                                toolClass(
                                    editor.selectedRoute.value.kind === k.key,
                                )
                            "
                            @click="editor.setRouteKind(k.key)"
                        >
                            {{ k.label }}
                        </Button>
                    </div>
                    <Label>Line style</Label>
                    <div class="grid grid-cols-3 gap-1">
                        <Button
                            v-for="d in DASH_OPTIONS"
                            :key="d.key"
                            variant="outline"
                            size="sm"
                            :class="
                                toolClass(
                                    editor.selectedRoute.value.style.dash ===
                                        d.key,
                                )
                            "
                            @click="editor.setRouteDash(d.key)"
                        >
                            {{ d.label }}
                        </Button>
                    </div>
                </div>
                <div
                    v-if="editor.selectedAnnotation.value"
                    class="grid gap-2 border-t pt-3"
                >
                    <Label>Text</Label>
                    <Input
                        v-model="editor.selectedAnnotation.value.text"
                        maxlength="80"
                        @focus="editor.remember()"
                    />
                </div>

                <InputError :message="form.errors.diagram" />
            </div>
        </div>
    </div>
</template>
