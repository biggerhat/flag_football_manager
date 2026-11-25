<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { RosterPlayer } from '@/types/practice';

const props = defineProps<{ roster: RosterPlayer[]; canEdit: boolean }>();

const present = defineModel<number[]>({ required: true });

function toggleAll() {
    present.value =
        present.value.length === props.roster.length
            ? []
            : props.roster.map((p) => p.id);
}
</script>

<template>
    <div class="grid gap-2 rounded-xl border p-3">
        <div class="flex items-center justify-between gap-2">
            <Label>
                Attendance ({{ present.length }}/{{ roster.length }})
            </Label>
            <Button
                v-if="canEdit"
                type="button"
                variant="ghost"
                size="sm"
                @click="toggleAll"
            >
                {{
                    present.length === roster.length
                        ? 'Clear all'
                        : 'Everyone here'
                }}
            </Button>
        </div>
        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
            <label
                v-for="player in roster"
                :key="player.id"
                class="flex items-center gap-1.5"
            >
                <input
                    v-model="present"
                    type="checkbox"
                    :value="player.id"
                    :disabled="!canEdit"
                />
                <span v-if="player.number" class="text-muted-foreground"
                    >#{{ player.number }}</span
                >
                {{ player.name }}
            </label>
        </div>
    </div>
</template>
