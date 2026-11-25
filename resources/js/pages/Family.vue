<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarSync, Megaphone } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

type Event = { date: string; location: string | null };

defineProps<{
    team: string;
    announcement: string | null;
    feedUrl: string;
    practices: (Event & { name: string; start_time: string | null })[];
    games: (Event & {
        opponent: string | null;
        name: string;
        kickoff: string | null;
    })[];
}>();

const day = (date: string) =>
    new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
    });
</script>

<template>
    <Head :title="`${team} schedule`" />

    <main class="mx-auto flex max-w-2xl flex-col gap-6 p-4 py-8">
        <h1 class="text-2xl font-semibold">{{ team }}</h1>

        <div
            v-if="announcement"
            class="flex items-start gap-3 rounded-xl border border-primary/40 bg-primary/5 p-4"
        >
            <Megaphone class="mt-0.5 size-5 shrink-0 text-primary" />
            <p class="text-sm whitespace-pre-line">{{ announcement }}</p>
        </div>

        <section class="flex flex-col gap-2">
            <h2 class="text-sm font-medium text-muted-foreground">Games</h2>
            <p v-if="!games.length" class="text-sm text-muted-foreground">
                No upcoming games.
            </p>
            <ul v-else class="divide-y rounded-xl border">
                <li
                    v-for="(g, i) in games"
                    :key="i"
                    class="flex flex-col gap-0.5 p-3"
                >
                    <div class="font-medium">
                        {{ g.opponent ? `vs ${g.opponent}` : g.name }}
                    </div>
                    <div class="text-sm text-muted-foreground">
                        {{ day(g.date)
                        }}<template v-if="g.kickoff">
                            · {{ g.kickoff }}</template
                        ><template v-if="g.location">
                            · {{ g.location }}</template
                        >
                    </div>
                </li>
            </ul>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-sm font-medium text-muted-foreground">Practices</h2>
            <p v-if="!practices.length" class="text-sm text-muted-foreground">
                No upcoming practices.
            </p>
            <ul v-else class="divide-y rounded-xl border">
                <li
                    v-for="(p, i) in practices"
                    :key="i"
                    class="flex flex-col gap-0.5 p-3"
                >
                    <div class="font-medium">
                        {{ p.name }} <Badge variant="secondary">Practice</Badge>
                    </div>
                    <div class="text-sm text-muted-foreground">
                        {{ day(p.date)
                        }}<template v-if="p.start_time">
                            · {{ p.start_time.slice(0, 5) }}</template
                        ><template v-if="p.location">
                            · {{ p.location }}</template
                        >
                    </div>
                </li>
            </ul>
        </section>

        <p class="flex items-center gap-2 text-sm text-muted-foreground">
            <CalendarSync class="size-4" />
            Add to your phone's calendar:
            <a :href="feedUrl" class="underline">subscribe link</a>
        </p>
    </main>
</template>
