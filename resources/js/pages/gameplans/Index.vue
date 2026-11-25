<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { Swords } from '@lucide/vue';
import { useCanEdit } from '@/composables/useCanEdit';
import { Head, Link, router } from '@inertiajs/vue3';
import { Clipboard, Pencil, Plus, Printer, Trash2 } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useDialog } from '@/composables/useDialog';
import { create, destroy, edit, gameday, index } from '@/routes/gameplans';

type GamePlanRow = {
    id: number;
    name: string;
    opponent: string | null;
    date: string | null;
    plays_count: number;
    location: string | null;
    kickoff: string | null;
    score_for: number | null;
    score_against: number | null;
    result: 'W' | 'L' | 'T' | null;
};

defineProps<{
    gamePlans: GamePlanRow[];
    record: {
        wins: number;
        losses: number;
        ties: number;
        pointsFor: number;
        pointsAgainst: number;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Game plans', href: index() }] },
});

const canEdit = useCanEdit();
const { confirm } = useDialog();

async function remove(plan: GamePlanRow) {
    if (
        await confirm({
            title: `Delete "${plan.name}"?`,
            confirmText: 'Delete',
            destructive: true,
        })
    ) {
        router.delete(destroy.url(plan.id));
    }
}
</script>

<template>
    <Head title="Game plans" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">Game plans</h1>
            <Button v-if="canEdit" as-child>
                <Link :href="create()"><Plus /> New game plan</Link>
            </Button>
        </div>

        <div
            v-if="record.wins + record.losses + record.ties"
            class="flex flex-wrap items-baseline gap-x-6 gap-y-1 rounded-xl border px-4 py-3"
        >
            <span class="text-2xl font-semibold"
                >{{ record.wins }}–{{ record.losses
                }}<template v-if="record.ties">–{{ record.ties }}</template>
            </span>
            <span class="text-sm text-muted-foreground"
                >Season record · {{ record.pointsFor }} points scored,
                {{ record.pointsAgainst }} allowed</span
            >
        </div>

        <EmptyState
            v-if="!gamePlans.length"
            :icon="Swords"
            title="No game plans yet"
            description="Pick and order the plays you want for a specific opponent, then print the call sheet."
        >
            <Button v-if="canEdit" as-child>
                <Link :href="create()"><Plus /> New game plan</Link>
            </Button>
        </EmptyState>

        <div
            v-for="plan in gamePlans"
            :key="plan.id"
            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border p-3"
        >
            <div>
                <div class="font-medium">{{ plan.name }}</div>
                <div class="flex flex-wrap gap-1 pt-1">
                    <Badge v-if="plan.opponent" variant="secondary"
                        >vs {{ plan.opponent }}</Badge
                    >
                    <Badge v-if="plan.date" variant="outline">{{
                        plan.date
                    }}</Badge>
                    <Badge v-if="plan.kickoff" variant="outline">{{
                        plan.kickoff.slice(0, 5)
                    }}</Badge>
                    <Badge v-if="plan.location" variant="outline">{{
                        plan.location
                    }}</Badge>
                    <Badge
                        v-if="plan.result"
                        :variant="
                            plan.result === 'L' ? 'destructive' : 'default'
                        "
                        >{{ plan.result }} {{ plan.score_for }}–{{
                            plan.score_against
                        }}</Badge
                    >
                    <Badge variant="outline"
                        >{{ plan.plays_count }} plays</Badge
                    >
                </div>
            </div>
            <div class="flex gap-1">
                <Button
                    v-if="plan.plays_count"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="gameday(plan.id)"><Clipboard /> Game day</Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="edit(plan.id)"
                        ><Pencil /> {{ canEdit ? 'Edit' : 'View' }}</Link
                    >
                </Button>
                <Button
                    v-if="canEdit"
                    variant="outline"
                    size="icon-sm"
                    title="Delete"
                    @click="remove(plan)"
                    ><Trash2
                /></Button>
            </div>
        </div>
    </div>
</template>
