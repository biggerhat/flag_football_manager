<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarSync, ChevronLeft, ChevronRight, Copy } from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Input } from '@/components/ui/input';
import { store as createFeed } from '@/routes/calendar/feed';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCanEdit } from '@/composables/useCanEdit';
import { calendar } from '@/routes';
import { create as createGame, edit as editGame } from '@/routes/gameplans';
import {
    create as createPractice,
    duplicate,
    edit as editPractice,
} from '@/routes/practices';

type Practice = {
    id: number;
    name: string;
    date: string;
    start_time: string | null;
    location: string | null;
};
type Game = {
    id: number;
    name: string;
    opponent: string | null;
    date: string;
    kickoff: string | null;
    location: string | null;
    score_for: number | null;
    score_against: number | null;
    result: 'W' | 'L' | 'T' | null;
};

const props = defineProps<{
    feedUrl: string | null;
    familyUrl: string | null;
    month: string;
    today: string;
    practices: Practice[];
    games: Game[];
    templates: { id: number; name: string }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Calendar', href: calendar() }] },
});

const canEdit = useCanEdit();

const first = computed(() => new Date(`${props.month}-01T00:00:00`));
const title = computed(() =>
    first.value.toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    }),
);

const shift = (delta: number): string => {
    const d = new Date(first.value);
    d.setMonth(d.getMonth() + delta);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};

const iso = (d: Date): string =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

/** Weeks starting on Sunday, padded with the neighbouring months' days. */
const weeks = computed(() => {
    const start = new Date(first.value);
    start.setDate(1 - start.getDay());
    const days: { date: string; inMonth: boolean }[] = [];
    const cursor = new Date(start);

    while (days.length < 42) {
        days.push({
            date: iso(cursor),
            inMonth: cursor.getMonth() === first.value.getMonth(),
        });
        cursor.setDate(cursor.getDate() + 1);
    }

    const rows = [];

    for (let i = 0; i < 42; i += 7) {
        rows.push(days.slice(i, i + 7));
    }

    // Drop a trailing week that is entirely the next month.
    return rows.filter((row) => row.some((d) => d.inMonth));
});

const on = <T extends { date: string }>(items: T[], date: string): T[] =>
    items.filter((i) => i.date === date);

function schedule(date: string, event: Event) {
    const select = event.target as HTMLSelectElement;
    const id = Number(select.value);
    select.value = '';

    if (id) {
        router.post(duplicate.url(id), { date });
    }
}

const time = (t: string | null) => (t ? t.slice(0, 5) : '');

const showFeed = ref(false);

async function copyFeed() {
    try {
        await navigator.clipboard.writeText(props.feedUrl ?? '');
        toast.success('Link copied.');
    } catch {
        toast.error('Copy failed. Select the link and copy it by hand.');
    }
}
</script>

<template>
    <Head title="Calendar" />

    <div class="flex flex-col gap-4 p-4">
        <div
            v-if="showFeed"
            class="flex flex-col gap-2 rounded-xl border p-4 text-sm"
        >
            <p>
                Add this link to Google Calendar, Apple Calendar or Outlook
                ("subscribe from URL") to see practices and games on your phone.
                Anyone with the link can see the schedule, so only share it with
                your team.
            </p>
            <div v-if="feedUrl" class="flex gap-2">
                <Input
                    :model-value="feedUrl"
                    readonly
                    aria-label="Calendar feed link"
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <Button variant="outline" @click="copyFeed"
                    ><Copy /> Copy</Button
                >
            </div>
            <div class="flex gap-2">
                <Button
                    size="sm"
                    :variant="feedUrl ? 'ghost' : 'default'"
                    @click="
                        router.post(
                            createFeed.url(),
                            {},
                            { preserveScroll: true },
                        )
                    "
                >
                    {{
                        feedUrl
                            ? 'Make a new link (the old one stops working)'
                            : 'Create link'
                    }}
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">{{ title }}</h1>
            <Button variant="outline" size="sm" @click="showFeed = !showFeed">
                <CalendarSync /> Subscribe
            </Button>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Previous month"
                    as-child
                >
                    <Link :href="calendar({ query: { month: shift(-1) } })"
                        ><ChevronLeft
                    /></Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="calendar()">Today</Link>
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Next month"
                    as-child
                >
                    <Link :href="calendar({ query: { month: shift(1) } })"
                        ><ChevronRight
                    /></Link>
                </Button>
            </div>
        </div>

        <div
            class="grid grid-cols-7 gap-px overflow-hidden rounded-xl border bg-border text-sm"
        >
            <div
                v-for="d in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']"
                :key="d"
                class="bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
            >
                {{ d }}
            </div>
            <template v-for="week in weeks" :key="week[0].date">
                <div
                    v-for="day in week"
                    :key="day.date"
                    class="group flex min-h-28 flex-col gap-1 bg-background p-1.5"
                    :class="day.inMonth ? '' : 'opacity-40'"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="flex size-6 items-center justify-center rounded-full text-xs"
                            :class="
                                day.date === today
                                    ? 'bg-primary font-semibold text-primary-foreground'
                                    : ''
                            "
                            >{{ Number(day.date.slice(8)) }}</span
                        >
                        <span
                            v-if="canEdit && day.inMonth"
                            class="flex gap-1 opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100"
                        >
                            <Link
                                :href="
                                    createPractice({
                                        query: { date: day.date },
                                    })
                                "
                                class="text-xs text-muted-foreground underline"
                                >+ Practice</Link
                            >
                            <Link
                                :href="
                                    createGame({ query: { date: day.date } })
                                "
                                class="text-xs text-muted-foreground underline"
                                >+ Game</Link
                            >
                        </span>
                    </div>

                    <Link
                        v-for="game in on(games, day.date)"
                        :key="`g${game.id}`"
                        :href="editGame(game.id)"
                        class="truncate rounded bg-primary/10 px-1.5 py-0.5 text-xs font-medium"
                    >
                        {{ time(game.kickoff) }}
                        {{ game.opponent ? `vs ${game.opponent}` : game.name }}
                        <Badge
                            v-if="game.result"
                            :variant="
                                game.result === 'L' ? 'destructive' : 'default'
                            "
                            class="ml-1 px-1 py-0 text-xs"
                            >{{ game.result }} {{ game.score_for }}–{{
                                game.score_against
                            }}</Badge
                        >
                    </Link>
                    <Link
                        v-for="practice in on(practices, day.date)"
                        :key="`p${practice.id}`"
                        :href="editPractice(practice.id)"
                        class="truncate rounded bg-muted px-1.5 py-0.5 text-xs"
                    >
                        {{ time(practice.start_time) }} {{ practice.name }}
                    </Link>

                    <select
                        v-if="canEdit && day.inMonth && templates.length"
                        class="mt-auto h-6 w-full rounded border border-input bg-background text-xs opacity-0 group-focus-within:opacity-100 group-hover:opacity-100"
                        :aria-label="`Schedule a template on ${day.date}`"
                        @change="schedule(day.date, $event)"
                    >
                        <option value="">From template…</option>
                        <option
                            v-for="t in templates"
                            :key="t.id"
                            :value="t.id"
                        >
                            {{ t.name }}
                        </option>
                    </select>
                </div>
            </template>
        </div>
    </div>
</template>
