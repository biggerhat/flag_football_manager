<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, Copy, Trash2, X } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useDialog } from '@/composables/useDialog';
import { destroy, show, store } from '@/routes/club';
import { cancel } from '@/routes/club/invitations';
import {
    invite as inviteMember,
    remove as removeMember,
    update as updateMember,
} from '@/routes/club/members';
import { add as addTeam, remove as removeTeam } from '@/routes/club/teams';

type Club = {
    id: number;
    name: string;
    isOwner: boolean;
    canManage: boolean;
    roles: string[];
    teams: { id: number; name: string; members: number }[];
    members: { id: number; name: string; email: string; role: string }[];
    invitations: { id: number; email: string; url: string; expired: boolean }[];
    addable: { id: number; name: string }[];
};

defineProps<{ club: Club | null }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Club', href: show() }] },
});

const { confirm } = useDialog();
const create = useForm({ name: '' });
const invite = useForm({ email: '', role: 'admin' });
const add = useForm({ team_id: '' as number | '' });

const roleHelp: Record<string, string> = {
    admin: 'Owner of every team in the club',
    coach: 'Can edit plays, practices and plans on every team',
    viewer: 'Can view and print on every team',
};
const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm capitalize shadow-xs';

async function copy(url: string) {
    try {
        await navigator.clipboard.writeText(url);
    } catch {
        // The link is shown on the page; copying by hand still works.
    }
}

async function dissolve() {
    if (
        await confirm({
            title: 'Delete this club?',
            description:
                'The teams and their data stay. Everyone loses the access the club gave them.',
            confirmText: 'Delete club',
            destructive: true,
        })
    ) {
        router.delete(destroy.url());
    }
}

async function takeOut(id: number, name: string) {
    if (
        await confirm({
            title: `Take ${name} out of the club?`,
            description:
                'Club members lose access to it unless they are members in their own right.',
            confirmText: 'Remove',
            destructive: true,
        })
    ) {
        router.delete(removeTeam.url(id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Club" />

    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Club</h1>

        <template v-if="!club">
            <EmptyState
                :icon="Building2"
                title="Run several teams together"
                description="A club groups your teams. Give people a role across all of them at once: administrators own every team, coaches can edit them, viewers can look."
            />
            <form
                class="flex max-w-md items-end gap-2"
                @submit.prevent="create.post(store.url())"
            >
                <div class="grid flex-1 gap-1">
                    <Label for="club-name">Club name</Label>
                    <Input
                        id="club-name"
                        v-model="create.name"
                        required
                        maxlength="80"
                        placeholder="Riverside Flag Football"
                    />
                    <InputError :message="create.errors.name" />
                </div>
                <Button type="submit" :disabled="create.processing"
                    >Start a club</Button
                >
            </form>
        </template>

        <template v-else>
            <p class="text-muted-foreground">
                <strong class="text-foreground">{{ club.name }}</strong> ·
                {{ club.teams.length }}
                {{ club.teams.length === 1 ? 'team' : 'teams' }}
            </p>

            <section class="rounded-xl border">
                <h2 class="border-b px-4 py-3 font-medium">Teams</h2>
                <ul v-if="club.teams.length" class="divide-y">
                    <li
                        v-for="t in club.teams"
                        :key="t.id"
                        class="flex items-center gap-3 px-4 py-2 text-sm"
                    >
                        <span class="flex-1 font-medium">{{ t.name }}</span>
                        <span class="text-muted-foreground"
                            >{{ t.members }} members</span
                        >
                        <Button
                            v-if="club.canManage"
                            size="icon-sm"
                            variant="ghost"
                            :aria-label="`Take ${t.name} out of the club`"
                            @click="takeOut(t.id, t.name)"
                        >
                            <X />
                        </Button>
                    </li>
                </ul>
                <p v-else class="p-4 text-sm text-muted-foreground">
                    No teams in the club yet.
                </p>
                <form
                    v-if="club.canManage && club.addable.length"
                    class="flex flex-wrap items-center gap-2 border-t p-4"
                    @submit.prevent="
                        add.post(addTeam.url(), {
                            preserveScroll: true,
                            onSuccess: () => add.reset(),
                        })
                    "
                >
                    <select
                        v-model="add.team_id"
                        required
                        aria-label="Team to add"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                    >
                        <option value="" disabled>Add a team you own…</option>
                        <option
                            v-for="t in club.addable"
                            :key="t.id"
                            :value="t.id"
                        >
                            {{ t.name }}
                        </option>
                    </select>
                    <Button type="submit" size="sm" :disabled="add.processing"
                        >Add to club</Button
                    >
                </form>
            </section>

            <section class="rounded-xl border">
                <h2 class="border-b px-4 py-3 font-medium">People</h2>
                <p class="px-4 pt-3 text-sm text-muted-foreground">
                    Their role applies to every team in the club, including
                    teams added later.
                </p>
                <ul class="divide-y">
                    <li
                        v-for="m in club.members"
                        :key="m.id"
                        class="flex flex-wrap items-center gap-3 px-4 py-2 text-sm"
                    >
                        <span class="min-w-40 flex-1"
                            >{{ m.name }}
                            <span class="text-muted-foreground">{{
                                m.email
                            }}</span></span
                        >
                        <Badge v-if="m.role === 'owner'" variant="secondary"
                            >Owner</Badge
                        >
                        <template v-else-if="club.isOwner">
                            <select
                                :value="m.role"
                                :class="selectClass"
                                :aria-label="`Role for ${m.name}`"
                                @change="
                                    router.put(
                                        updateMember.url(m.id),
                                        {
                                            role: (
                                                $event.target as HTMLSelectElement
                                            ).value,
                                        },
                                        { preserveScroll: true },
                                    )
                                "
                            >
                                <option
                                    v-for="r in club.roles"
                                    :key="r"
                                    :value="r"
                                >
                                    {{ r }}
                                </option>
                            </select>
                            <Button
                                size="icon-sm"
                                variant="ghost"
                                :aria-label="`Remove ${m.name}`"
                                @click="
                                    router.delete(removeMember.url(m.id), {
                                        preserveScroll: true,
                                    })
                                "
                            >
                                <Trash2 />
                            </Button>
                        </template>
                        <Badge v-else variant="outline" class="capitalize">{{
                            m.role
                        }}</Badge>
                    </li>
                </ul>

                <ul
                    v-if="club.invitations.length"
                    class="divide-y border-t text-sm"
                >
                    <li
                        v-for="i in club.invitations"
                        :key="i.id"
                        class="flex flex-wrap items-center gap-2 px-4 py-2"
                    >
                        <span class="flex-1">{{ i.email }}</span>
                        <Badge variant="outline">{{
                            i.expired ? 'Expired' : 'Invited'
                        }}</Badge>
                        <Button
                            v-if="!i.expired"
                            size="sm"
                            variant="outline"
                            @click="copy(i.url)"
                            ><Copy /> Copy link</Button
                        >
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Cancel invitation"
                            @click="
                                router.delete(cancel.url(i.id), {
                                    preserveScroll: true,
                                })
                            "
                        >
                            <X />
                        </Button>
                    </li>
                </ul>

                <form
                    v-if="club.isOwner"
                    class="flex flex-wrap items-end gap-3 border-t p-4"
                    @submit.prevent="
                        invite.post(inviteMember.url(), {
                            preserveScroll: true,
                            onSuccess: () => invite.reset('email'),
                        })
                    "
                >
                    <div class="grid min-w-56 flex-1 gap-1">
                        <Label for="member-email">Invite to the club</Label>
                        <Input
                            id="member-email"
                            v-model="invite.email"
                            type="email"
                            required
                            placeholder="coach@example.com"
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label for="member-role">Role</Label>
                        <select
                            id="member-role"
                            v-model="invite.role"
                            :class="selectClass"
                        >
                            <option v-for="r in club.roles" :key="r" :value="r">
                                {{ r }}
                            </option>
                        </select>
                    </div>
                    <Button type="submit" :disabled="invite.processing"
                        >Create invitation</Button
                    >
                    <div class="w-full space-y-1">
                        <InputError :message="invite.errors.email" />
                        <p class="text-xs text-muted-foreground">
                            <span v-for="r in club.roles" :key="r" class="mr-3">
                                <strong class="capitalize">{{ r }}</strong
                                >: {{ roleHelp[r] }}.
                            </span>
                        </p>
                    </div>
                </form>
            </section>

            <section v-if="club.isOwner" class="rounded-xl border">
                <h2 class="border-b px-4 py-3 font-medium">Delete club</h2>
                <div class="p-4">
                    <Button variant="destructive" size="sm" @click="dissolve"
                        >Delete club</Button
                    >
                </div>
            </section>
        </template>
    </div>
</template>
