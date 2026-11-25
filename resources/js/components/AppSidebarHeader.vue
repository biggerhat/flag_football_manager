<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Search } from '@lucide/vue';
import SearchDialog from '@/components/SearchDialog.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';
import { ref } from 'vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const searchDialog = ref<InstanceType<typeof SearchDialog> | null>(null);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <Button
            variant="outline"
            size="sm"
            class="ml-auto gap-2 text-muted-foreground"
            aria-label="Search (Ctrl+K)"
            @click="searchDialog?.open()"
        >
            <Search />
            <span class="hidden sm:inline">Search</span>
            <kbd class="hidden rounded border px-1 text-xs sm:inline"
                >Ctrl K</kbd
            >
        </Button>
        <SearchDialog ref="searchDialog" />
    </header>
</template>
