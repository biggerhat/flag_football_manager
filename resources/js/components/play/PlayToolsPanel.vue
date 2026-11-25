<script setup lang="ts">
import {
    ClipboardPaste,
    Copy,
    Eraser,
    FlipHorizontal2,
    MousePointer2,
    PenLine,
    Plus,
    Trash2,
    Type,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { usePlayEditor } from '@/composables/usePlayEditor';
import { ROUTE_TEMPLATES } from '@/lib/diagram';
import type { RouteKind } from '@/lib/diagram';

defineProps<{ editor: ReturnType<typeof usePlayEditor> }>();

const routeKinds: { key: RouteKind; label: string }[] = [
    { key: 'route', label: 'Route' },
    { key: 'run', label: 'Run' },
    { key: 'handoff', label: 'Handoff' },
    { key: 'block', label: 'Block' },
];

const toolClass = (active: boolean) =>
    active ? 'bg-primary text-primary-foreground' : '';
</script>

<template>
    <div class="flex flex-col gap-4 rounded-xl border p-3">
        <div class="grid gap-2">
            <Label>Tool</Label>
            <div class="grid grid-cols-3 gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    :class="toolClass(editor.tool.value === 'select')"
                    title="Select and move"
                    @click="editor.setTool('select')"
                >
                    <MousePointer2 />
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :class="toolClass(editor.tool.value === 'draw')"
                    title="Draw a route for the selected player"
                    @click="editor.setTool('draw')"
                >
                    <PenLine />
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :class="toolClass(editor.tool.value === 'text')"
                    title="Add text"
                    @click="editor.setTool('text')"
                >
                    <Type />
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                <template v-if="editor.tool.value === 'draw'">
                    Select a player, then click the field to add route points.
                    Press Enter or Done to finish.
                </template>
                <template v-else-if="editor.tool.value === 'text'"
                    >Click the field to place text.</template
                >
                <template v-else
                    >Drag players and route points. Click a player to give them
                    a route.</template
                >
            </p>
        </div>

        <div v-if="editor.tool.value === 'draw'" class="grid gap-2">
            <Label>Draw as</Label>
            <div class="grid grid-cols-2 gap-1">
                <Button
                    v-for="k in routeKinds"
                    :key="k.key"
                    variant="outline"
                    size="sm"
                    :class="toolClass(editor.drawKind.value === k.key)"
                    @click="editor.drawKind.value = k.key"
                >
                    {{ k.label }}
                </Button>
            </div>
            <Button
                v-if="editor.drawingId.value"
                size="sm"
                @click="editor.finishDrawing()"
                >Done</Button
            >
        </div>

        <div class="grid gap-2">
            <Label>Preset routes</Label>
            <p
                v-if="!editor.selectedPlayer.value"
                class="text-xs text-muted-foreground"
            >
                Select a player first.
            </p>
            <div class="grid grid-cols-3 gap-1">
                <Button
                    v-for="r in ROUTE_TEMPLATES"
                    :key="r.key"
                    variant="outline"
                    size="sm"
                    class="px-1 text-xs"
                    :disabled="!editor.selectedPlayer.value"
                    @click="editor.applyRouteTemplate(r.key, r.label)"
                >
                    {{ r.label }}
                </Button>
            </div>
        </div>

        <div class="grid gap-2">
            <Label>Players</Label>
            <Button
                variant="outline"
                size="sm"
                @click="editor.addOffensePlayer()"
                ><Plus /> Offense</Button
            >
            <Button variant="outline" size="sm" @click="editor.addDefender()"
                ><Plus /> Defender</Button
            >
            <div class="grid grid-cols-2 gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!editor.selectedRoute.value"
                    title="Copy the selected route (Ctrl+C)"
                    @click="editor.copyRoute()"
                    ><Copy /> Copy route</Button
                >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="
                        !editor.clip.value || !editor.selectedPlayer.value
                    "
                    title="Paste onto the selected player (Ctrl+V)"
                    @click="editor.pasteRoute()"
                    ><ClipboardPaste /> Paste</Button
                >
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input
                    v-model="editor.snap.value"
                    type="checkbox"
                    class="size-4"
                />
                Snap to grid
            </label>
            <Button
                variant="outline"
                size="sm"
                :disabled="!editor.selection.value"
                @click="editor.deleteSelection()"
            >
                <Trash2 /> Delete selected
            </Button>
            <Button
                variant="outline"
                size="sm"
                title="Flip the play left to right"
                @click="editor.mirror()"
                ><FlipHorizontal2 /> Mirror play</Button
            >
            <Button variant="outline" size="sm" @click="editor.clearRoutes()"
                ><Eraser /> Clear routes</Button
            >
        </div>
    </div>
</template>
