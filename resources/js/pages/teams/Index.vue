<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Plus, Users } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as club } from '@/routes/club';
import { show as manage } from '@/routes/team';
import { index, switchMethod } from '@/routes/teams';

type TeamCard = {
    id: number;
    name: string;
    role: string;
    members: number;
    active: boolean;
    club: string | null;
    record: { wins: number; losses: number; ties: number };
    nextGame: { name: string; date: string | null } | null;
    nextPractice: { name: string; date: string | null } | null;
};

defineProps<{ teams: TeamCard[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'My teams', href: index() }] },
});

const played = (t: TeamCard) =>
    t.record.wins + t.record.losses + t.record.ties > 0;
</script>

<template>
    <Head title="My teams" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">My teams</h1>
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="club()"><Users /> Club</Link>
                </Button>
                <Button as-child>
                    <Link :href="manage()"><Plus /> New team</Link>
                </Button>
            </div>
        </div>

        <EmptyState
            v-if="!teams.length"
            :icon="Users"
            title="No teams yet"
            description="Create a team to start building plays and practices."
        />

        <div class="grid gap-3 sm:grid-cols-2">
            <div
                v-for="team in teams"
                :key="team.id"
                class="flex flex-col gap-3 rounded-xl border p-4"
                :class="team.active && 'border-primary'"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="font-medium">{{ team.name }}</h2>
                        <div class="flex flex-wrap gap-1 pt-1">
                            <Badge variant="secondary" class="capitalize">{{
                                team.role
                            }}</Badge>
                            <Badge v-if="team.club" variant="outline">{{
                                team.club
                            }}</Badge>
                            <Badge variant="outline"
                                >{{ team.members }}
                                {{
                                    team.members === 1 ? 'member' : 'members'
                                }}</Badge
                            >
                        </div>
                    </div>
                    <span
                        v-if="team.active"
                        class="flex items-center gap-1 text-sm text-primary"
                        ><Check class="size-4" /> Active</span
                    >
                </div>

                <dl class="grid gap-1 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">Record</dt>
                        <dd>
                            <template v-if="played(team)"
                                >{{ team.record.wins }}–{{ team.record.losses
                                }}<template v-if="team.record.ties"
                                    >–{{ team.record.ties }}</template
                                ></template
                            >
                            <span v-else class="text-muted-foreground"
                                >No games yet</span
                            >
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">Next game</dt>
                        <dd class="text-right">
                            <template v-if="team.nextGame"
                                >{{ team.nextGame.name }} ·
                                {{ team.nextGame.date }}</template
                            >
                            <span v-else class="text-muted-foreground">—</span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">Next practice</dt>
                        <dd class="text-right">
                            <template v-if="team.nextPractice"
                                >{{ team.nextPractice.name }} ·
                                {{ team.nextPractice.date }}</template
                            >
                            <span v-else class="text-muted-foreground">—</span>
                        </dd>
                    </div>
                </dl>

                <Button
                    v-if="!team.active"
                    variant="outline"
                    size="sm"
                    class="self-start"
                    @click="router.post(switchMethod.url(team.id))"
                    >Switch to this team</Button
                >
                <Button
                    v-else
                    variant="outline"
                    size="sm"
                    class="self-start"
                    as-child
                >
                    <Link :href="manage()">Manage team</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
