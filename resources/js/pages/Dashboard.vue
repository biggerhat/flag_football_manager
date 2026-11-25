<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    BookOpen,
    CalendarPlus,
    Megaphone,
    PenLine,
    Swords,
} from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { PlayRecord } from '@/lib/diagram';
import { dashboard } from '@/routes';
import { index as drills } from '@/routes/drills';
import {
    edit as editGamePlan,
    gameday,
    index as gameplans,
} from '@/routes/gameplans';
import { index as playbooks } from '@/routes/playbooks';
import { create as createPlay, edit as editPlay } from '@/routes/plays';
import {
    create as createPractice,
    edit as editPractice,
    index as practices,
} from '@/routes/practices';

defineProps<{
    nextGame: {
        id: number;
        name: string;
        opponent: string | null;
        date: string | null;
        kickoff: string | null;
        location: string | null;
    } | null;
    record: { wins: number; losses: number; ties: number };
    attention: {
        unscoredGames: {
            id: number;
            name: string;
            opponent: string | null;
            date: string | null;
        }[];
        noAttendance: { id: number; name: string; date: string | null }[];
    };
    week: {
        practices: {
            id: number;
            name: string;
            date: string;
            start_time: string | null;
        }[];
        games: {
            id: number;
            name: string;
            opponent: string | null;
            date: string;
            kickoff: string | null;
        }[];
    };
    announcement: string | null;
    counts: { plays: number; drills: number; practices: number };
    recentPlays: Pick<
        PlayRecord,
        'id' | 'name' | 'category' | 'formation' | 'diagram'
    >[];
    upcomingPractice: {
        id: number;
        name: string;
        date: string | null;
        duration: number;
        segments_count: number;
        segments_sum_duration: number | null;
    } | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});

function daysUntil(date: string | null): string | null {
    if (!date) {
        return null;
    }

    const target = new Date(`${date.slice(0, 10)}T00:00:00`);
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    const days = Math.round((target.getTime() - now.getTime()) / 86_400_000);

    return days === 0 ? 'Today' : days === 1 ? 'Tomorrow' : `In ${days} days`;
}

function dayLabel(date: string): string {
    return new Date(`${date.slice(0, 10)}T00:00:00`).toLocaleDateString(
        undefined,
        { weekday: 'short', month: 'short', day: 'numeric' },
    );
}
</script>

<template>
    <Head title="Dashboard" />
    <h1 class="sr-only">Dashboard</h1>

    <div class="flex flex-col gap-6 p-4">
        <div
            v-if="announcement"
            class="flex items-start gap-3 rounded-xl border border-primary/40 bg-primary/5 p-4"
        >
            <Megaphone class="mt-0.5 size-5 shrink-0 text-primary" />
            <p class="text-sm whitespace-pre-line">{{ announcement }}</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <Link
                v-if="nextGame"
                :href="gameday(nextGame.id)"
                class="rounded-xl border border-primary/40 bg-primary/5 p-4 hover:bg-primary/10 lg:col-span-2"
            >
                <div class="flex items-center gap-2 text-sm text-primary">
                    <Swords class="size-4" /> Next game ·
                    {{ daysUntil(nextGame.date) }}
                </div>
                <div class="pt-1 text-2xl font-semibold">
                    {{
                        nextGame.opponent
                            ? `vs ${nextGame.opponent}`
                            : nextGame.name
                    }}
                </div>
                <div class="text-sm text-muted-foreground">
                    {{ nextGame.date && dayLabel(nextGame.date) }}
                    <template v-if="nextGame.kickoff">
                        · {{ nextGame.kickoff }}</template
                    >
                    <template v-if="nextGame.location">
                        · {{ nextGame.location }}</template
                    >
                </div>
            </Link>
            <EmptyState
                v-else
                class="lg:col-span-2"
                :icon="Swords"
                title="No upcoming game"
                description="Create a game plan with a date to see it here."
            >
                <Button variant="outline" as-child>
                    <Link :href="gameplans()">Game plans</Link>
                </Button>
            </EmptyState>

            <Link
                :href="gameplans()"
                class="rounded-xl border p-4 hover:bg-accent"
            >
                <div class="text-sm text-muted-foreground">Season record</div>
                <div class="text-3xl font-semibold">
                    {{ record.wins }}–{{ record.losses
                    }}<template v-if="record.ties">–{{ record.ties }}</template>
                </div>
                <div class="text-xs text-muted-foreground">
                    {{ counts.plays }} plays · {{ counts.drills }} drills ·
                    {{ counts.practices }} practices
                </div>
            </Link>
        </div>

        <section
            v-if="
                attention.unscoredGames.length || attention.noAttendance.length
            "
            class="flex flex-col gap-2 rounded-xl border border-warning/40 bg-warning/5 p-4"
        >
            <h2 class="flex items-center gap-2 text-sm font-medium">
                <AlertCircle class="size-4 text-warning" /> Needs attention
            </h2>
            <ul class="flex flex-col gap-1 text-sm">
                <li v-for="g in attention.unscoredGames" :key="`g${g.id}`">
                    <Link
                        :href="gameday(g.id)"
                        class="underline-offset-2 hover:underline"
                    >
                        Enter the score for
                        {{ g.opponent ? `vs ${g.opponent}` : g.name }}
                    </Link>
                </li>
                <li v-for="p in attention.noAttendance" :key="`p${p.id}`">
                    <Link
                        :href="editPractice(p.id)"
                        class="underline-offset-2 hover:underline"
                    >
                        Take attendance for {{ p.name }}
                        <span class="text-muted-foreground"
                            >({{ p.date && dayLabel(p.date) }})</span
                        >
                    </Link>
                </li>
            </ul>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-sm font-medium text-muted-foreground">This week</h2>
            <p
                v-if="!week.practices.length && !week.games.length"
                class="text-sm text-muted-foreground"
            >
                Nothing scheduled in the next 7 days.
            </p>
            <ul v-else class="divide-y rounded-xl border">
                <li v-for="g in week.games" :key="`g${g.id}`">
                    <Link
                        :href="editGamePlan(g.id)"
                        class="flex items-center gap-3 p-3 hover:bg-accent"
                    >
                        <Badge>Game</Badge>
                        <span class="flex-1 font-medium">{{
                            g.opponent ? `vs ${g.opponent}` : g.name
                        }}</span>
                        <span class="text-sm text-muted-foreground">{{
                            dayLabel(g.date)
                        }}</span>
                    </Link>
                </li>
                <li v-for="p in week.practices" :key="`p${p.id}`">
                    <Link
                        :href="editPractice(p.id)"
                        class="flex items-center gap-3 p-3 hover:bg-accent"
                    >
                        <Badge variant="secondary">Practice</Badge>
                        <span class="flex-1 font-medium">{{ p.name }}</span>
                        <span class="text-sm text-muted-foreground">{{
                            dayLabel(p.date)
                        }}</span>
                    </Link>
                </li>
            </ul>
        </section>

        <div class="flex flex-wrap gap-2">
            <Button as-child
                ><Link :href="createPlay()"
                    ><PenLine /> Design a play</Link
                ></Button
            >
            <Button variant="outline" as-child
                ><Link :href="createPractice()"
                    ><CalendarPlus /> Build a practice</Link
                ></Button
            >
        </div>

        <section class="flex flex-col gap-2">
            <h2 class="text-sm font-medium text-muted-foreground">
                Next practice
            </h2>
            <Link
                v-if="upcomingPractice"
                :href="editPractice(upcomingPractice.id)"
                class="rounded-xl border p-4 hover:bg-accent"
            >
                <div class="font-medium">{{ upcomingPractice.name }}</div>
                <div class="flex gap-1 pt-1">
                    <Badge variant="outline">{{ upcomingPractice.date }}</Badge>
                    <Badge variant="secondary"
                        >{{ upcomingPractice.segments_count }} activities ·
                        {{ upcomingPractice.segments_sum_duration ?? 0 }} /
                        {{ upcomingPractice.duration }} min</Badge
                    >
                </div>
            </Link>
            <EmptyState
                v-else
                :icon="CalendarPlus"
                title="No upcoming practice"
                description="Build one and give it a date."
            />
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-sm font-medium text-muted-foreground">
                Recent plays
            </h2>
            <EmptyState
                v-if="!recentPlays.length"
                :icon="BookOpen"
                title="No plays yet"
                description="Design your first play to start your playbook."
            >
                <Button as-child>
                    <Link :href="createPlay()"><PenLine /> Design a play</Link>
                </Button>
            </EmptyState>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    v-for="play in recentPlays"
                    :key="play.id"
                    :href="editPlay(play.id)"
                    class="overflow-hidden rounded-xl border hover:bg-accent"
                >
                    <DiagramCanvas :diagram="play.diagram" />
                    <div class="p-3">
                        <div class="font-medium">{{ play.name }}</div>
                        <div class="text-xs text-muted-foreground capitalize">
                            {{ play.category }}
                        </div>
                    </div>
                </Link>
            </div>
        </section>
    </div>
</template>
