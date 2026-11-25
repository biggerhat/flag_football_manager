import { computed, ref } from 'vue';
import type { CanvasTarget } from '@/components/play/DiagramCanvas.vue';
import {
    DEFENSE_COLOR,
    KIND_STYLES,
    OFFENSE_COLOR,
    buildRoute,
    clamp01,
    clone,
    diagramFromFormation,
    uid,
    uniqueLabel,
} from '@/lib/diagram';
import type {
    Diagram,
    DiagramPlayer,
    DiagramRoute,
    FormationRecord,
    PlayerType,
    Point,
    RouteDash,
    RouteKind,
    Selection,
} from '@/lib/diagram';

export type Tool = 'select' | 'draw' | 'text';

type Drag =
    | { type: 'player'; id: string; last: Point }
    | { type: 'handle'; routeId: string; index: number }
    | { type: 'annotation'; id: string; last: Point };

const HISTORY_LIMIT = 100;
/** Snap grid: 1/40th of the field, so a snapped route lands on whole yards. */
export const GRID = 0.025;

type RouteClip = Pick<DiagramRoute, 'kind' | 'name' | 'style'> & {
    offsets: Point[];
    fromLeft: boolean;
};

/**
 * Editing state and operations for a play diagram. Knows nothing about
 * persistence: callers pass a diagram in and read the diagram back out.
 */
export function usePlayEditor(initial: Diagram) {
    const diagram = ref<Diagram>(clone(initial));
    const selection = ref<Selection>(null);
    const tool = ref<Tool>('select');
    const drawKind = ref<RouteKind>('route');
    const drawingId = ref<string | null>(null);

    const undoStack = ref<string[]>([]);
    const redoStack = ref<string[]>([]);
    let drag: Drag | null = null;
    let dragSnapshot = '';
    let dragMoved = false;

    const snap = ref(false);
    const clip = ref<RouteClip | null>(null);

    const snapped = (point: Point): Point =>
        snap.value
            ? {
                  x: clamp01(Math.round(point.x / GRID) * GRID),
                  y: clamp01(Math.round(point.y / GRID) * GRID),
              }
            : point;

    const canUndo = computed(() => undoStack.value.length > 0);
    const canRedo = computed(() => redoStack.value.length > 0);

    const snapshot = () => JSON.stringify(diagram.value);

    function remember(before = snapshot()) {
        undoStack.value.push(before);

        if (undoStack.value.length > HISTORY_LIMIT) {
            undoStack.value.shift();
        }

        redoStack.value = [];
    }

    function undo() {
        const previous = undoStack.value.pop();

        if (previous === undefined) {
            return;
        }

        redoStack.value.push(snapshot());
        diagram.value = JSON.parse(previous);
        selection.value = null;
        drawingId.value = null;
    }

    function redo() {
        const next = redoStack.value.pop();

        if (next === undefined) {
            return;
        }

        undoStack.value.push(snapshot());
        diagram.value = JSON.parse(next);
        selection.value = null;
        drawingId.value = null;
    }

    const selectedPlayer = computed(() =>
        selection.value?.kind === 'player'
            ? diagram.value.players.find((p) => p.id === selection.value!.id)
            : undefined,
    );

    const selectedRoute = computed(() =>
        selection.value?.kind === 'route'
            ? diagram.value.routes.find((r) => r.id === selection.value!.id)
            : undefined,
    );

    const selectedAnnotation = computed(() =>
        selection.value?.kind === 'annotation'
            ? diagram.value.annotations.find(
                  (a) => a.id === selection.value!.id,
              )
            : undefined,
    );

    const player = (id: string) =>
        diagram.value.players.find((p) => p.id === id);

    function applyFormation(formation: FormationRecord) {
        remember();
        // A formation replaces the side(s) it contains; the other side stays as drawn.
        const hasDefense = formation.diagram.players.some(
            (p) => p.type === 'defender',
        );
        const hasOffense = formation.diagram.players.some(
            (p) => p.type !== 'defender',
        );
        const kept = diagram.value.players.filter((p) =>
            p.type === 'defender' ? !hasDefense : !hasOffense,
        );
        const keptIds = new Set(kept.map((p) => p.id));
        const next = diagramFromFormation(formation);
        next.players.push(...kept);
        next.routes = diagram.value.routes.filter((r) =>
            keptIds.has(r.player_id),
        );
        diagram.value = next;
        selection.value = null;
        drawingId.value = null;
    }

    function addDefender() {
        remember();
        const label = uniqueLabel(diagram.value.players, 'D');
        const count = diagram.value.players.filter(
            (p) => p.type === 'defender',
        ).length;
        const p: DiagramPlayer = {
            id: uid('d'),
            type: 'defender',
            label,
            x: clamp01(0.2 + (count % 5) * 0.15),
            y: Math.max(0.05, diagram.value.los - 0.1),
            color: DEFENSE_COLOR,
        };
        diagram.value.players.push(p);
        selection.value = { kind: 'player', id: p.id };
    }

    function addOffensePlayer() {
        remember();
        const p: DiagramPlayer = {
            id: uid('o'),
            type: 'receiver',
            label: uniqueLabel(diagram.value.players, 'R'),
            x: 0.5,
            y: Math.min(0.95, diagram.value.los + 0.2),
            color: OFFENSE_COLOR,
        };
        diagram.value.players.push(p);
        selection.value = { kind: 'player', id: p.id };
    }

    function setPlayerType(type: PlayerType) {
        const p = selectedPlayer.value;

        if (!p || p.type === type) {
            return;
        }

        remember();
        p.type = type;
        p.color = type === 'defender' ? DEFENSE_COLOR : OFFENSE_COLOR;
    }

    /** Move the selected player (with their routes) or note by a small step; fine=false is a bigger jump. */
    function nudge(dx: number, dy: number) {
        const step = snap.value ? GRID : 0.01;
        const sel = selection.value;

        if (!sel || sel.kind === 'route') {
            return;
        }

        remember();

        if (sel.kind === 'player') {
            const p = player(sel.id);

            if (!p) {
                return;
            }

            const nx = clamp01(p.x + dx * step);
            const ny = clamp01(p.y + dy * step);
            const mx = nx - p.x;
            const my = ny - p.y;
            p.x = nx;
            p.y = ny;
            diagram.value.routes
                .filter((r) => r.player_id === p.id)
                .forEach((r) =>
                    r.points.forEach((pt) => {
                        pt.x = clamp01(pt.x + mx);
                        pt.y = clamp01(pt.y + my);
                    }),
                );
        } else {
            const note = diagram.value.annotations.find((a) => a.id === sel.id);

            if (note) {
                note.x = clamp01(note.x + dx * step);
                note.y = clamp01(note.y + dy * step);
            }
        }
    }

    function copyRoute() {
        const r = selectedRoute.value;
        const owner = r && player(r.player_id);

        if (!r || !owner) {
            return;
        }

        clip.value = {
            kind: r.kind,
            name: r.name,
            style: { ...r.style },
            offsets: r.points.slice(1).map((pt) => ({
                x: pt.x - r.points[0].x,
                y: pt.y - r.points[0].y,
            })),
            fromLeft: owner.x < 0.5,
        };
    }

    /** Paste the copied route onto the selected player, mirrored if they stand on the other side of the field. */
    function pasteRoute() {
        const p = selectedPlayer.value;
        const c = clip.value;

        if (!p || !c) {
            return;
        }

        remember();
        const flip = c.fromLeft === p.x < 0.5 ? 1 : -1;
        const route: DiagramRoute = {
            id: uid('r'),
            player_id: p.id,
            kind: c.kind,
            name: c.name,
            points: [
                { x: p.x, y: p.y },
                ...c.offsets.map((o) => ({
                    x: clamp01(p.x + o.x * flip),
                    y: clamp01(p.y + o.y),
                })),
            ],
            style: { ...c.style },
        };
        diagram.value.routes = diagram.value.routes.filter(
            (r) => !(r.player_id === p.id && r.kind === c.kind),
        );
        diagram.value.routes.push(route);
        selection.value = { kind: 'route', id: route.id };
    }

    function addAnnotationAt(point: Point) {
        remember();
        const note = { id: uid('t'), text: 'Note', x: point.x, y: point.y };
        diagram.value.annotations.push(note);
        selection.value = { kind: 'annotation', id: note.id };
        tool.value = 'select';
    }

    function applyRouteTemplate(key: string, label: string) {
        const p = selectedPlayer.value;

        if (!p) {
            return;
        }

        remember();
        diagram.value.routes = diagram.value.routes.filter(
            (r) => !(r.player_id === p.id && r.kind === 'route'),
        );
        const route: DiagramRoute = {
            id: uid('r'),
            player_id: p.id,
            kind: 'route',
            name: label,
            points: buildRoute(key, p),
            style: { ...KIND_STYLES.route },
        };
        diagram.value.routes.push(route);
        selection.value = { kind: 'route', id: route.id };
    }

    function setRouteKind(kind: RouteKind) {
        const r = selectedRoute.value;

        if (!r) {
            return;
        }

        remember();
        r.kind = kind;
        r.style = { ...KIND_STYLES[kind] };
    }

    function setRouteDash(dash: RouteDash) {
        const r = selectedRoute.value;

        if (!r || r.style.dash === dash) {
            return;
        }

        remember();
        r.style.dash = dash;
    }

    function deleteSelection() {
        const sel = selection.value;

        if (!sel) {
            return;
        }

        remember();

        if (sel.kind === 'player') {
            diagram.value.players = diagram.value.players.filter(
                (p) => p.id !== sel.id,
            );
            diagram.value.routes = diagram.value.routes.filter(
                (r) => r.player_id !== sel.id,
            );
        } else if (sel.kind === 'route') {
            diagram.value.routes = diagram.value.routes.filter(
                (r) => r.id !== sel.id,
            );
        } else {
            diagram.value.annotations = diagram.value.annotations.filter(
                (a) => a.id !== sel.id,
            );
        }

        selection.value = null;
        drawingId.value = null;
    }

    /** Flip the whole play left-to-right, so a play drawn for one side can run to the other. */
    function mirror() {
        remember();
        const flip = (pt: Point) => {
            pt.x = 1 - pt.x;
        };

        diagram.value.players.forEach(flip);
        diagram.value.routes.forEach((r) => r.points.forEach(flip));
        diagram.value.annotations.forEach(flip);
    }

    function clearRoutes() {
        if (!diagram.value.routes.length) {
            return;
        }

        remember();
        diagram.value.routes = [];
        selection.value = null;
        drawingId.value = null;
    }

    function finishDrawing() {
        const route = diagram.value.routes.find(
            (r) => r.id === drawingId.value,
        );
        drawingId.value = null;

        if (route) {
            selection.value = { kind: 'route', id: route.id };
        }
    }

    function setTool(next: Tool) {
        finishDrawing();
        tool.value = next;
    }

    function startOrExtendRoute(point: Point) {
        const p = selectedPlayer.value;

        if (!p) {
            return;
        }

        const existing = diagram.value.routes.find(
            (r) => r.id === drawingId.value,
        );

        if (existing) {
            remember();
            existing.points.push(snapped(point));

            return;
        }

        remember();
        const route: DiagramRoute = {
            id: uid('r'),
            player_id: p.id,
            kind: drawKind.value,
            name: null,
            points: [{ x: p.x, y: p.y }, snapped(point)],
            style: { ...KIND_STYLES[drawKind.value] },
        };
        diagram.value.routes.push(route);
        drawingId.value = route.id;
    }

    function onDown(target: CanvasTarget, point: Point) {
        if (target.kind === 'player') {
            if (tool.value === 'draw' && selection.value?.id !== target.id) {
                finishDrawing();
            }

            selection.value = { kind: 'player', id: target.id };
            const p = player(target.id);

            if (p && tool.value !== 'draw') {
                drag = { type: 'player', id: target.id, last: point };
                dragSnapshot = snapshot();
                dragMoved = false;
            }

            return;
        }

        if (target.kind === 'handle') {
            drag = { type: 'handle', routeId: target.id, index: target.index };
            dragSnapshot = snapshot();
            dragMoved = false;

            return;
        }

        if (target.kind === 'annotation') {
            selection.value = { kind: 'annotation', id: target.id };
            drag = { type: 'annotation', id: target.id, last: point };
            dragSnapshot = snapshot();
            dragMoved = false;

            return;
        }

        if (target.kind === 'route' && tool.value !== 'draw') {
            selection.value = { kind: 'route', id: target.id };

            return;
        }

        if (tool.value === 'draw' && selectedPlayer.value) {
            startOrExtendRoute(point);

            return;
        }

        if (tool.value === 'text') {
            addAnnotationAt(point);

            return;
        }

        selection.value = null;
    }

    function onMove(point: Point) {
        if (!drag) {
            return;
        }

        dragMoved = true;
        const current = drag;

        if (current.type === 'player') {
            const p = player(current.id);

            if (!p) {
                return;
            }

            const target = snapped({
                x: p.x + point.x - current.last.x,
                y: p.y + point.y - current.last.y,
            });
            const dx = clamp01(target.x) - p.x;
            const dy = clamp01(target.y) - p.y;

            if (snap.value) {
                // Keep the leftover travel so the player lands on the next grid line once the cursor gets there.
                current.last = {
                    x: current.last.x + dx,
                    y: current.last.y + dy,
                };

                if (dx === 0 && dy === 0) {
                    return;
                }
            } else {
                current.last = point;
            }

            p.x = clamp01(p.x + dx);
            p.y = clamp01(p.y + dy);
            diagram.value.routes
                .filter((r) => r.player_id === p.id)
                .forEach((r) =>
                    r.points.forEach((pt) => {
                        pt.x = clamp01(pt.x + dx);
                        pt.y = clamp01(pt.y + dy);
                    }),
                );
        } else if (current.type === 'handle') {
            const route = diagram.value.routes.find(
                (r) => r.id === current.routeId,
            );

            if (route) {
                route.points[current.index] = snapped(point);
            }
        } else {
            const note = diagram.value.annotations.find(
                (a) => a.id === current.id,
            );

            if (note) {
                note.x = clamp01(note.x + point.x - current.last.x);
                note.y = clamp01(note.y + point.y - current.last.y);
                current.last = point;
            }
        }
    }

    function onUp() {
        if (drag && dragMoved) {
            remember(dragSnapshot);
        }

        drag = null;
        dragMoved = false;
    }

    function load(next: Diagram) {
        diagram.value = clone(next);
        selection.value = null;
        undoStack.value = [];
        redoStack.value = [];
        drawingId.value = null;
    }

    return {
        diagram,
        selection,
        tool,
        snap,
        clip,
        nudge,
        copyRoute,
        pasteRoute,
        drawKind,
        drawingId,
        canUndo,
        canRedo,
        selectedPlayer,
        selectedRoute,
        selectedAnnotation,
        undo,
        redo,
        remember,
        applyFormation,
        addDefender,
        addOffensePlayer,
        setPlayerType,
        applyRouteTemplate,
        setRouteKind,
        setRouteDash,
        deleteSelection,
        clearRoutes,
        mirror,
        finishDrawing,
        setTool,
        onDown,
        onMove,
        onUp,
        load,
    };
}
