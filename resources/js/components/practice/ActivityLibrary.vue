<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import { Button } from '@/components/ui/button';
import { lastRunLabel } from '@/lib/dates';
import type { PlayRecord } from '@/lib/diagram';
import type { DragPayload, Drill } from '@/types/practice';

defineProps<{ drills: Drill[]; plays: PlayRecord[] }>();

const emit = defineEmits<{
    addDrill: [drill: Drill];
    addPlay: [play: PlayRecord];
}>();

const tab = ref<'drills' | 'plays'>('drills');

function dragStart(event: DragEvent, payload: DragPayload) {
    event.dataTransfer?.setData('application/json', JSON.stringify(payload));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copyMove';
    }
}
</script>

<template>
    <div class="flex max-h-[80vh] flex-col gap-2 rounded-xl border p-3">
        <div class="grid grid-cols-2 gap-1">
            <Button
                size="sm"
                :variant="tab === 'drills' ? 'default' : 'outline'"
                @click="tab = 'drills'"
                >Drills</Button
            >
            <Button
                size="sm"
                :variant="tab === 'plays' ? 'default' : 'outline'"
                @click="tab = 'plays'"
                >Plays</Button
            >
        </div>
        <div class="flex flex-col gap-1 overflow-y-auto">
            <template v-if="tab === 'drills'">
                <div
                    v-for="drill in drills"
                    :key="drill.id"
                    draggable="true"
                    class="flex cursor-grab items-center justify-between gap-2 rounded-lg border p-2 text-sm"
                    @dragstart="
                        dragStart($event, { type: 'drill', id: drill.id })
                    "
                >
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ drill.name }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ drill.category }} · {{ drill.duration }} min ·
                            {{ lastRunLabel(drill.last_run) }}
                        </div>
                    </div>
                    <Button
                        variant="outline"
                        size="icon-sm"
                        title="Add to practice"
                        @click="emit('addDrill', drill)"
                        ><Plus
                    /></Button>
                </div>
                <p v-if="!drills.length" class="text-sm text-muted-foreground">
                    No drills yet.
                </p>
            </template>
            <template v-else>
                <div
                    v-for="play in plays"
                    :key="play.id"
                    draggable="true"
                    class="flex cursor-grab items-center gap-2 rounded-lg border p-2 text-sm"
                    @dragstart="
                        dragStart($event, { type: 'play', id: play.id })
                    "
                >
                    <div class="w-12 shrink-0 overflow-hidden rounded border">
                        <DiagramCanvas
                            :diagram="play.diagram"
                            :show-labels="false"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ play.name }}</div>
                        <div class="text-xs text-muted-foreground capitalize">
                            {{ play.category }}
                        </div>
                    </div>
                    <Button
                        variant="outline"
                        size="icon-sm"
                        title="Add to practice"
                        @click="emit('addPlay', play)"
                        ><Plus
                    /></Button>
                </div>
                <p v-if="!plays.length" class="text-sm text-muted-foreground">
                    No plays yet.
                </p>
            </template>
        </div>
        <p class="text-xs text-muted-foreground">
            Drop a play onto an existing activity to attach it.
        </p>
    </div>
</template>
