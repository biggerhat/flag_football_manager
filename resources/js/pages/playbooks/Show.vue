<script setup lang="ts">
import CopyToTeam from '@/components/CopyToTeam.vue';
import { useCanEdit } from '@/composables/useCanEdit';
import { useOnce } from '@/composables/useOnce';
import { Head, Link, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import {
    BookOpen,
    Copy,
    Download,
    GitBranch,
    ListOrdered,
    Pencil,
    Printer,
    Plus,
    Share2,
    Star,
    Trash2,
    Watch,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { lastRunLabel } from '@/lib/dates';
import { offlineSupported, savePagesOffline } from '@/lib/offline';
import { CATEGORIES } from '@/lib/diagram';
import { useDialog } from '@/composables/useDialog';
import type { PlayRecord } from '@/lib/diagram';
import {
    index as playbooksIndex,
    packet,
    share,
    show,
} from '@/routes/playbooks';
import {
    create,
    destroy,
    bulk,
    duplicate,
    edit,
    favorite,
    move,
    variation,
} from '@/routes/plays';
import { plays as printPlays } from '@/routes/print';

const canEdit = useCanEdit();
const once = useOnce();
const props = defineProps<{
    playbook: {
        id: number;
        name: string;
        description: string | null;
        share_url: string | null;
        share_expires_at: string | null;
    };
    playbooks: { id: number; name: string }[];
    plays: PlayRecord[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Playbooks', href: playbooksIndex() }] },
});

const search = ref('');
const category = ref<string>('all');
const tag = ref<string | null>(null);
const folder = ref<string | null>(null);
const selected = ref<number[]>([]);
const onlyFavorites = ref(false);

const allTags = computed(() => [
    ...new Set(props.plays.flatMap((p) => p.tags ?? [])),
]);

const folders = computed(
    () =>
        [
            ...new Set(props.plays.map((p) => p.folder).filter(Boolean)),
        ].sort() as string[],
);

const nameById = computed(
    () => new Map(props.plays.map((p) => [p.id, p.name])),
);

const favoriteCount = computed(
    () => props.plays.filter((p) => p.is_favorite).length,
);

const filtered = computed(() =>
    props.plays
        .filter(
            (p) =>
                (category.value === 'all' || p.category === category.value) &&
                (!folder.value || p.folder === folder.value) &&
                (!tag.value || (p.tags ?? []).includes(tag.value)) &&
                (!onlyFavorites.value || p.is_favorite) &&
                p.name.toLowerCase().includes(search.value.toLowerCase()),
        )
        // Starred plays first; otherwise keep the server's order.
        .sort((a, b) => Number(!!b.is_favorite) - Number(!!a.is_favorite)),
);

function bulkAction(
    action: 'star' | 'unstar' | 'tag' | 'delete',
    tagName?: string,
) {
    router.post(
        bulk.url(),
        { ids: selected.value, action, tag: tagName ?? null },
        { preserveScroll: true },
    );
}

async function deleteSelected() {
    if (
        await confirm({
            title: `Delete ${selected.value.length} plays?`,
            description:
                'They are removed from practices and game plans. You can restore them from Recently deleted.',
            confirmText: 'Delete',
            destructive: true,
        })
    ) {
        bulkAction('delete');
        selected.value = [];
    }
}

async function tagSelected() {
    const name = await prompt({
        title: 'Add a tag',
        description: `The tag is added to ${selected.value.length} selected plays.`,
        confirmText: 'Add tag',
    });

    if (name?.trim()) {
        bulkAction('tag', name.trim());
    }
}

function toggleAll() {
    selected.value =
        selected.value.length === filtered.value.length
            ? []
            : filtered.value.map((p) => p.id);
}

function toggle(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((i) => i !== id)
        : [...selected.value, id];
}

const printUrl = computed(() =>
    printPlays.url({ query: { ids: selected.value.join(',') } }),
);

const callSheetUrl = computed(() =>
    printPlays.url({
        query: {
            ids: filtered.value.map((p) => p.id).join(','),
            style: 'callsheet',
        },
    }),
);

const wristbandUrl = computed(() =>
    printPlays.url({
        query: {
            ids: filtered.value.map((p) => p.id).join(','),
            style: 'wristband',
        },
    }),
);

const otherPlaybooks = computed(() =>
    props.playbooks.filter((p) => p.id !== props.playbook.id),
);

function moveSelected(event: Event) {
    const select = event.target as HTMLSelectElement;
    const id = Number(select.value);
    select.value = '';

    if (id) {
        router.post(
            move.url(),
            { ids: selected.value, playbook_id: id },
            { onSuccess: () => (selected.value = []) },
        );
    }
}

const { confirm, prompt } = useDialog();

/** Keeps this playbook's pages readable on the sideline without a signal. */
async function saveOffline() {
    const saved = await savePagesOffline([
        playbooksIndex.url(),
        show.url(props.playbook.id),
        packet.url(props.playbook.id),
        callSheetUrl.value,
        wristbandUrl.value,
    ]);

    toast[saved ? 'success' : 'error'](
        saved
            ? 'Saved for offline use. Open the Packet when you have no signal.'
            : 'Could not save for offline use.',
    );
}

/** Turns the read-only link on, or shows it so it can be copied. */
async function sharing() {
    if (!props.playbook.share_url) {
        router.post(share.url(props.playbook.id), { enabled: true });

        return;
    }

    const link = props.playbook.share_url;
    const copy = await prompt({
        title: 'Share this playbook',
        description: `Anyone with this link can view the plays without an account. They cannot change anything.${props.playbook.share_expires_at ? ` The link works until ${props.playbook.share_expires_at}.` : ''}`,
        defaultValue: link,
        confirmText: 'Copy link',
    });

    if (copy !== null) {
        try {
            await navigator.clipboard.writeText(link);
        } catch {
            // The link is selectable in the dialog; copying by hand still works.
        }
    }
}

async function stopSharing() {
    if (
        await confirm({
            title: 'Stop sharing?',
            description: 'The current link stops working for everyone.',
            confirmText: 'Stop sharing',
            destructive: true,
        })
    ) {
        router.post(share.url(props.playbook.id), { enabled: false });
    }
}

async function remove(play: PlayRecord) {
    if (
        await confirm({
            title: `Delete "${play.name}"?`,
            description:
                'The play is removed from practices and game plans too.',
            confirmText: 'Delete',
            destructive: true,
        })
    ) {
        router.delete(destroy.url(play.id));
    }
}
</script>

<template>
    <Head :title="playbook.name" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">{{ playbook.name }}</h1>
            <div class="flex flex-wrap items-center gap-2">
                <select
                    v-if="canEdit && selected.length && otherPlaybooks.length"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                    aria-label="Move selected plays to another playbook"
                    @change="moveSelected"
                >
                    <option value="">Move {{ selected.length }} to…</option>
                    <option
                        v-for="p in otherPlaybooks"
                        :key="p.id"
                        :value="p.id"
                    >
                        {{ p.name }}
                    </option>
                </select>
                <template v-if="canEdit && selected.length">
                    <Button variant="outline" @click="bulkAction('star')"
                        ><Star /> Star</Button
                    >
                    <Button variant="outline" @click="bulkAction('unstar')"
                        >Unstar</Button
                    >
                    <Button variant="outline" @click="tagSelected"
                        >Add tag…</Button
                    >
                </template>
                <Button
                    v-if="filtered.length > 1"
                    variant="ghost"
                    @click="toggleAll"
                >
                    {{
                        selected.length === filtered.length
                            ? 'Clear selection'
                            : 'Select all'
                    }}
                </Button>
                <Button v-if="selected.length" variant="outline" as-child>
                    <a :href="printUrl" target="_blank"
                        ><Printer /> Print {{ selected.length }} selected</a
                    >
                </Button>
                <Button v-if="filtered.length" variant="outline" as-child>
                    <a :href="callSheetUrl" target="_blank"
                        ><ListOrdered /> Call sheet</a
                    >
                </Button>
                <Button v-if="plays.length" variant="outline" as-child>
                    <a :href="packet.url(playbook.id)" target="_blank"
                        ><BookOpen /> Packet</a
                    >
                </Button>
                <Button
                    v-if="plays.length && offlineSupported()"
                    variant="outline"
                    title="Keep this playbook readable without a connection"
                    @click="saveOffline"
                >
                    <Download /> Save offline
                </Button>
                <Button
                    v-if="canEdit && plays.length"
                    variant="outline"
                    @click="sharing"
                >
                    <Share2 />
                    {{ playbook.share_url ? 'Sharing on' : 'Share' }}
                </Button>
                <Button
                    v-if="canEdit && playbook.share_url"
                    variant="ghost"
                    size="sm"
                    @click="stopSharing"
                >
                    Stop sharing
                </Button>
                <Button v-if="filtered.length" variant="outline" as-child>
                    <a :href="wristbandUrl" target="_blank"
                        ><Watch /> Wristband</a
                    >
                </Button>
                <Button v-if="canEdit" as-child>
                    <Link :href="create({ query: { playbook: playbook.id } })"
                        ><Plus /> New play</Link
                    >
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <Input
                v-model="search"
                placeholder="Search plays"
                class="max-w-xs"
            />
            <select
                v-model="category"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm capitalize shadow-xs"
            >
                <option value="all">All categories</option>
                <option v-for="c in CATEGORIES" :key="c" :value="c">
                    {{ c }}
                </option>
            </select>
            <Button
                v-if="favoriteCount"
                :variant="onlyFavorites ? 'default' : 'outline'"
                :aria-pressed="onlyFavorites"
                @click="onlyFavorites = !onlyFavorites"
            >
                <Star /> Favorites ({{ favoriteCount }})
            </Button>
        </div>

        <div v-if="folders.length" class="flex flex-wrap gap-1">
            <Button
                size="sm"
                :variant="folder === null ? 'default' : 'outline'"
                @click="folder = null"
            >
                All folders
            </Button>
            <Button
                v-for="f in folders"
                :key="f"
                size="sm"
                :variant="folder === f ? 'default' : 'outline'"
                @click="folder = folder === f ? null : f"
            >
                {{ f }}
            </Button>
        </div>

        <div v-if="allTags.length" class="flex flex-wrap gap-1">
            <Button
                v-for="t in allTags"
                :key="t"
                size="sm"
                :variant="tag === t ? 'default' : 'outline'"
                @click="tag = tag === t ? null : t"
            >
                {{ t }}
            </Button>
        </div>

        <p
            v-if="!plays.length"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            No plays in this playbook yet. Create your first play to get
            started.
        </p>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            <div
                v-for="play in filtered"
                :key="play.id"
                class="flex flex-col overflow-hidden rounded-xl border"
            >
                <div class="relative">
                    <DiagramCanvas :diagram="play.diagram" />
                    <button
                        v-if="canEdit"
                        type="button"
                        class="absolute top-2 right-2 rounded-full bg-background/80 p-1.5 hover:bg-background"
                        :aria-pressed="!!play.is_favorite"
                        :aria-label="`${play.is_favorite ? 'Unstar' : 'Star'} ${play.name}`"
                        @click="
                            router.post(
                                favorite.url(play.id),
                                {},
                                { preserveScroll: true },
                            )
                        "
                    >
                        <Star
                            class="size-4"
                            :class="
                                play.is_favorite
                                    ? 'fill-warning text-warning'
                                    : 'text-muted-foreground'
                            "
                        />
                    </button>
                    <input
                        type="checkbox"
                        class="absolute top-2 left-2 size-5"
                        :checked="selected.includes(play.id)"
                        :aria-label="`Select ${play.name} for printing`"
                        @change="toggle(play.id)"
                    />
                </div>
                <div class="flex flex-1 flex-col gap-2 p-3">
                    <div class="font-medium">{{ play.name }}</div>
                    <div class="flex flex-wrap gap-1">
                        <Badge variant="secondary" class="capitalize">{{
                            play.category
                        }}</Badge>
                        <Badge v-if="play.formation" variant="outline">{{
                            play.formation
                        }}</Badge>
                        <Badge variant="outline" class="capitalize">{{
                            play.difficulty
                        }}</Badge>
                        <Badge
                            v-for="t in play.tags ?? []"
                            :key="t"
                            variant="outline"
                            >#{{ t }}</Badge
                        >
                        <Badge v-if="play.beginner_friendly" variant="outline"
                            >Beginner</Badge
                        >
                        <Badge v-if="play.folder" variant="secondary">{{
                            play.folder
                        }}</Badge>
                        <Badge v-if="play.parent_id" variant="outline"
                            >Variation of
                            {{
                                nameById.get(play.parent_id) ?? 'deleted'
                            }}</Badge
                        >
                        <Badge variant="outline">{{
                            lastRunLabel(play.last_run)
                        }}</Badge>
                        <Badge
                            v-if="play.stats"
                            variant="secondary"
                            :title="`${play.stats.success} of ${play.stats.calls} calls worked`"
                            >Called {{ play.stats.calls }}× ·
                            {{
                                Math.round(
                                    (play.stats.success / play.stats.calls) *
                                        100,
                                )
                            }}% ·
                            {{
                                (play.stats.yards / play.stats.calls).toFixed(1)
                            }}
                            yds</Badge
                        >
                    </div>
                    <div class="mt-auto flex gap-1 pt-2">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="edit(play.id)"
                                ><Pencil />
                                {{ canEdit ? 'Edit' : 'View' }}</Link
                            >
                        </Button>
                        <Button
                            v-if="canEdit"
                            variant="outline"
                            size="icon-sm"
                            title="Duplicate"
                            @click="once.post(duplicate.url(play.id))"
                            :disabled="once.pending.value"
                        >
                            <Copy />
                        </Button>
                        <Button
                            v-if="canEdit"
                            variant="outline"
                            size="icon-sm"
                            title="Create a variation"
                            @click="once.post(variation.url(play.id))"
                            :disabled="once.pending.value"
                        >
                            <GitBranch />
                        </Button>
                        <CopyToTeam
                            v-if="canEdit"
                            kind="play"
                            :id="play.id"
                            :name="play.name"
                        />
                        <Button
                            v-if="canEdit"
                            variant="outline"
                            size="icon-sm"
                            title="Delete"
                            @click="remove(play)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
