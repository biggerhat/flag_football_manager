<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { CalendarRange } from '@lucide/vue';
import { computed } from 'vue';
import { useSidebar } from '@/components/ui/sidebar';
import { switchMethod } from '@/routes/seasons';
import { show } from '@/routes/team';

const page = usePage();
const { state } = useSidebar();
const seasons = computed(() => page.props.seasons);

function choose(event: Event) {
    const value = (event.target as HTMLSelectElement).value;

    if (value === 'manage') {
        router.visit(show.url());

        return;
    }

    router.post(
        switchMethod.url(),
        { season_id: value === 'all' ? null : Number(value) },
        { preserveScroll: true },
    );
}
</script>

<template>
    <div
        v-if="seasons && state !== 'collapsed'"
        class="flex items-center gap-2 px-2 pb-1 text-sm"
    >
        <CalendarRange class="size-4 shrink-0 text-muted-foreground" />
        <select
            :value="seasons.current ?? 'all'"
            aria-label="Season"
            class="h-8 min-w-0 flex-1 rounded-md border border-sidebar-border bg-sidebar px-2 text-sm"
            @change="choose"
        >
            <option value="all">All time</option>
            <option v-for="s in seasons.list" :key="s.id" :value="s.id">
                {{ s.name }}
            </option>
            <option value="manage">Manage seasons…</option>
        </select>
    </div>
</template>
