<script setup lang="ts">
import CopyToTeam from '@/components/CopyToTeam.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Shapes } from '@lucide/vue';
import { useCanEdit } from '@/composables/useCanEdit';
import { useOnce } from '@/composables/useOnce';
import { Head, Link, router } from '@inertiajs/vue3';
import { Copy, Pencil, Plus, Printer, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import { Button } from '@/components/ui/button';
import { useDialog } from '@/composables/useDialog';
import type { FormationRecord } from '@/lib/diagram';
import { create, destroy, duplicate, edit, index } from '@/routes/formations';
import { formations as printFormations } from '@/routes/print';

defineProps<{ formations: FormationRecord[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Formations', href: index() }] },
});

const canEdit = useCanEdit();
const once = useOnce();
const selected = ref<number[]>([]);

function toggle(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((i) => i !== id)
        : [...selected.value, id];
}

const printUrl = computed(() =>
    printFormations.url({ query: { ids: selected.value.join(',') } }),
);

const { confirm } = useDialog();

async function remove(formation: FormationRecord) {
    if (
        await confirm({
            title: `Delete "${formation.name}"?`,
            description: 'Plays already using it keep their positions.',
            confirmText: 'Delete',
            destructive: true,
        })
    ) {
        router.delete(destroy.url(formation.id));
    }
}
</script>

<template>
    <Head title="Formations" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">Formations</h1>
            <div class="flex flex-wrap items-center gap-2">
                <Button v-if="selected.length" variant="outline" as-child>
                    <a :href="printUrl" target="_blank"
                        ><Printer /> Print {{ selected.length }} selected</a
                    >
                </Button>
                <Button v-if="canEdit" as-child>
                    <Link :href="create()"><Plus /> New formation</Link>
                </Button>
            </div>
        </div>

        <EmptyState
            v-if="!formations.length"
            :icon="Shapes"
            title="No formations yet"
            description="Create one, or save a play's offense as a formation from the play editor."
        >
            <Button v-if="canEdit" as-child>
                <Link :href="create()"><Plus /> New formation</Link>
            </Button>
        </EmptyState>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            <div
                v-for="formation in formations"
                :key="formation.id"
                class="flex flex-col overflow-hidden rounded-xl border"
            >
                <div class="relative">
                    <DiagramCanvas :diagram="formation.diagram" />
                    <input
                        type="checkbox"
                        class="absolute top-2 left-2 size-5"
                        :checked="selected.includes(formation.id)"
                        :aria-label="`Select ${formation.name} for printing`"
                        @change="toggle(formation.id)"
                    />
                </div>
                <div class="flex flex-1 flex-col gap-1 p-3">
                    <div class="font-medium">{{ formation.name }}</div>
                    <p
                        v-if="formation.description"
                        class="text-sm text-muted-foreground"
                    >
                        {{ formation.description }}
                    </p>
                    <div class="mt-auto flex gap-1 pt-2">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="edit(formation.id)"
                                ><Pencil />
                                {{ canEdit ? 'Edit' : 'View' }}</Link
                            >
                        </Button>
                        <Button
                            v-if="canEdit"
                            variant="outline"
                            size="icon-sm"
                            title="Duplicate"
                            @click="once.post(duplicate.url(formation.id))"
                            :disabled="once.pending.value"
                        >
                            <Copy />
                        </Button>
                        <CopyToTeam
                            v-if="canEdit"
                            kind="formation"
                            :id="formation.id"
                            :name="formation.name"
                        />
                        <Button
                            v-if="canEdit"
                            variant="outline"
                            size="icon-sm"
                            title="Delete"
                            @click="remove(formation)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
