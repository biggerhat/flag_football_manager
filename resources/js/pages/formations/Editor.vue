<script setup lang="ts">
import { useCanEdit } from '@/composables/useCanEdit';
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Redo2, Save, Trash2, Undo2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePlayEditor } from '@/composables/usePlayEditor';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import {
    OFFENSE_COLOR,
    PLAYER_TYPES,
    diagramFromFormation,
    emptyDiagram,
} from '@/lib/diagram';
import type { FormationRecord, PlayerType } from '@/lib/diagram';
import { index, store, update } from '@/routes/formations';
import { formations as printFormations } from '@/routes/print';

const canEdit = useCanEdit();
const props = defineProps<{
    formation: FormationRecord | null;
    positionNames: string[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Formations', href: index() }] },
});

/** New formations start from the standard five-player offense so there is something to move. */
const starter = (): FormationRecord['diagram'] => {
    const base = emptyDiagram();
    base.players = (
        [
            ['center', 'C', 0.5, base.los],
            ['quarterback', 'QB', 0.5, base.los + 0.1],
            ['receiver', 'X', 0.15, base.los],
            ['receiver', 'Y', 0.85, base.los],
            ['receiver', 'Z', 0.5, base.los + 0.2],
        ] as [PlayerType, string, number, number][]
    ).map(([type, label, x, y]) => ({
        id: `p-${label.toLowerCase()}`,
        type,
        label,
        x,
        y,
        color: OFFENSE_COLOR,
    }));

    return base;
};

const editor = usePlayEditor(
    props.formation ? diagramFromFormation(props.formation) : starter(),
);

const form = useForm({
    name: props.formation?.name ?? '',
    description: props.formation?.description ?? '',
    diagram: editor.diagram.value,
});

const title = computed(() =>
    props.formation ? 'Edit formation' : 'New formation',
);

const snapshot = () =>
    JSON.stringify([form.name, form.description, editor.diagram.value]);
const saved = ref(snapshot());
const { allow } = useUnsavedGuard(() => snapshot() !== saved.value);

function save() {
    form.diagram = {
        ...editor.diagram.value,
        routes: [],
        annotations: [],
    };
    const options = { onSuccess: () => (saved.value = snapshot()) };

    allow(() => {
        if (props.formation) {
            form.put(update.url(props.formation.id), options);
        } else {
            form.post(store.url(), options);
        }
    });
}

function onKey(event: KeyboardEvent) {
    const tag = (event.target as HTMLElement).tagName;

    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) {
        return;
    }

    if (
        (event.key === 'Delete' || event.key === 'Backspace') &&
        editor.selection.value
    ) {
        event.preventDefault();
        editor.deleteSelection();
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
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs';
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">{{ title }}</h1>
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
                <Button v-if="formation" variant="outline" size="sm" as-child>
                    <a
                        :href="
                            printFormations.url({
                                query: { ids: formation.id },
                            })
                        "
                        target="_blank"
                        >Print</a
                    >
                </Button>
                <Button
                    v-if="canEdit"
                    size="sm"
                    :disabled="form.processing"
                    @click="save"
                >
                    <Save /> Save formation
                </Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[200px_minmax(0,1fr)_300px]">
            <div class="flex flex-col gap-2 rounded-xl border p-3">
                <Label>Players</Label>
                <Button
                    variant="outline"
                    size="sm"
                    @click="editor.addOffensePlayer()"
                >
                    <Plus /> Offense
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    @click="editor.addDefender()"
                >
                    <Plus /> Defender
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!editor.selection.value"
                    @click="editor.deleteSelection()"
                >
                    <Trash2 /> Delete selected
                </Button>
                <p class="text-xs text-muted-foreground">
                    Drag players into position. Routes are added per play, not
                    to the formation.
                </p>
            </div>

            <div
                class="mx-auto w-full max-w-[640px] overflow-hidden rounded-xl border"
            >
                <DiagramCanvas
                    :diagram="editor.diagram.value"
                    :selection="editor.selection.value"
                    interactive
                    @choose="(target) => (editor.selection.value = target)"
                    @down="editor.onDown"
                    @move="editor.onMove"
                    @up="editor.onUp"
                />
            </div>

            <div class="flex flex-col gap-4 rounded-xl border p-3">
                <div class="grid gap-2">
                    <Label for="name">Formation name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="Trips right"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <Input id="description" v-model="form.description" />
                    <InputError :message="form.errors.description" />
                </div>

                <div
                    v-if="editor.selectedPlayer.value"
                    class="grid gap-2 border-t pt-3"
                >
                    <Label>Selected player</Label>
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

                <InputError :message="form.errors.diagram" />
            </div>
        </div>
    </div>
</template>
