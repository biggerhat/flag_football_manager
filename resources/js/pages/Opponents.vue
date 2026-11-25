<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Save, Users } from '@lucide/vue';
import { reactive } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCanEdit } from '@/composables/useCanEdit';
import { opponents as opponentsRoute } from '@/routes';
import { gameday } from '@/routes/gameplans';
import { update } from '@/routes/opponents';

type Game = {
    id: number;
    name: string;
    date: string | null;
    result: 'W' | 'L' | 'T' | null;
    score_for: number | null;
    score_against: number | null;
};
type Opponent = {
    name: string;
    notes: string | null;
    wins: number;
    losses: number;
    ties: number;
    pointsFor: number;
    pointsAgainst: number;
    games: Game[];
};

const props = defineProps<{ opponents: Opponent[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Opponents', href: opponentsRoute() }],
    },
});

const canEdit = useCanEdit();
const drafts = reactive<Record<string, string>>(
    Object.fromEntries(props.opponents.map((o) => [o.name, o.notes ?? ''])),
);

function save(o: Opponent) {
    router.put(
        update.url(),
        { name: o.name, notes: drafts[o.name] },
        { preserveScroll: true },
    );
}

const RESULT_VARIANT = {
    W: 'default',
    L: 'destructive',
    T: 'secondary',
} as const;
</script>

<template>
    <Head title="Opponents" />

    <div class="flex max-w-4xl flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold">Opponents</h1>
            <p class="text-sm text-muted-foreground">
                Every team you have a game plan against, with your record and
                scouting notes. Teams are matched by the opponent name on the
                game plan.
            </p>
        </div>

        <EmptyState
            v-if="!opponents.length"
            :icon="Users"
            title="No opponents yet"
            description="Add an opponent's name to a game plan and it shows up here."
        />

        <section
            v-for="o in opponents"
            :key="o.name"
            class="flex flex-col gap-3 rounded-xl border p-4"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-lg font-semibold">{{ o.name }}</h2>
                <span class="text-sm text-muted-foreground">
                    {{ o.wins }}–{{ o.losses
                    }}<template v-if="o.ties">–{{ o.ties }}</template>
                    <template v-if="o.wins + o.losses + o.ties">
                        · {{ o.pointsFor }}–{{ o.pointsAgainst }} points
                    </template>
                </span>
            </div>

            <ul class="flex flex-wrap gap-2 text-sm">
                <li v-for="g in o.games" :key="g.id">
                    <Link
                        :href="gameday(g.id)"
                        class="flex items-center gap-1 rounded-md border px-2 py-1 hover:bg-accent"
                    >
                        <Badge
                            v-if="g.result"
                            :variant="RESULT_VARIANT[g.result]"
                            >{{ g.result }}</Badge
                        >
                        <span>{{ g.date ?? g.name }}</span>
                        <span v-if="g.result" class="text-muted-foreground">
                            {{ g.score_for }}–{{ g.score_against }}
                        </span>
                    </Link>
                </li>
            </ul>

            <div class="flex flex-col gap-2">
                <textarea
                    v-model="drafts[o.name]"
                    rows="3"
                    maxlength="5000"
                    :disabled="!canEdit"
                    :aria-label="`Scouting notes for ${o.name}`"
                    placeholder="Scouting notes: what they run, who to watch, how we beat them"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                />
                <Button
                    v-if="canEdit"
                    size="sm"
                    variant="outline"
                    class="self-start"
                    :disabled="drafts[o.name] === (o.notes ?? '')"
                    @click="save(o)"
                >
                    <Save /> Save notes
                </Button>
            </div>
        </section>
    </div>
</template>
