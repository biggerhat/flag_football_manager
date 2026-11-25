<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    DASH_PATTERNS,
    FIELD_H,
    FIELD_W,
    PREVIEW_SPEED,
    endMarker,
    positionAlong,
    pathData,
    routeLabelPosition,
    toSvg,
    clamp01,
} from '@/lib/diagram';
import type { Diagram, Point, Selection } from '@/lib/diagram';

export type CanvasTarget =
    | { kind: 'player'; id: string }
    | { kind: 'route'; id: string }
    | { kind: 'handle'; id: string; index: number }
    | { kind: 'annotation'; id: string }
    | { kind: 'field' };

const props = withDefaults(
    defineProps<{
        diagram: Diagram;
        theme?: 'green' | 'paper' | 'bw' | 'dark';
        interactive?: boolean;
        selection?: Selection;
        showLabels?: boolean;
        /** Fill the parent box (letterboxed) instead of sizing by width. */
        fit?: boolean;
        /** Write each route's name beyond its arrowhead. */
        routeLabels?: boolean;
        /** Seconds into the animated preview; players walk their routes. Null shows the play as drawn. */
        playback?: number | null;
        /** Another alignment (a defense, say) drawn faintly behind the players to check matchups. */
        overlay?: Diagram | null;
    }>(),
    {
        theme: 'green',
        interactive: false,
        selection: null,
        showLabels: true,
        fit: false,
        routeLabels: false,
        playback: null,
        overlay: null,
    },
);

const emit = defineEmits<{
    /** A keyboard user picked something (Tab focus, Enter or Space). */
    choose: [target: { kind: 'player' | 'route' | 'annotation'; id: string }];
    down: [target: CanvasTarget, point: Point, event: PointerEvent];
    move: [point: Point];
    up: [point: Point];
}>();

const svg = ref<SVGSVGElement | null>(null);

/** What a screen reader hears for a read-only diagram. */
const description = computed(() => {
    const names = props.diagram.players.map((p) => p.label).join(', ');
    const routes = props.diagram.routes.length;

    return `Play diagram with ${props.diagram.players.length} players (${names}) and ${routes} ${routes === 1 ? 'route' : 'routes'}.`;
});

const routeLabel = (route: Diagram['routes'][number]): string => {
    const owner = props.diagram.players.find((p) => p.id === route.player_id);

    return `${route.name || route.kind} for ${owner?.label ?? 'a player'}`;
};

const palette = computed(() => {
    if (props.theme === 'green') {
        return {
            bg: '#2f7d3a',
            line: '#ffffffaa',
            los: '#ffffff',
            text: '#ffffff',
            routeOverride: null as string | null,
        };
    }

    if (props.theme === 'dark') {
        return {
            bg: '#0a0a0a',
            line: '#3f3f46',
            los: '#a1a1aa',
            text: '#ffffff',
            routeOverride: null as string | null,
        };
    }

    if (props.theme === 'bw') {
        return {
            bg: '#ffffff',
            line: '#d4d4d8',
            los: '#52525b',
            text: '#111111',
            routeOverride: '#111111',
        };
    }

    return {
        bg: '#ffffff',
        line: '#d4d4d8',
        los: '#52525b',
        text: '#111111',
        routeOverride: null,
    };
});

/** Where a player stands: as drawn, or partway along their route during the preview. */
function playerPos(player: Diagram['players'][number]): Point {
    if (props.playback === null) {
        return player;
    }

    const route = props.diagram.routes.find(
        (r) => r.player_id === player.id && r.kind !== 'handoff',
    );

    return route
        ? positionAlong(route.points, props.playback * PREVIEW_SPEED)
        : player;
}

const yardLines = Array.from({ length: 9 }, (_, i) => (i + 1) * 0.1);

const routeColor = (color: string): string => {
    if (palette.value.routeOverride) {
        return palette.value.routeOverride;
    }

    // Light route colors disappear on paper, so darken them there.
    if (props.theme === 'paper') {
        const map: Record<string, string> = {
            '#facc15': '#b45309',
            '#ffffff': '#111111',
            '#f97316': '#c2410c',
            '#a3e635': '#4d7c0f',
        };

        return map[color.toLowerCase()] ?? color;
    }

    return color;
};

const playerFill = (color: string): string =>
    props.theme === 'bw' ? '#ffffff' : color;
const playerText = computed(() =>
    props.theme === 'bw' ? '#111111' : '#ffffff',
);

const selectedRoute = computed(() =>
    props.selection?.kind === 'route'
        ? props.diagram.routes.find((r) => r.id === props.selection!.id)
        : undefined,
);

function toNormalized(event: PointerEvent | MouseEvent): Point {
    const el = svg.value!;
    const ctm = el.getScreenCTM();

    if (!ctm) {
        return { x: 0, y: 0 };
    }

    const pt = el.createSVGPoint();
    pt.x = event.clientX;
    pt.y = event.clientY;
    const local = pt.matrixTransform(ctm.inverse());

    return { x: clamp01(local.x / FIELD_W), y: clamp01(local.y / FIELD_H) };
}

function targetOf(event: PointerEvent): CanvasTarget {
    const el = (event.target as Element).closest<SVGElement>('[data-kind]');

    if (!el) {
        return { kind: 'field' };
    }

    const kind = el.dataset.kind;
    const id = el.dataset.id ?? '';

    if (kind === 'handle') {
        return { kind: 'handle', id, index: Number(el.dataset.index) };
    }

    return { kind, id } as CanvasTarget;
}

function onDown(event: PointerEvent) {
    if (!props.interactive) {
        return;
    }

    svg.value?.setPointerCapture(event.pointerId);
    emit('down', targetOf(event), toNormalized(event), event);
}

function onMove(event: PointerEvent) {
    if (props.interactive && svg.value?.hasPointerCapture(event.pointerId)) {
        emit('move', toNormalized(event));
    }
}

function onUp(event: PointerEvent) {
    if (props.interactive && svg.value?.hasPointerCapture(event.pointerId)) {
        svg.value.releasePointerCapture(event.pointerId);
        emit('up', toNormalized(event));
    }
}

const isSelected = (kind: string, id: string): boolean =>
    props.selection?.kind === kind && props.selection.id === id;
</script>

<template>
    <svg
        ref="svg"
        :role="interactive ? 'group' : 'img'"
        :aria-label="
            interactive
                ? 'Play diagram editor. Press Tab to move between players, routes and notes, arrow keys to move the selection, Delete to remove it.'
                : description
        "
        :viewBox="`0 0 ${FIELD_W} ${FIELD_H}`"
        class="block w-full touch-none select-none"
        :class="[
            fit ? 'h-full' : 'h-auto',
            interactive ? 'cursor-crosshair' : '',
        ]"
        @pointerdown="onDown"
        @pointermove="onMove"
        @pointerup="onUp"
        @pointercancel="onUp"
    >
        <rect :width="FIELD_W" :height="FIELD_H" :fill="palette.bg" />

        <line
            v-for="y in yardLines"
            :key="y"
            :x1="0"
            :x2="FIELD_W"
            :y1="y * FIELD_H"
            :y2="y * FIELD_H"
            :stroke="palette.line"
            stroke-width="0.35"
            stroke-dasharray="2 2"
        />
        <template v-for="y in yardLines" :key="`h${y}`">
            <line
                :x1="38"
                :x2="40"
                :y1="y * FIELD_H"
                :y2="y * FIELD_H"
                :stroke="palette.line"
                stroke-width="0.6"
            />
            <line
                :x1="60"
                :x2="62"
                :y1="y * FIELD_H"
                :y2="y * FIELD_H"
                :stroke="palette.line"
                stroke-width="0.6"
            />
        </template>
        <rect
            x="0.4"
            y="0.4"
            :width="FIELD_W - 0.8"
            :height="FIELD_H - 0.8"
            fill="none"
            :stroke="theme === 'green' ? palette.line : palette.los"
            stroke-width="0.8"
        />

        <line
            :x1="0"
            :x2="FIELD_W"
            :y1="diagram.los * FIELD_H"
            :y2="diagram.los * FIELD_H"
            :stroke="palette.los"
            stroke-width="1"
        />

        <!-- routes -->
        <g v-for="route in diagram.routes" :key="route.id">
            <path
                v-if="interactive"
                :d="pathData(route.points, route.style.end)"
                fill="none"
                stroke="transparent"
                stroke-width="5"
                data-kind="route"
                :data-id="route.id"
                class="cursor-pointer outline-none"
                tabindex="0"
                role="button"
                :aria-label="routeLabel(route)"
                :aria-pressed="isSelected('route', route.id)"
                @focus="emit('choose', { kind: 'route', id: route.id })"
            />
            <path
                :d="pathData(route.points, route.style.end)"
                fill="none"
                :stroke="routeColor(route.style.color)"
                :stroke-width="isSelected('route', route.id) ? 1.6 : 1.1"
                :stroke-dasharray="DASH_PATTERNS[route.style.dash]"
                stroke-linejoin="round"
                stroke-linecap="round"
                pointer-events="none"
            />
            <polygon
                v-if="route.style.end === 'arrow'"
                :points="endMarker(route.points, 'arrow') ?? ''"
                :fill="routeColor(route.style.color)"
                pointer-events="none"
            />
            <polyline
                v-else-if="route.style.end === 'block'"
                :points="endMarker(route.points, 'block') ?? ''"
                fill="none"
                :stroke="routeColor(route.style.color)"
                stroke-width="1.6"
                stroke-linecap="round"
                pointer-events="none"
            />
            <text
                v-if="
                    routeLabels &&
                    route.name &&
                    routeLabelPosition(route.points)
                "
                :x="routeLabelPosition(route.points)!.x"
                :y="routeLabelPosition(route.points)!.y"
                text-anchor="middle"
                font-size="2.9"
                font-weight="700"
                :fill="theme === 'green' ? '#ffffff' : '#111111'"
                :stroke="palette.bg"
                stroke-width="0.7"
                paint-order="stroke"
                pointer-events="none"
            >
                {{ route.name }}
            </text>
        </g>

        <!-- annotations -->
        <text
            v-for="note in diagram.annotations"
            :key="note.id"
            :x="toSvg(note).x"
            :y="toSvg(note).y"
            text-anchor="middle"
            font-size="3.6"
            font-weight="600"
            :fill="palette.text"
            :stroke="isSelected('annotation', note.id) ? '#38bdf8' : 'none'"
            stroke-width="0.4"
            data-kind="annotation"
            :data-id="note.id"
            :class="interactive ? 'cursor-move outline-none' : ''"
            :tabindex="interactive ? 0 : undefined"
            :aria-label="interactive ? `Note: ${note.text}` : undefined"
            @focus="
                interactive &&
                emit('choose', { kind: 'annotation', id: note.id })
            "
        >
            {{ note.text }}
        </text>

        <!-- overlay: a second alignment for matchups, never interactive -->
        <g v-if="overlay" opacity="0.5" pointer-events="none">
            <polyline
                v-for="route in overlay.routes"
                :key="`o${route.id}`"
                :points="
                    route.points
                        .map((p) => `${toSvg(p).x},${toSvg(p).y}`)
                        .join(' ')
                "
                fill="none"
                stroke="#fca5a5"
                stroke-width="0.8"
                stroke-dasharray="2 1.6"
            />
            <g v-for="player in overlay.players" :key="`o${player.id}`">
                <circle
                    :cx="toSvg(player).x"
                    :cy="toSvg(player).y"
                    r="3.6"
                    fill="#dc2626"
                    stroke="#ffffff"
                    stroke-width="0.5"
                />
                <text
                    v-if="showLabels"
                    :x="toSvg(player).x"
                    :y="toSvg(player).y + 1.2"
                    text-anchor="middle"
                    :font-size="player.label.length > 2 ? 2.8 : 3.4"
                    font-weight="700"
                    fill="#ffffff"
                >
                    {{ player.label }}
                </text>
            </g>
        </g>

        <!-- players -->
        <g
            v-for="player in diagram.players"
            :key="player.id"
            data-kind="player"
            :data-id="player.id"
            :class="interactive ? 'cursor-grab outline-none' : ''"
            :tabindex="interactive ? 0 : undefined"
            :role="interactive ? 'button' : undefined"
            :aria-label="
                interactive ? `${player.label}, ${player.type}` : undefined
            "
            :aria-pressed="
                interactive ? isSelected('player', player.id) : undefined
            "
            @focus="
                interactive && emit('choose', { kind: 'player', id: player.id })
            "
        >
            <rect
                v-if="player.type === 'center'"
                :x="toSvg(playerPos(player)).x - 4"
                :y="toSvg(playerPos(player)).y - 4"
                width="8"
                height="8"
                rx="1.2"
                :fill="playerFill(player.color)"
                :stroke="
                    isSelected('player', player.id)
                        ? '#38bdf8'
                        : props.theme === 'bw'
                          ? '#111111'
                          : '#ffffff'
                "
                :stroke-width="isSelected('player', player.id) ? 1.2 : 0.7"
            />
            <circle
                v-else
                :cx="toSvg(playerPos(player)).x"
                :cy="toSvg(playerPos(player)).y"
                r="4.2"
                :fill="playerFill(player.color)"
                :stroke="
                    isSelected('player', player.id)
                        ? '#38bdf8'
                        : props.theme === 'bw'
                          ? '#111111'
                          : '#ffffff'
                "
                :stroke-width="isSelected('player', player.id) ? 1.2 : 0.7"
            />
            <text
                v-if="showLabels"
                :x="toSvg(playerPos(player)).x"
                :y="toSvg(playerPos(player)).y + 1.4"
                text-anchor="middle"
                :font-size="player.label.length > 2 ? 3.2 : 4"
                font-weight="700"
                :fill="playerText"
                pointer-events="none"
            >
                {{ player.label }}
            </text>
        </g>

        <!-- route point handles -->
        <template v-if="interactive && selectedRoute">
            <circle
                v-for="(pt, index) in selectedRoute.points.slice(1)"
                :key="index + 1"
                :cx="toSvg(pt).x"
                :cy="toSvg(pt).y"
                r="1.8"
                fill="#ffffff"
                stroke="#0ea5e9"
                stroke-width="0.7"
                data-kind="handle"
                :data-id="selectedRoute.id"
                :data-index="index + 1"
                class="cursor-move"
            />
        </template>
    </svg>
</template>
