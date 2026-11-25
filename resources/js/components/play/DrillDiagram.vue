<script setup lang="ts">
import { Eraser, MousePointer2, PenLine, Plus, Trash2 } from '@lucide/vue';
import { watch } from 'vue';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import { Button } from '@/components/ui/button';
import { usePlayEditor } from '@/composables/usePlayEditor';
import { emptyDiagram } from '@/lib/diagram';
import type { Diagram } from '@/lib/diagram';

const props = defineProps<{ modelValue: Diagram; disabled?: boolean }>();
const emit = defineEmits<{
    'update:modelValue': [diagram: Diagram];
    remove: [];
}>();

// A drill diagram is the same field as a play, but with players placed freely.
const editor = usePlayEditor(
    props.modelValue.players.length ? props.modelValue : emptyDiagram(),
);

watch(
    editor.diagram,
    (diagram) => emit('update:modelValue', JSON.parse(JSON.stringify(diagram))),
    { deep: true },
);
</script>

<template>
    <div class="grid gap-2 rounded-xl border p-3">
        <div v-if="!disabled" class="flex flex-wrap items-center gap-1">
            <Button
                type="button"
                size="sm"
                :variant="
                    editor.tool.value === 'select' ? 'default' : 'outline'
                "
                @click="editor.setTool('select')"
            >
                <MousePointer2 /> Move
            </Button>
            <Button
                type="button"
                size="sm"
                :variant="editor.tool.value === 'draw' ? 'default' : 'outline'"
                @click="editor.setTool('draw')"
            >
                <PenLine /> Draw path
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                @click="editor.addOffensePlayer()"
            >
                <Plus /> Player
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                @click="editor.addDefender()"
            >
                <Plus /> Defender
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                @click="editor.deleteSelection()"
            >
                <Trash2 /> Delete selected
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                @click="editor.clearRoutes()"
            >
                <Eraser /> Clear paths
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                class="ml-auto"
                @click="emit('remove')"
            >
                Remove diagram
            </Button>
        </div>
        <div
            class="mx-auto w-full max-w-[420px] overflow-hidden rounded-lg border"
        >
            <DiagramCanvas
                :diagram="editor.diagram.value"
                :selection="editor.selection.value"
                :interactive="!disabled"
                @choose="(target) => (editor.selection.value = target)"
                @down="editor.onDown"
                @move="editor.onMove"
                @up="editor.onUp"
            />
        </div>
        <p v-if="!disabled" class="text-xs text-muted-foreground">
            Drag players to place them. With “Draw path” selected, click a
            player then click along the field to draw where they run;
            double-click to finish.
        </p>
    </div>
</template>
