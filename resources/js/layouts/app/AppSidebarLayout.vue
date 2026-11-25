<script setup lang="ts">
import AppDialogHost from '@/components/AppDialogHost.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { useCanEdit } from '@/composables/useCanEdit';
import { useOnline } from '@/composables/useOnline';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const canEdit = useCanEdit();
const online = useOnline();
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <p
                v-if="!online"
                class="border-b bg-warning/15 px-4 py-2 text-sm text-warning"
                role="status"
            >
                You’re offline. Pages you’ve opened or saved are still readable,
                but changes can’t be saved until you reconnect.
            </p>
            <p
                v-if="!canEdit"
                class="border-b bg-muted px-4 py-2 text-sm text-muted-foreground"
                role="status"
            >
                You have view-only access to this team. You can look at and
                print everything, but not change it.
            </p>
            <slot />
        </AppContent>
        <Toaster />
        <AppDialogHost />
    </AppShell>
</template>
