<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useDialog } from '@/composables/useDialog';
import { versions as versionsRoute } from '@/routes/plays';
import { restore as restoreVersion } from '@/routes/plays/versions';

type Version = {
    id: number;
    name: string;
    author: string | null;
    saved_at: string;
    diagram: { players?: unknown[]; routes?: unknown[] };
};

const props = defineProps<{ playId: number; canEdit: boolean }>();

const { confirm } = useDialog();
const open = ref(false);
const loading = ref(false);
const failed = ref(false);
const versions = ref<Version[]>([]);

async function show() {
    open.value = true;
    loading.value = true;
    failed.value = false;

    try {
        const response = await fetch(versionsRoute.url(props.playId), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        versions.value = await response.json();
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

async function restore(version: Version) {
    if (
        await confirm({
            title: `Restore "${version.name}"?`,
            description:
                'The play goes back to this version. What it looks like now is kept in the history, so you can undo this.',
            confirmText: 'Restore',
        })
    ) {
        open.value = false;
        router.post(restoreVersion.url([props.playId, version.id]));
    }
}

const when = (iso: string) =>
    new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
</script>

<template>
    <Button variant="outline" size="sm" @click="show"
        ><History /> History</Button
    >

    <Dialog v-model:open="open">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Version history</DialogTitle>
                <DialogDescription>
                    Earlier saves of this play, newest first. The last 20 are
                    kept.
                </DialogDescription>
            </DialogHeader>

            <p v-if="loading" class="text-sm text-muted-foreground">Loading…</p>
            <p v-else-if="failed" class="text-sm text-destructive">
                Could not load the history.
            </p>
            <p
                v-else-if="!versions.length"
                class="text-sm text-muted-foreground"
            >
                No earlier versions yet. One is kept each time you save a
                change.
            </p>
            <ul v-else class="max-h-96 divide-y overflow-y-auto text-sm">
                <li
                    v-for="v in versions"
                    :key="v.id"
                    class="flex items-center gap-3 py-2"
                >
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ v.name }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ when(v.saved_at)
                            }}<template v-if="v.author">
                                · {{ v.author }}</template
                            >
                            · {{ v.diagram.players?.length ?? 0 }} players,
                            {{ v.diagram.routes?.length ?? 0 }} routes
                        </div>
                    </div>
                    <Button
                        v-if="canEdit"
                        variant="outline"
                        size="sm"
                        @click="restore(v)"
                        >Restore</Button
                    >
                </li>
            </ul>
        </DialogContent>
    </Dialog>
</template>
