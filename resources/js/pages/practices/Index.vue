<script setup lang="ts">
import CopyToTeam from '@/components/CopyToTeam.vue';
import { useCanEdit } from '@/composables/useCanEdit';
import { useOnce } from '@/composables/useOnce';
import { Head, Link, router } from '@inertiajs/vue3';
import { Copy, FileText, Pencil, Plus, Printer, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useDialog } from '@/composables/useDialog';
import { create, destroy, duplicate, edit, index } from '@/routes/practices';
import { practice as printPractice } from '@/routes/print';

type PracticeRow = {
    id: number;
    name: string;
    date: string | null;
    duration: number;
    is_template: boolean;
    review_notes: string | null;
    segments_count: number;
    segments_sum_duration: number | null;
};

const canEdit = useCanEdit();
const once = useOnce();
const props = defineProps<{ practices: PracticeRow[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Practices', href: index() }] },
});

const sessions = computed(() => props.practices.filter((p) => !p.is_template));
const templates = computed(() => props.practices.filter((p) => p.is_template));

const { confirm } = useDialog();

async function remove(p: PracticeRow) {
    if (
        await confirm({
            title: `Delete "${p.name}"?`,
            confirmText: 'Delete',
            destructive: true,
        })
    ) {
        router.delete(destroy.url(p.id));
    }
}
</script>

<template>
    <Head title="Practices" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">Practices</h1>
            <Button v-if="canEdit" as-child>
                <Link :href="create()"><Plus /> New practice</Link>
            </Button>
        </div>

        <template
            v-for="group in [
                {
                    title: 'Practice sessions',
                    rows: sessions,
                    empty: 'No practices yet.',
                },
                {
                    title: 'Templates',
                    rows: templates,
                    empty: 'Save a practice as a template to reuse its structure.',
                },
            ]"
            :key="group.title"
        >
            <section class="flex flex-col gap-2">
                <h2 class="text-sm font-medium text-muted-foreground">
                    {{ group.title }}
                </h2>
                <p
                    v-if="!group.rows.length"
                    class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
                >
                    {{ group.empty }}
                </p>
                <div
                    v-for="p in group.rows"
                    :key="p.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl border p-3"
                >
                    <div>
                        <div class="font-medium">{{ p.name }}</div>
                        <div class="flex flex-wrap gap-1 pt-1">
                            <Badge v-if="p.date" variant="outline">{{
                                p.date
                            }}</Badge>
                            <Badge variant="secondary"
                                >{{ p.segments_sum_duration ?? 0 }} /
                                {{ p.duration }} min</Badge
                            >
                            <Badge variant="outline"
                                >{{ p.segments_count }} activities</Badge
                            >
                        </div>
                        <p
                            v-if="p.review_notes"
                            class="max-w-xl pt-1 text-xs text-muted-foreground"
                        >
                            Review: {{ p.review_notes }}
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="edit(p.id)"
                                ><Pencil />
                                {{ canEdit ? 'Edit' : 'View' }}</Link
                            >
                        </Button>
                        <Button
                            variant="outline"
                            size="icon-sm"
                            title="Print"
                            as-child
                        >
                            <a :href="printPractice.url(p.id)" target="_blank"
                                ><Printer
                            /></a>
                        </Button>
                        <Button
                            v-if="canEdit"
                            variant="outline"
                            size="icon-sm"
                            title="Duplicate"
                            @click="once.post(duplicate.url(p.id))"
                            :disabled="once.pending.value"
                        >
                            <Copy />
                        </Button>
                        <Button
                            v-if="p.is_template"
                            variant="outline"
                            size="sm"
                            @click="once.post(duplicate.url(p.id))"
                            :disabled="once.pending.value"
                        >
                            Use template
                        </Button>
                        <Button
                            v-if="!p.is_template"
                            variant="outline"
                            size="icon-sm"
                            title="Save as template"
                            @click="
                                router.post(duplicate.url(p.id), {
                                    template: true,
                                })
                            "
                        >
                            <FileText />
                        </Button>
                        <CopyToTeam
                            v-if="canEdit"
                            kind="practice"
                            :id="p.id"
                            :name="p.name"
                        />
                        <Button
                            v-if="canEdit"
                            variant="outline"
                            size="icon-sm"
                            title="Delete"
                            @click="remove(p)"
                            ><Trash2
                        /></Button>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>
