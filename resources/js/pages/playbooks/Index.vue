<script setup lang="ts">
import { useCanEdit } from '@/composables/useCanEdit';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDialog } from '@/composables/useDialog';
import type { Diagram } from '@/lib/diagram';
import { destroy, index, show, store, update } from '@/routes/playbooks';

type PlaybookRow = {
    id: number;
    name: string;
    description: string | null;
    plays_count: number;
    previews: { id: number; diagram: Diagram }[];
};

defineProps<{ playbooks: PlaybookRow[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Playbooks', href: index() }] },
});

const canEdit = useCanEdit();
const form = useForm({ name: '' });

function create() {
    form.post(store.url(), { onSuccess: () => form.reset() });
}

const { confirm, prompt } = useDialog();

async function rename(playbook: PlaybookRow) {
    const name = (
        await prompt({
            title: 'Rename playbook',
            defaultValue: playbook.name,
        })
    )?.trim();

    if (name && name !== playbook.name) {
        router.put(update.url(playbook.id), {
            name,
            description: playbook.description,
        });
    }
}

async function remove(playbook: PlaybookRow) {
    if (
        await confirm({
            title: `Delete "${playbook.name}"?`,
            description: 'This playbook is empty.',
            confirmText: 'Delete',
            destructive: true,
        })
    ) {
        router.delete(destroy.url(playbook.id));
    }
}
</script>

<template>
    <Head title="Playbooks" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">Playbooks</h1>
            <form
                v-if="canEdit"
                class="flex items-start gap-2"
                @submit.prevent="create"
            >
                <div class="grid gap-1">
                    <Input
                        v-model="form.name"
                        placeholder="New playbook name"
                        maxlength="80"
                        aria-label="New playbook name"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <Button
                    v-if="canEdit"
                    type="submit"
                    :disabled="form.processing || !form.name.trim()"
                    ><Plus /> Create</Button
                >
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="playbook in playbooks"
                :key="playbook.id"
                class="flex flex-col overflow-hidden rounded-xl border"
            >
                <Link
                    :href="show(playbook.id)"
                    class="grid grid-cols-3 gap-px bg-muted"
                >
                    <div
                        v-for="i in 3"
                        :key="i"
                        class="aspect-[5/6] overflow-hidden bg-background"
                    >
                        <DiagramCanvas
                            v-if="playbook.previews[i - 1]"
                            :diagram="playbook.previews[i - 1].diagram"
                            :show-labels="false"
                        />
                    </div>
                </Link>
                <div class="flex flex-1 flex-col gap-2 p-3">
                    <Link
                        :href="show(playbook.id)"
                        class="font-medium hover:underline"
                        >{{ playbook.name }}</Link
                    >
                    <p class="text-sm text-muted-foreground">
                        {{ playbook.plays_count }}
                        {{ playbook.plays_count === 1 ? 'play' : 'plays' }}
                    </p>
                    <div class="mt-auto flex gap-1 pt-2">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="show(playbook.id)">Open</Link>
                        </Button>
                        <Button
                            v-if="canEdit"
                            variant="outline"
                            size="icon-sm"
                            title="Rename"
                            @click="rename(playbook)"
                            ><Pencil
                        /></Button>
                        <Button
                            v-if="canEdit"
                            variant="outline"
                            size="icon-sm"
                            :title="
                                playbook.plays_count
                                    ? 'Move or delete its plays first'
                                    : 'Delete'
                            "
                            :disabled="playbook.plays_count > 0"
                            @click="remove(playbook)"
                            ><Trash2
                        /></Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
