<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy as removeSeason, store as addSeason } from '@/routes/seasons';

const newSeason = useForm({
    name: '',
    starts_on: new Date().toISOString().slice(0, 10),
    ends_on: '',
});
const seasonList = computed(() => usePage().props.seasons?.list ?? []);

function createSeason() {
    newSeason
        .transform((data) => ({ ...data, ends_on: data.ends_on || null }))
        .post(addSeason.url(), {
            preserveScroll: true,
            onSuccess: () => newSeason.reset('name', 'ends_on'),
        });
}
</script>

<template>
    <section class="rounded-xl border">
        <h2 class="border-b px-4 py-3 font-medium">Seasons</h2>
        <div class="flex flex-col gap-3 p-4 text-sm">
            <p class="text-muted-foreground">
                A season is a range of dates. Pick one in the sidebar to limit
                the record, stats and attendance to it. Games and practices
                themselves are never changed.
            </p>
            <ul v-if="seasonList.length" class="divide-y rounded-lg border">
                <li
                    v-for="s in seasonList"
                    :key="s.id"
                    class="flex items-center gap-3 p-2"
                >
                    <span class="flex-1 font-medium">{{ s.name }}</span>
                    <span class="text-muted-foreground"
                        >{{ s.starts_on }} – {{ s.ends_on ?? 'ongoing' }}</span
                    >
                    <Button
                        size="icon-sm"
                        variant="ghost"
                        :aria-label="`Remove ${s.name}`"
                        @click="
                            router.delete(removeSeason.url(s.id), {
                                preserveScroll: true,
                            })
                        "
                    >
                        <Trash2 />
                    </Button>
                </li>
            </ul>
            <form
                class="flex flex-wrap items-end gap-2"
                @submit.prevent="createSeason"
            >
                <div class="grid gap-1">
                    <Label for="season-name">Name</Label>
                    <Input
                        id="season-name"
                        v-model="newSeason.name"
                        placeholder="Fall 2026"
                        required
                        maxlength="80"
                    />
                    <InputError :message="newSeason.errors.name" />
                </div>
                <div class="grid gap-1">
                    <Label for="season-start">Starts</Label>
                    <Input
                        id="season-start"
                        v-model="newSeason.starts_on"
                        type="date"
                        required
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="season-end">Ends (optional)</Label>
                    <Input
                        id="season-end"
                        v-model="newSeason.ends_on"
                        type="date"
                    />
                    <InputError :message="newSeason.errors.ends_on" />
                </div>
                <Button type="submit" :disabled="newSeason.processing"
                    >Add season</Button
                >
            </form>
        </div>
    </section>
</template>
