<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { RotateCcw, Trash2 } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { restore, trash } from '@/routes';

type Item = { kind: string; id: number; name: string; deleted_at: string };

defineProps<{ items: Item[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recently deleted', href: trash() }] },
});

const LABELS: Record<string, string> = {
    play: 'Play',
    drill: 'Drill',
    formation: 'Formation',
    practice: 'Practice',
    gameplan: 'Game plan',
};

const when = (iso: string) =>
    new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
</script>

<template>
    <Head title="Recently deleted" />

    <div class="flex max-w-3xl flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold">Recently deleted</h1>
            <p class="text-sm text-muted-foreground">
                Anything you deleted in the last 30 days can be brought back.
            </p>
        </div>

        <EmptyState
            v-if="!items.length"
            :icon="Trash2"
            title="Nothing deleted"
            description="Deleted plays, drills, formations, practices and game plans show up here."
        />

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="item in items"
                :key="item.kind + item.id"
                class="flex items-center gap-3 p-3"
            >
                <Badge variant="secondary">{{ LABELS[item.kind] }}</Badge>
                <span class="flex-1 truncate font-medium">{{ item.name }}</span>
                <span class="text-sm text-muted-foreground">{{
                    when(item.deleted_at)
                }}</span>
                <Button
                    size="sm"
                    variant="outline"
                    @click="
                        router.post(
                            restore.url({ kind: item.kind, id: item.id }),
                            {},
                            { preserveScroll: true },
                        )
                    "
                >
                    <RotateCcw /> Restore
                </Button>
            </li>
        </ul>
    </div>
</template>
