<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { CopyPlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { copy } from '@/routes';

const props = defineProps<{
    kind: 'play' | 'drill' | 'formation' | 'practice';
    id: number;
    name: string;
}>();

const page = usePage();
const open = ref(false);
const busy = ref(false);
const target = ref<number | ''>('');

/** Other teams the person can edit (viewers cannot receive copies). */
const targets = computed(
    () =>
        page.props.team?.teams.filter(
            (t) => t.id !== page.props.team?.id && t.role !== 'viewer',
        ) ?? [],
);

function send() {
    if (target.value === '' || busy.value) {
        return;
    }

    busy.value = true;
    router.post(
        copy.url({ kind: props.kind, id: props.id }),
        { team_id: target.value },
        {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
            onFinish: () => (busy.value = false),
        },
    );
}
</script>

<template>
    <template v-if="targets.length">
        <Button
            variant="outline"
            size="icon-sm"
            title="Copy to another team"
            :aria-label="`Copy ${name} to another team`"
            @click="open = true"
        >
            <CopyPlus />
        </Button>

        <Dialog v-model:open="open">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Copy "{{ name }}"</DialogTitle>
                    <DialogDescription>
                        Adds a copy to another team. The original stays where it
                        is.
                        <template v-if="kind === 'practice'">
                            The drills and plays it uses are copied too.
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <select
                    v-model="target"
                    aria-label="Team to copy to"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                >
                    <option value="" disabled>Choose a team…</option>
                    <option v-for="t in targets" :key="t.id" :value="t.id">
                        {{ t.name }}
                    </option>
                </select>
                <DialogFooter>
                    <Button variant="outline" @click="open = false"
                        >Cancel</Button
                    >
                    <Button :disabled="target === '' || busy" @click="send"
                        >Copy</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </template>
</template>
