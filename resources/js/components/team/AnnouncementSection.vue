<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useCanEdit } from '@/composables/useCanEdit';
import { announce } from '@/routes/teams';

const props = defineProps<{
    teamId: number;
    announcementText: string | null;
}>();

const canEdit = useCanEdit();
const announcement = useForm({ announcement: props.announcementText ?? '' });

function postAnnouncement() {
    announcement.put(announce.url(props.teamId), { preserveScroll: true });
}
</script>

<template>
    <section class="rounded-xl border">
        <h2 class="border-b px-4 py-3 font-medium">Announcement</h2>
        <form
            v-if="canEdit"
            class="flex flex-col gap-2 p-4"
            @submit.prevent="postAnnouncement"
        >
            <p class="text-sm text-muted-foreground">
                Shown on everyone's dashboard, such as "Practice moved to the
                north field". Leave empty to clear it.
            </p>
            <textarea
                v-model="announcement.announcement"
                rows="3"
                maxlength="500"
                aria-label="Announcement"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
            />
            <InputError :message="announcement.errors.announcement" />
            <Button
                type="submit"
                size="sm"
                class="self-start"
                :disabled="announcement.processing"
                >Post</Button
            >
        </form>
        <p v-else class="p-4 text-sm whitespace-pre-line">
            {{ announcementText }}
        </p>
    </section>
</template>
