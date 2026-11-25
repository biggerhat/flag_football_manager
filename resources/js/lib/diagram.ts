export type PlayerType = 'center' | 'quarterback' | 'receiver' | 'defender';
export type RouteDash = 'solid' | 'dashed' | 'dotted';
export type RouteKind = 'route' | 'run' | 'handoff' | 'block';

export type Point = { x: number; y: number };

export type DiagramPlayer = Point & {
    id: string;
    type: PlayerType;
    label: string;
    color: string;
};

export type DiagramRoute = {
    id: string;
    player_id: string;
    kind: RouteKind;
    name?: string | null;
    points: Point[];
    style: {
        dash: RouteDash;
        end: 'arrow' | 'block' | 'none';
        color: string;
    };
};

export type DiagramAnnotation = Point & { id: string; text: string };

export type Diagram = {
    schema_version: 1;
    los: number;
    players: DiagramPlayer[];
    routes: DiagramRoute[];
    annotations: DiagramAnnotation[];
};

export type Selection =
    | { kind: 'player'; id: string }
    | { kind: 'route'; id: string }
    | { kind: 'annotation'; id: string }
    | null;

/** Field geometry: normalized (0..1) coordinates are scaled into this viewBox. */
export const FIELD_W = 100;
export const FIELD_H = 120;

export const OFFENSE_COLOR = '#2563eb';
export const DEFENSE_COLOR = '#dc2626';
export const ROUTE_COLOR = '#facc15';

export const uid = (prefix: string): string =>
    `${prefix}-${Math.random().toString(36).slice(2, 8)}`;

export const clamp01 = (n: number): number => Math.min(1, Math.max(0, n));

export const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value));

export type FormationRecord = {
    id: number;
    name: string;
    description?: string | null;
    diagram: Diagram;
    updated_at?: string;
};

export const LOS = 0.68;

export const emptyDiagram = (): Diagram => ({
    schema_version: 1,
    los: LOS,
    players: [],
    routes: [],
    annotations: [],
});

/** A play diagram that starts from a saved formation's line of scrimmage and players. */
export const diagramFromFormation = (formation: FormationRecord): Diagram => ({
    ...emptyDiagram(),
    los: formation.diagram.los,
    players: clone(formation.diagram.players),
});

export type RouteTemplate = { key: string; label: string };

export const ROUTE_TEMPLATES: RouteTemplate[] = [
    { key: 'go', label: 'Go' },
    { key: 'slant', label: 'Slant' },
    { key: 'out', label: 'Out' },
    { key: 'in', label: 'In' },
    { key: 'hitch', label: 'Hitch' },
    { key: 'curl', label: 'Curl' },
    { key: 'post', label: 'Post' },
    { key: 'corner', label: 'Corner' },
    { key: 'drag', label: 'Drag' },
    { key: 'wheel', label: 'Wheel' },
    { key: 'flat', label: 'Flat' },
    { key: 'comeback', label: 'Comeback' },
    { key: 'zigzag', label: 'Zig-zag' },
    { key: 'fade', label: 'Fade' },
    { key: 'whip', label: 'Whip' },
    { key: 'bubble', label: 'Bubble' },
    { key: 'shallow', label: 'Shallow' },
    { key: 'stopgo', label: 'Stop & go' },
];

/** Builds a route path for a player. "in" always means toward the middle of the field. */
export function buildRoute(key: string, start: Point): Point[] {
    const inward = start.x < 0.5 ? 1 : -1;
    const { x, y } = start;
    const at = (dx: number, dy: number): Point => ({
        x: clamp01(x + dx * inward),
        y: clamp01(y - dy),
    });

    const offsets: Record<string, Point[]> = {
        go: [at(0, 0.4)],
        slant: [at(0, 0.05), at(0.2, 0.2)],
        out: [at(0, 0.18), at(-0.22, 0.18)],
        in: [at(0, 0.18), at(0.25, 0.18)],
        hitch: [at(0, 0.18), at(0, 0.14)],
        curl: [at(0, 0.24), at(0.06, 0.18)],
        post: [at(0, 0.18), at(0.2, 0.38)],
        corner: [at(0, 0.18), at(-0.2, 0.38)],
        drag: [at(0, 0.08), at(0.4, 0.08)],
        wheel: [at(-0.12, 0.05), at(-0.15, 0.25), at(-0.15, 0.42)],
        flat: [at(-0.28, 0.03)],
        comeback: [at(0, 0.32), at(-0.08, 0.25)],
        zigzag: [at(0, 0.12), at(0.12, 0.22), at(0, 0.32), at(0.12, 0.42)],
        fade: [at(-0.06, 0.18), at(-0.1, 0.42)],
        whip: [at(0, 0.1), at(0.12, 0.1), at(-0.08, 0.02)],
        bubble: [at(-0.12, -0.04), at(-0.2, 0.02)],
        shallow: [at(0.1, 0.06), at(0.4, 0.1)],
        stopgo: [at(0, 0.2), at(0, 0.18), at(-0.04, 0.42)],
    };

    return [{ x, y }, ...(offsets[key] ?? [])];
}

/** Speed of the animated preview, in svg units per second. */
export const PREVIEW_SPEED = 28;

/** Total length of a polyline in svg units. */
export function pathLength(points: Point[]): number {
    const pts = points.map(toSvg);

    return pts
        .slice(1)
        .reduce(
            (sum, p, i) => sum + Math.hypot(p.x - pts[i].x, p.y - pts[i].y),
            0,
        );
}

/** The normalized point `distance` svg units along a polyline (clamped to its ends). */
export function positionAlong(points: Point[], distance: number): Point {
    const pts = points.map(toSvg);
    let left = Math.max(0, distance);

    for (let i = 1; i < pts.length; i++) {
        const seg = Math.hypot(
            pts[i].x - pts[i - 1].x,
            pts[i].y - pts[i - 1].y,
        );

        if (left <= seg && seg > 0) {
            const k = left / seg;

            return {
                x: points[i - 1].x + (points[i].x - points[i - 1].x) * k,
                y: points[i - 1].y + (points[i].y - points[i - 1].y) * k,
            };
        }

        left -= seg;
    }

    return points[points.length - 1];
}

/** SVG dash pattern per line style; dots rely on the round line caps the canvas uses. */
export const DASH_PATTERNS: Record<RouteDash, string | undefined> = {
    solid: undefined,
    dashed: '2.4 1.8',
    dotted: '0.01 2.2',
};

export const DASH_OPTIONS: { key: RouteDash; label: string }[] = [
    { key: 'solid', label: 'Solid' },
    { key: 'dashed', label: 'Dashed' },
    { key: 'dotted', label: 'Dotted' },
];

export const KIND_STYLES: Record<RouteKind, DiagramRoute['style']> = {
    route: { dash: 'solid', end: 'arrow', color: ROUTE_COLOR },
    run: { dash: 'dashed', end: 'arrow', color: '#ffffff' },
    handoff: { dash: 'dashed', end: 'arrow', color: '#f97316' },
    block: { dash: 'solid', end: 'block', color: '#a3e635' },
};

export const toSvg = (p: Point): Point => ({
    x: p.x * FIELD_W,
    y: p.y * FIELD_H,
});

/** Arrowhead triangle (as svg points) at the end of a path, or the T-bar for blocks. */
export function endMarker(
    points: Point[],
    end: 'arrow' | 'block' | 'none',
): string | null {
    if (end === 'none' || points.length < 2) {
        return null;
    }

    const a = toSvg(points[points.length - 2]);
    const b = toSvg(points[points.length - 1]);
    const len = Math.hypot(b.x - a.x, b.y - a.y) || 1;
    const ux = (b.x - a.x) / len;
    const uy = (b.y - a.y) / len;
    const px = -uy;
    const py = ux;

    if (end === 'block') {
        const w = 3.2;

        return `${b.x + px * w},${b.y + py * w} ${b.x - px * w},${b.y - py * w}`;
    }

    const size = 3.4;
    const bx = b.x - ux * size;
    const by = b.y - uy * size;

    return `${b.x},${b.y} ${bx + px * size * 0.55},${by + py * size * 0.55} ${bx - px * size * 0.55},${by - py * size * 0.55}`;
}

/** Path data that stops short of the end marker so the line does not poke through the arrowhead. */
export function pathData(
    points: Point[],
    end: 'arrow' | 'block' | 'none',
): string {
    const pts = points.map(toSvg);

    if (pts.length >= 2 && end === 'arrow') {
        const a = pts[pts.length - 2];
        const b = pts[pts.length - 1];
        const len = Math.hypot(b.x - a.x, b.y - a.y);

        if (len > 3) {
            const k = (len - 2.4) / len;
            pts[pts.length - 1] = {
                x: a.x + (b.x - a.x) * k,
                y: a.y + (b.y - a.y) * k,
            };
        }
    }

    return pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`).join(' ');
}

/** Where a route's name sits: just beyond its end, along the direction of travel (svg units). */
export function routeLabelPosition(points: Point[]): Point | null {
    if (points.length < 2) {
        return null;
    }

    const a = toSvg(points[points.length - 2]);
    const b = toSvg(points[points.length - 1]);
    const len = Math.hypot(b.x - a.x, b.y - a.y) || 1;
    const reach = 4.6;

    return {
        x: Math.min(95, Math.max(5, b.x + ((b.x - a.x) / len) * reach)),
        y: b.y + ((b.y - a.y) / len) * reach + 1,
    };
}

/** First `${prefix}${n}` not already used as a label, so adds after deletions don't duplicate. */
export const uniqueLabel = (
    players: DiagramPlayer[],
    prefix: string,
): string => {
    const used = new Set(players.map((p) => p.label));
    let n = 1;

    while (used.has(`${prefix}${n}`)) {
        n++;
    }

    return `${prefix}${n}`;
};

export const PLAYER_TYPES: { key: PlayerType; label: string }[] = [
    { key: 'center', label: 'Center' },
    { key: 'quarterback', label: 'Quarterback' },
    { key: 'receiver', label: 'Receiver / back' },
    { key: 'defender', label: 'Defender' },
];

export const CATEGORIES = ['run', 'pass', 'trick', 'defense', 'other'] as const;
export const DIFFICULTIES = ['easy', 'medium', 'hard'] as const;

export type PlayRecord = {
    id: number;
    playbook_id?: number;
    name: string;
    formation: string | null;
    category: (typeof CATEGORIES)[number];
    difficulty: (typeof DIFFICULTIES)[number];
    beginner_friendly: boolean;
    is_favorite?: boolean;
    tags: string[] | null;
    folder?: string | null;
    parent_id?: number | null;
    last_run?: string | null;
    stats?: {
        calls: number;
        yards: number;
        touchdowns: number;
        success: number;
    } | null;
    notes: string | null;
    diagram: Diagram;
    updated_at?: string;
};

/**
 * Plain-language assignment lines derived from the diagram, e.g. "X: Slant".
 * Defensive plays list the defenders instead of the offense.
 */
export function assignments(
    diagram: Diagram,
    category?: string | null,
): { label: string; text: string }[] {
    const defense = category === 'defense';

    return diagram.players
        .filter((p) => (p.type === 'defender') === defense)
        .map((p) => {
            const routes = diagram.routes.filter((r) => r.player_id === p.id);
            const text = routes.length
                ? routes
                      .map(
                          (r) =>
                              r.name ||
                              {
                                  route: defense ? 'Cover' : 'Route',
                                  run: 'Run',
                                  handoff: 'Handoff',
                                  block: 'Block',
                              }[r.kind],
                      )
                      .join(', ')
                : defense
                  ? 'Hold'
                  : 'Stay in';

            return { label: p.label, text };
        });
}

/** What a printed sheet needs: a play, or a formation shaped like one. */
export type SheetItem = {
    id: number;
    name: string;
    category?: string | null;
    difficulty?: string | null;
    formation?: string | null;
    notes: string | null;
    diagram: Diagram;
};
