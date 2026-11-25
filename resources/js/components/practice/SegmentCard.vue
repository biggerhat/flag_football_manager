<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    GripVertical,
    Minus,
    Plus,
    Trash2,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { PlayRecord } from '@/lib/diagram';
import type { Drill, Segment } from '@/types/practice';

/** Timeline cards grow with their duration so the schedule reads at a glance. */
const PX_PER_MINUTE = 9;

const props = defineProps<{
    segment: Segment;
    /** Clock label for when the activity starts, such as 0:15. */
    start: string;
    first: boolean;
    last: boolean;
    drill: Drill | null;
    play: PlayRecord | null;
}>();

const emit = defineEmits<{
    moveUp: [];
    moveDown: [];
    remove: [];
    detachPlay: [];
    reorderStart: [event: PointerEvent];
    reorderMove: [event: PointerEvent];
    reorderEnd: [event: PointerEvent];
}>();

const duration = defineModel<number>('duration', { required: true });
const title = defineModel<string>('title', { required: true });
const notes = defineModel<string | null>('notes', { required: true });

const minHeight = computed(
    () => `${Math.max(96, (Number(duration.value) || 0) * PX_PER_MINUTE)}px`,
);

function step(delta: number) {
    duration.value = Math.max(1, (Number(duration.value) || 0) + delta);
}

let resizing: { startY: number; startMinutes: number } | null = null;

function startResize(event: PointerEvent) {
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    resizing = {
        startY: event.clientY,
        startMinutes: Number(duration.value) || 1,
    };
}

function resize(event: PointerEvent) {
    if (!resizing) {
        return;
    }

    const minutes = Math.round(
        resizing.startMinutes +
            (event.clientY - resizing.startY) / PX_PER_MINUTE,
    );
    duration.value = Math.min(180, Math.max(1, minutes));
}

function endResize(event: PointerEvent) {
    resizing = null;
    (event.currentTarget as HTMLElement).releasePointerCapture?.(
        event.pointerId,
    );
}

const hasLinks = computed(
    () => props.segment.drill_id || props.segment.play_id,
);
</script>

<template>
    <div
        class="relative flex gap-2 rounded-xl border p-3 pb-5 transition-colors"
        :style="{ minHeight }"
    >
        <div
            class="flex w-14 shrink-0 flex-col items-center gap-1 text-xs text-muted-foreground"
        >
            <GripVertical
                class="size-6 cursor-grab touch-none"
                aria-label="Drag to reorder"
                @pointerdown.prevent="emit('reorderStart', $event)"
                @pointermove="emit('reorderMove', $event)"
                @pointerup="emit('reorderEnd', $event)"
                @pointercancel="emit('reorderEnd', $event)"
            />
            <span class="font-mono">{{ start }}</span>
        </div>
        <div class="flex min-w-0 flex-1 flex-col gap-2">
            <div class="flex gap-2">
                <Input
                    v-model="title"
                    class="font-medium"
                    aria-label="Activity title"
                />
                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon-sm"
                        title="5 minutes less"
                        @click="step(-5)"
                        ><Minus
                    /></Button>
                    <Input
                        v-model="duration"
                        type="number"
                        min="1"
                        class="w-16 text-center"
                        aria-label="Minutes"
                    />
                    <Button
                        variant="outline"
                        size="icon-sm"
                        title="5 minutes more"
                        @click="step(5)"
                        ><Plus
                    /></Button>
                </div>
            </div>
            <Input
                :model-value="notes ?? ''"
                placeholder="Notes"
                @update:model-value="notes = String($event) || null"
            />
            <div v-if="hasLinks" class="flex flex-wrap items-center gap-2">
                <Badge v-if="segment.drill_id" variant="outline"
                    >Drill: {{ drill?.name ?? 'removed' }}</Badge
                >
                <div v-if="play" class="flex items-center gap-2">
                    <div class="w-16 overflow-hidden rounded border">
                        <DiagramCanvas
                            :diagram="play.diagram"
                            :show-labels="false"
                        />
                    </div>
                    <span class="text-sm">{{ play.name }}</span>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        title="Detach play"
                        @click="emit('detachPlay')"
                        ><X
                    /></Button>
                </div>
            </div>
        </div>
        <div class="flex shrink-0 flex-col gap-1">
            <Button
                variant="ghost"
                size="icon-sm"
                title="Move up"
                :disabled="first"
                @click="emit('moveUp')"
                ><ArrowUp
            /></Button>
            <Button
                variant="ghost"
                size="icon-sm"
                title="Move down"
                :disabled="last"
                @click="emit('moveDown')"
                ><ArrowDown
            /></Button>
            <Button
                variant="ghost"
                size="icon-sm"
                title="Remove"
                @click="emit('remove')"
                ><Trash2
            /></Button>
        </div>
        <!-- drag this edge to lengthen or shorten the activity -->
        <div
            draggable="true"
            class="absolute inset-x-0 bottom-0 flex h-4 cursor-ns-resize touch-none items-center justify-center"
            title="Drag to change the duration"
            @dragstart.prevent.stop
            @pointerdown="startResize"
            @pointermove="resize"
            @pointerup="endResize"
            @pointercancel="endResize"
        >
            <span class="h-1 w-10 rounded-full bg-border"></span>
        </div>
    </div>
</template>
