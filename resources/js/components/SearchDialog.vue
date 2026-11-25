<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useCanEdit } from '@/composables/useCanEdit';
import { create as createDrill } from '@/routes/drills';
import { create as createFormation } from '@/routes/formations';
import { create as createGamePlan } from '@/routes/gameplans';
import { create as createPlay } from '@/routes/plays';
import { create as createPractice } from '@/routes/practices';
import { calendar, dashboard } from '@/routes';
import { index as playersIndex } from '@/routes/players';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { search } from '@/routes';

type Item = { title: string; subtitle?: string | null; href: string };
type Group = { label: string; items: Item[] };

const open = ref(false);
const query = ref('');
const remote = ref<Group[]>([]);
const canEdit = useCanEdit();

const ACTIONS: (Item & { edit?: boolean })[] = [
    { title: 'New play', href: createPlay().url, edit: true },
    { title: 'New practice', href: createPractice().url, edit: true },
    { title: 'New drill', href: createDrill().url, edit: true },
    { title: 'New formation', href: createFormation().url, edit: true },
    { title: 'New game plan', href: createGamePlan().url, edit: true },
    { title: 'Go to dashboard', href: dashboard().url },
    { title: 'Go to calendar', href: calendar().url },
    { title: 'Go to roster', href: playersIndex().url },
];

// Actions matching what is typed (all of them when the box is empty).
const groups = computed<Group[]>(() => {
    const q = query.value.trim().toLowerCase();
    const actions = ACTIONS.filter(
        (a) => (!a.edit || canEdit.value) && a.title.toLowerCase().includes(q),
    );

    return [
        ...(actions.length ? [{ label: 'Actions', items: actions }] : []),
        ...remote.value,
    ];
});
const active = ref(0);
let timer: ReturnType<typeof setTimeout> | undefined;
let sequence = 0;

const flat = () => groups.value.flatMap((g) => g.items);

function onKey(event: KeyboardEvent) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        open.value = !open.value;
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    clearTimeout(timer);
});

watch(open, (isOpen) => {
    if (!isOpen) {
        query.value = '';
        remote.value = [];
    }
});

watch(query, (value) => {
    clearTimeout(timer);
    active.value = 0;

    if (value.trim().length < 2) {
        remote.value = [];

        return;
    }

    timer = setTimeout(async () => {
        const mine = ++sequence;

        try {
            const response = await fetch(
                search.url({ query: { q: value.trim() } }),
                { headers: { Accept: 'application/json' } },
            );
            const data = await response.json();

            // Ignore answers to searches that have since been replaced.
            if (mine === sequence) {
                remote.value = data.groups;
            }
        } catch {
            remote.value = [];
        }
    }, 200);
});

function go(item: Item | undefined) {
    if (!item) {
        return;
    }

    open.value = false;
    router.visit(item.href);
}

function onInputKey(event: KeyboardEvent) {
    const count = flat().length;

    if (event.key === 'ArrowDown' && count) {
        event.preventDefault();
        active.value = (active.value + 1) % count;
    } else if (event.key === 'ArrowUp' && count) {
        event.preventDefault();
        active.value = (active.value - 1 + count) % count;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        go(flat()[active.value]);
    }
}

defineExpose({ open: () => (open.value = true) });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="top-[20%] translate-y-0 gap-3 p-4 sm:max-w-lg">
            <DialogHeader class="sr-only">
                <DialogTitle>Search</DialogTitle>
                <DialogDescription>
                    Search plays, playbooks, formations, drills, practices, game
                    plans and players.
                </DialogDescription>
            </DialogHeader>
            <div class="relative">
                <Search
                    class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="query"
                    v-focus
                    class="pl-8"
                    placeholder="Search or run a command…"
                    aria-label="Search"
                    @keydown="onInputKey"
                />
            </div>
            <div class="max-h-80 overflow-y-auto" role="listbox">
                <p
                    v-if="query.trim().length >= 2 && !groups.length"
                    class="px-2 py-3 text-sm text-muted-foreground"
                >
                    Nothing found.
                </p>
                <p
                    v-else-if="query.trim().length < 2"
                    class="px-2 py-1 text-xs text-muted-foreground"
                >
                    Type two or more letters to search your content.
                </p>
                <div v-for="group in groups" :key="group.label" class="py-1">
                    <p
                        class="px-2 py-1 text-xs font-medium text-muted-foreground"
                    >
                        {{ group.label }}
                    </p>
                    <button
                        v-for="item in group.items"
                        :key="item.href + item.title"
                        type="button"
                        role="option"
                        :aria-selected="flat()[active] === item"
                        class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent"
                        :class="flat()[active] === item ? 'bg-accent' : ''"
                        @click="go(item)"
                    >
                        <span class="truncate">{{ item.title }}</span>
                        <span
                            v-if="item.subtitle"
                            class="ml-2 shrink-0 text-xs text-muted-foreground capitalize"
                            >{{ item.subtitle }}</span
                        >
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
