<script setup lang="ts">
import CopyToTeam from '@/components/CopyToTeam.vue';
import EmptyState from '@/components/EmptyState.vue';
import { ClipboardList } from '@lucide/vue';
import { useCanEdit } from '@/composables/useCanEdit';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useDialog } from '@/composables/useDialog';
import { lastRunLabel } from '@/lib/dates';
import { Button } from '@/components/ui/button';
import { create, destroy, edit, index } from '@/routes/drills';

type Drill = {
    id: number;
    name: string;
    category: string;
    duration: number;
    players: number | null;
    equipment: string | null;
    instructions: string | null;
    coaching_points: string | null;
    last_run: string | null;
};

const canEdit = useCanEdit();
const props = defineProps<{ drills: Drill[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Drills', href: index() }] },
});

const category = ref('all');
const categories = computed(() => [
    ...new Set(props.drills.map((d) => d.category)),
]);
const filtered = computed(() =>
    props.drills.filter(
        (d) => category.value === 'all' || d.category === category.value,
    ),
);

const { confirm } = useDialog();

async function remove(drill: Drill) {
    if (
        await confirm({
            title: `Delete "${drill.name}"?`,
            description: 'Practices that use it keep their activity.',
            confirmText: 'Delete',
            destructive: true,
        })
    ) {
        router.delete(destroy.url(drill.id));
    }
}
</script>

<template>
    <Head title="Drills" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">Drill library</h1>
            <Button v-if="canEdit" as-child>
                <Link :href="create()"><Plus /> New drill</Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-1">
            <Button
                size="sm"
                :variant="category === 'all' ? 'default' : 'outline'"
                @click="category = 'all'"
                >All</Button
            >
            <Button
                v-for="c in categories"
                :key="c"
                size="sm"
                :variant="category === c ? 'default' : 'outline'"
                @click="category = c"
            >
                {{ c }}
            </Button>
        </div>

        <EmptyState
            v-if="!drills.length"
            :icon="ClipboardList"
            title="No drills yet"
            description="Build a library of drills to drop into practice plans."
        >
            <Button v-if="canEdit" as-child>
                <Link :href="create()"><Plus /> New drill</Link>
            </Button>
        </EmptyState>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="drill in filtered"
                :key="drill.id"
                class="flex flex-col gap-2 rounded-xl border p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="font-medium">{{ drill.name }}</div>
                    <Badge variant="secondary">{{ drill.duration }} min</Badge>
                </div>
                <div class="flex flex-wrap gap-1">
                    <Badge variant="outline">{{ drill.category }}</Badge>
                    <Badge v-if="drill.players" variant="outline"
                        >{{ drill.players }} players</Badge
                    >
                    <Badge variant="outline">{{
                        lastRunLabel(drill.last_run)
                    }}</Badge>
                </div>
                <p
                    v-if="drill.instructions"
                    class="text-sm text-muted-foreground"
                >
                    {{ drill.instructions }}
                </p>
                <p v-if="drill.coaching_points" class="text-sm">
                    <span class="font-medium">Coaching points:</span>
                    {{ drill.coaching_points }}
                </p>
                <p v-if="drill.equipment" class="text-xs text-muted-foreground">
                    Equipment: {{ drill.equipment }}
                </p>
                <div class="mt-auto flex gap-1 pt-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="edit(drill.id)"
                            ><Pencil /> {{ canEdit ? 'Edit' : 'View' }}</Link
                        >
                    </Button>
                    <CopyToTeam
                        v-if="canEdit"
                        kind="drill"
                        :id="drill.id"
                        :name="drill.name"
                    />
                    <Button
                        v-if="canEdit"
                        variant="outline"
                        size="icon-sm"
                        title="Delete"
                        @click="remove(drill)"
                        ><Trash2
                    /></Button>
                </div>
            </div>
        </div>
    </div>
</template>
