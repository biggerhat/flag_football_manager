<script setup lang="ts">
import AnnouncementSection from '@/components/team/AnnouncementSection.vue';
import BackupSection from '@/components/team/BackupSection.vue';
import SeasonsSection from '@/components/team/SeasonsSection.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Copy, Crown, LogOut, Trash2, UserPlus, X } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCanEdit } from '@/composables/useCanEdit';
import { useDialog } from '@/composables/useDialog';
import { show, invite } from '@/routes/team';
import { cancel } from '@/routes/team/invitations';
import {
    destroy as removeMember,
    update as setRole,
} from '@/routes/team/members';
import { destroy, leave, store, switchMethod, update } from '@/routes/teams';

type Member = {
    id: number;
    name: string;
    email: string;
    role: string;
    via_club: boolean;
};
type Invitation = {
    id: number;
    email: string;
    role: string;
    url: string;
    expired: boolean;
};

const timezones = computed(() => {
    const all = (
        Intl as unknown as { supportedValuesOf?: (key: string) => string[] }
    ).supportedValuesOf?.('timeZone') ?? ['UTC'];

    return all.includes(props.team.timezone)
        ? all
        : [props.team.timezone, ...all];
});

const props = defineProps<{
    team: {
        id: number;
        name: string;
        announcement: string | null;
        timezone: string;
    };
    role: string;
    members: Member[];
    invitations: Invitation[];
    roles: string[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Team', href: show() }] },
});

const isOwner = computed(() => props.role === 'owner');
const { confirm, prompt } = useDialog();
const canEdit = useCanEdit();

const roleHelp: Record<string, string> = {
    owner: 'Manages the team and its members',
    coach: 'Can edit plays, practices and plans',
    viewer: 'Can view and print, but not change anything',
};

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm capitalize shadow-xs';

const inviteForm = useForm({
    email: '',
    role: 'coach',
    team_ids: [] as number[],
});
// Other teams this person owns, so one invitation can cover several.
const otherOwned = computed(
    () =>
        usePage().props.team?.teams.filter(
            (t) => t.id !== props.team.id && t.role === 'owner',
        ) ?? [],
);
const newTeam = useForm({ name: '' });
function sendInvite() {
    inviteForm.post(invite.url(), {
        preserveScroll: true,
        onSuccess: () => inviteForm.reset('email', 'team_ids'),
    });
}

async function copy(url: string) {
    try {
        await navigator.clipboard.writeText(url);
    } catch {
        await prompt({
            title: 'Invitation link',
            description: 'Copy this link and send it to them.',
            defaultValue: url,
            confirmText: 'Done',
        });
    }
}

async function rename() {
    const name = (
        await prompt({ title: 'Rename team', defaultValue: props.team.name })
    )?.trim();

    if (name && name !== props.team.name) {
        router.put(update.url(props.team.id), { name });
    }
}

function changeRole(member: Member, event: Event) {
    router.put(
        setRole.url(member.id),
        { role: (event.target as HTMLSelectElement).value },
        { preserveScroll: true },
    );
}

async function remove(member: Member) {
    if (
        await confirm({
            title: `Remove ${member.name}?`,
            description: 'They lose access to this team’s playbooks and plans.',
            confirmText: 'Remove',
            destructive: true,
        })
    ) {
        router.delete(removeMember.url(member.id), { preserveScroll: true });
    }
}

async function leaveTeam() {
    if (
        await confirm({
            title: `Leave ${props.team.name}?`,
            description: 'You will need a new invitation to come back.',
            confirmText: 'Leave',
            destructive: true,
        })
    ) {
        router.post(leave.url(props.team.id));
    }
}

async function deleteTeam() {
    if (
        await confirm({
            title: `Delete ${props.team.name}?`,
            description:
                'Every playbook, play, formation, drill, practice and game plan in this team is deleted for everyone. This cannot be undone.',
            confirmText: 'Delete team',
            destructive: true,
        })
    ) {
        router.delete(destroy.url(props.team.id));
    }
}
</script>

<template>
    <Head title="Team" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h1 class="text-xl font-semibold">{{ team.name }}</h1>
                <p class="text-sm text-muted-foreground">
                    Everyone on the team shares its playbooks, formations,
                    drills and plans.
                </p>
            </div>
            <Button v-if="isOwner" variant="outline" size="sm" @click="rename">
                Rename
            </Button>
        </div>

        <section v-if="isOwner" class="rounded-xl border">
            <h2 class="border-b px-4 py-3 font-medium">Timezone</h2>
            <div class="flex flex-col gap-2 p-4 text-sm">
                <p class="text-muted-foreground">
                    "Today", game countdowns and calendar reminders use this
                    timezone.
                </p>
                <select
                    :value="team.timezone"
                    aria-label="Team timezone"
                    class="h-9 max-w-xs rounded-md border border-input bg-background px-3 shadow-xs"
                    @change="
                        router.put(
                            update.url(team.id),
                            {
                                timezone: ($event.target as HTMLSelectElement)
                                    .value,
                            },
                            { preserveScroll: true },
                        )
                    "
                >
                    <option v-for="tz in timezones" :key="tz" :value="tz">
                        {{ tz }}
                    </option>
                </select>
            </div>
        </section>

        <SeasonsSection v-if="canEdit" />

        <BackupSection v-if="canEdit" />

        <AnnouncementSection
            v-if="canEdit || team.announcement"
            :team-id="team.id"
            :announcement-text="team.announcement"
        />

        <section class="rounded-xl border">
            <h2 class="border-b px-4 py-3 font-medium">Members</h2>
            <ul class="divide-y">
                <li
                    v-for="member in members"
                    :key="member.id"
                    class="flex flex-wrap items-center gap-3 px-4 py-3"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ member.name }}</p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ member.email }}
                        </p>
                    </div>
                    <Badge v-if="member.role === 'owner'" variant="secondary">
                        <Crown /> Owner
                    </Badge>
                    <Badge
                        v-else-if="member.via_club"
                        variant="outline"
                        class="capitalize"
                        title="This access comes from the club. Change it on the Club page."
                    >
                        {{ member.role }} · club
                    </Badge>
                    <select
                        v-else-if="isOwner"
                        :value="member.role"
                        :class="selectClass"
                        :aria-label="`Role for ${member.name}`"
                        @change="changeRole(member, $event)"
                    >
                        <option v-for="r in roles" :key="r" :value="r">
                            {{ r }}
                        </option>
                    </select>
                    <Badge v-else variant="outline" class="capitalize">
                        {{ member.role }}
                    </Badge>
                    <Button
                        v-if="
                            isOwner &&
                            member.role !== 'owner' &&
                            !member.via_club
                        "
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Remove ${member.name}`"
                        @click="remove(member)"
                    >
                        <Trash2 />
                    </Button>
                </li>
            </ul>
        </section>

        <section v-if="isOwner" class="rounded-xl border">
            <h2 class="border-b px-4 py-3 font-medium">Invite someone</h2>
            <form
                class="flex flex-wrap items-end gap-3 p-4"
                @submit.prevent="sendInvite"
            >
                <div class="grid min-w-56 flex-1 gap-1">
                    <Label for="invite-email">Email address</Label>
                    <Input
                        id="invite-email"
                        v-model="inviteForm.email"
                        type="email"
                        required
                        placeholder="assistant@example.com"
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="invite-role">Role</Label>
                    <select
                        id="invite-role"
                        v-model="inviteForm.role"
                        :class="selectClass"
                    >
                        <option v-for="r in roles" :key="r" :value="r">
                            {{ r }}
                        </option>
                    </select>
                </div>
                <Button type="submit" :disabled="inviteForm.processing">
                    <UserPlus /> Invite
                </Button>
                <fieldset v-if="otherOwned.length" class="w-full text-sm">
                    <legend class="mb-1 text-muted-foreground">
                        Also invite to
                    </legend>
                    <label
                        v-for="t in otherOwned"
                        :key="t.id"
                        class="mr-4 inline-flex items-center gap-1.5"
                    >
                        <input
                            v-model="inviteForm.team_ids"
                            type="checkbox"
                            :value="t.id"
                            class="size-4"
                        />
                        {{ t.name }}
                    </label>
                </fieldset>
                <div class="w-full space-y-1">
                    <InputError :message="inviteForm.errors.email" />
                    <p class="text-xs text-muted-foreground">
                        <span v-for="r in roles" :key="r" class="mr-3">
                            <strong class="capitalize">{{ r }}</strong
                            >: {{ roleHelp[r] }}.
                        </span>
                    </p>
                </div>
            </form>

            <ul v-if="invitations.length" class="divide-y border-t">
                <li
                    v-for="invitation in invitations"
                    :key="invitation.id"
                    class="flex flex-wrap items-center gap-3 px-4 py-3"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate">{{ invitation.email }}</p>
                        <p class="text-sm text-muted-foreground capitalize">
                            {{ invitation.role }} ·
                            {{ invitation.expired ? 'expired' : 'pending' }}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="copy(invitation.url)"
                    >
                        <Copy /> Copy link
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Cancel invitation"
                        @click="
                            router.delete(cancel.url(invitation.id), {
                                preserveScroll: true,
                            })
                        "
                    >
                        <X />
                    </Button>
                </li>
            </ul>
        </section>

        <section class="rounded-xl border">
            <h2 class="border-b px-4 py-3 font-medium">Your teams</h2>
            <div class="grid gap-3 p-4">
                <p class="text-sm text-muted-foreground">
                    Switch teams from the menu at the top of the sidebar. Start
                    a new one for another squad or season.
                </p>
                <form
                    class="flex flex-wrap items-end gap-3"
                    @submit.prevent="
                        newTeam.post(store.url(), {
                            onSuccess: () => newTeam.reset(),
                        })
                    "
                >
                    <div class="grid min-w-56 flex-1 gap-1">
                        <Label for="team-name">New team name</Label>
                        <Input
                            id="team-name"
                            v-model="newTeam.name"
                            required
                            maxlength="80"
                            placeholder="U10 Tigers"
                        />
                    </div>
                    <Button type="submit" variant="outline">Create team</Button>
                    <InputError :message="newTeam.errors.name" />
                </form>
            </div>
        </section>

        <section class="flex flex-wrap gap-2">
            <Button
                v-if="!isOwner"
                variant="outline"
                size="sm"
                @click="leaveTeam"
            >
                <LogOut /> Leave team
            </Button>
            <Button
                v-else
                variant="outline"
                size="sm"
                class="text-destructive"
                @click="deleteTeam"
            >
                <Trash2 /> Delete team
            </Button>
        </section>
    </div>
</template>
