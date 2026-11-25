<script setup lang="ts">
import { useCanEdit } from '@/composables/useCanEdit';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DrillDiagram from '@/components/play/DrillDiagram.vue';
import { emptyDiagram, OFFENSE_COLOR } from '@/lib/diagram';
import type { Diagram } from '@/lib/diagram';
import { index, store, update } from '@/routes/drills';

type Drill = {
    id: number;
    name: string;
    category: string;
    duration: number;
    players: number | null;
    equipment: string | null;
    instructions: string | null;
    coaching_points: string | null;
    diagram: Diagram | null;
};

const canEdit = useCanEdit();
const props = defineProps<{ drill: Drill | null }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Drills', href: index() }] },
});

const categories = [
    'Warm-up',
    'Ball handling',
    'Passing',
    'Catching',
    'Routes',
    'Running',
    'Defense',
    'Team offense',
    'Scrimmage',
];

const form = useForm({
    name: props.drill?.name ?? '',
    category: props.drill?.category ?? categories[0],
    duration: props.drill?.duration ?? 10,
    players: (props.drill?.players ?? '') as number | string,
    equipment: props.drill?.equipment ?? '',
    instructions: props.drill?.instructions ?? '',
    coaching_points: props.drill?.coaching_points ?? '',
    diagram: (props.drill?.diagram ?? null) as Diagram | null,
});

/** Starts a diagram with a couple of players on the field so there is something to move. */
function addDiagram() {
    const base = emptyDiagram();
    base.players = [
        {
            id: 'p-1',
            type: 'receiver',
            label: '1',
            x: 0.35,
            y: 0.6,
            color: OFFENSE_COLOR,
        },
        {
            id: 'p-2',
            type: 'receiver',
            label: '2',
            x: 0.65,
            y: 0.6,
            color: OFFENSE_COLOR,
        },
    ];
    form.diagram = base;
}

function submit() {
    if (props.drill) {
        form.put(update.url(props.drill.id));
    } else {
        form.post(store.url());
    }
}

const textareaClass =
    'border-input bg-background w-full rounded-md border px-3 py-2 text-sm shadow-xs';
</script>

<template>
    <Head :title="drill ? 'Edit drill' : 'New drill'" />

    <form
        class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4"
        @submit.prevent="submit"
    >
        <h1 class="text-xl font-semibold">
            {{ drill ? 'Edit drill' : 'New drill' }}
        </h1>

        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input id="name" v-model="form.name" required />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label for="category">Category</Label>
                <input
                    id="category"
                    v-model="form.category"
                    list="drill-categories"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                />
                <datalist id="drill-categories">
                    <option v-for="c in categories" :key="c" :value="c" />
                </datalist>
                <InputError :message="form.errors.category" />
            </div>
            <div class="grid gap-2">
                <Label for="duration">Minutes</Label>
                <Input
                    id="duration"
                    v-model="form.duration"
                    type="number"
                    min="1"
                />
                <InputError :message="form.errors.duration" />
            </div>
            <div class="grid gap-2">
                <Label for="players">Players needed</Label>
                <Input
                    id="players"
                    v-model="form.players"
                    type="number"
                    min="1"
                />
                <InputError :message="form.errors.players" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label>Diagram</Label>
            <DrillDiagram
                v-if="form.diagram"
                v-model="form.diagram"
                :disabled="!canEdit"
                @remove="form.diagram = null"
            />
            <Button
                v-else-if="canEdit"
                type="button"
                variant="outline"
                class="self-start"
                @click="addDiagram"
            >
                Add a diagram
            </Button>
            <InputError :message="form.errors.diagram" />
        </div>
        <div class="grid gap-2">
            <Label for="equipment">Equipment</Label>
            <Input
                id="equipment"
                v-model="form.equipment"
                placeholder="Cones, balls, flags"
            />
        </div>
        <div class="grid gap-2">
            <Label for="instructions">Instructions</Label>
            <textarea
                id="instructions"
                v-model="form.instructions"
                rows="4"
                :class="textareaClass"
            />
            <InputError :message="form.errors.instructions" />
        </div>
        <div class="grid gap-2">
            <Label for="points">Coaching points</Label>
            <textarea
                id="points"
                v-model="form.coaching_points"
                rows="3"
                :class="textareaClass"
            />
        </div>

        <div class="flex gap-2">
            <Button v-if="canEdit" type="submit" :disabled="form.processing"
                >Save drill</Button
            >
            <Button variant="outline" as-child
                ><Link :href="index()">Cancel</Link></Button
            >
        </div>
    </form>
</template>
