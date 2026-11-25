<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Details = {
    name: string;
    date: string;
    start_time: string;
    duration: number;
    location: string;
    objectives: string;
    notes: string;
    review_notes: string;
};

defineProps<{ errors: Partial<Record<keyof Details, string>> }>();

const name = defineModel<string>('name', { required: true });
const date = defineModel<string>('date', { required: true });
const startTime = defineModel<string>('startTime', { required: true });
const duration = defineModel<number>('duration', { required: true });
const location = defineModel<string>('location', { required: true });
const objectives = defineModel<string>('objectives', { required: true });
const notes = defineModel<string>('notes', { required: true });
const reviewNotes = defineModel<string>('reviewNotes', { required: true });

const textareaClass =
    'border-input bg-background w-full rounded-md border px-3 py-2 text-sm shadow-xs';
</script>

<template>
    <div class="flex flex-col gap-3 rounded-xl border p-3">
        <div class="grid gap-2">
            <Label for="name">Title</Label>
            <Input id="name" v-model="name" />
            <InputError :message="errors.name" />
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div class="grid gap-2">
                <Label for="date">Date</Label>
                <Input id="date" v-model="date" type="date" />
            </div>
            <div class="grid gap-2">
                <Label for="start">Start</Label>
                <Input id="start" v-model="startTime" type="time" />
            </div>
        </div>
        <div class="grid gap-2">
            <Label for="duration">Planned minutes</Label>
            <Input id="duration" v-model="duration" type="number" min="5" />
            <InputError :message="errors.duration" />
        </div>
        <div class="grid gap-2">
            <Label for="location">Location</Label>
            <Input id="location" v-model="location" />
        </div>
        <div class="grid gap-2">
            <Label for="objectives">Objectives</Label>
            <textarea
                id="objectives"
                v-model="objectives"
                rows="3"
                :class="textareaClass"
            />
        </div>
        <div class="grid gap-2">
            <Label for="notes">Coach notes</Label>
            <textarea
                id="notes"
                v-model="notes"
                rows="3"
                :class="textareaClass"
            />
        </div>
        <div class="grid gap-2">
            <Label for="review">After practice: what worked?</Label>
            <textarea
                id="review"
                v-model="reviewNotes"
                rows="3"
                placeholder="What to repeat, fix or skip next time"
                :class="textareaClass"
            />
        </div>
    </div>
</template>
