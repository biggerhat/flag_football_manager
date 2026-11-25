<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BarChart3 } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { stats } from '@/routes';

type Row = {
    calls: number;
    yards: number;
    touchdowns: number;
    success: number;
};

defineProps<{
    totals: Row;
    byDown: (Row & { down: number })[];
    byPlay: (Row & { name: string; category: string | null })[];
    byPlayer: (Row & { name: string; number: string | null })[];
    byGame: (Row & { name: string; date: string | null })[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Stats', href: stats() }] },
});

const pct = (row: Row) =>
    row.calls ? Math.round((row.success / row.calls) * 100) : 0;
const avg = (row: Row) =>
    row.calls ? (row.yards / row.calls).toFixed(1) : '–';
const ORDINAL = ['', 'st', 'nd', 'rd', 'th'];
</script>

<template>
    <Head title="Stats" />

    <div class="flex max-w-5xl flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Stats</h1>
            <p class="text-sm text-muted-foreground">
                What is working, from the calls you log on game day. A call
                "works" when it gains yards or scores.
            </p>
        </div>

        <EmptyState
            v-if="!totals.calls"
            :icon="BarChart3"
            title="No calls logged yet"
            description="Open a game plan on game day and log each call. Results show up here."
        />

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl border p-4">
                    <div class="text-3xl font-semibold">{{ totals.calls }}</div>
                    <div class="text-sm text-muted-foreground">Calls</div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-3xl font-semibold">{{ pct(totals) }}%</div>
                    <div class="text-sm text-muted-foreground">Worked</div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-3xl font-semibold">{{ avg(totals) }}</div>
                    <div class="text-sm text-muted-foreground">
                        Yards per call
                    </div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-3xl font-semibold">
                        {{ totals.touchdowns }}
                    </div>
                    <div class="text-sm text-muted-foreground">Touchdowns</div>
                </div>
            </div>

            <section class="flex flex-col gap-2">
                <h2 class="text-sm font-medium text-muted-foreground">
                    Success rate by down
                </h2>
                <ul class="flex flex-col gap-2 rounded-xl border p-4">
                    <li
                        v-for="d in byDown"
                        :key="d.down"
                        class="flex items-center gap-3 text-sm"
                    >
                        <span class="w-12"
                            >{{ d.down }}{{ ORDINAL[d.down] }}</span
                        >
                        <div
                            class="h-3 flex-1 overflow-hidden rounded-full bg-muted"
                            role="img"
                            :aria-label="`${pct(d)}% of ${d.calls} calls worked`"
                        >
                            <div
                                class="h-full rounded-full bg-chart-1"
                                :style="{ width: `${pct(d)}%` }"
                            />
                        </div>
                        <span class="w-28 text-right text-muted-foreground">
                            {{
                                d.calls
                                    ? `${pct(d)}% of ${d.calls}`
                                    : 'no calls'
                            }}
                        </span>
                    </li>
                </ul>
                <p class="text-xs text-muted-foreground">
                    Calls logged without a down are not counted here.
                </p>
            </section>

            <section class="flex flex-col gap-2">
                <h2 class="text-sm font-medium text-muted-foreground">
                    Most-called plays
                </h2>
                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-sm">
                        <thead class="text-left text-muted-foreground">
                            <tr>
                                <th class="p-3 font-medium">Play</th>
                                <th class="p-3 text-right font-medium">
                                    Calls
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Worked
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Yds/call
                                </th>
                                <th class="p-3 text-right font-medium">TD</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="p in byPlay" :key="p.name">
                                <td class="p-3">
                                    {{ p.name }}
                                    <Badge
                                        v-if="p.category"
                                        variant="outline"
                                        class="ml-1 capitalize"
                                        >{{ p.category }}</Badge
                                    >
                                </td>
                                <td class="p-3 text-right">{{ p.calls }}</td>
                                <td class="p-3 text-right">{{ pct(p) }}%</td>
                                <td class="p-3 text-right">{{ avg(p) }}</td>
                                <td class="p-3 text-right">
                                    {{ p.touchdowns }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="byPlayer.length" class="flex flex-col gap-2">
                <h2 class="text-sm font-medium text-muted-foreground">
                    Touches by player
                </h2>
                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-sm">
                        <thead class="text-left text-muted-foreground">
                            <tr>
                                <th class="p-3 font-medium">Player</th>
                                <th class="p-3 text-right font-medium">
                                    Touches
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Yards
                                </th>
                                <th class="p-3 text-right font-medium">TD</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="p in byPlayer" :key="p.name + p.number">
                                <td class="p-3">
                                    <span
                                        v-if="p.number"
                                        class="text-muted-foreground"
                                        >#{{ p.number }}</span
                                    >
                                    {{ p.name }}
                                </td>
                                <td class="p-3 text-right">{{ p.calls }}</td>
                                <td class="p-3 text-right">{{ p.yards }}</td>
                                <td class="p-3 text-right">
                                    {{ p.touchdowns }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="byGame.length" class="flex flex-col gap-2">
                <h2 class="text-sm font-medium text-muted-foreground">
                    By game
                </h2>
                <ul class="divide-y rounded-xl border text-sm">
                    <li
                        v-for="g in byGame"
                        :key="g.name + g.date"
                        class="flex items-center gap-3 p-3"
                    >
                        <span class="flex-1 font-medium">{{ g.name }}</span>
                        <span class="text-muted-foreground"
                            >{{ g.calls }} calls</span
                        >
                        <span class="w-16 text-right">{{ pct(g) }}%</span>
                        <span class="w-20 text-right">{{ g.yards }} yds</span>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
