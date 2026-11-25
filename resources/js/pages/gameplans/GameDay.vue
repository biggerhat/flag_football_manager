<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    Ban,
    Maximize2,
    Minus,
    Monitor,
    Plus,
    RefreshCcw,
    Star,
    Undo2,
    WifiOff,
    X,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { useWakeLock } from '@/composables/useWakeLock';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { readQueue, writeQueue } from '@/lib/callQueue';
import type { QueuedCall } from '@/lib/callQueue';
import { drainQueue, isRetryableStatus } from '@/lib/callSync';
import type { SendOutcome } from '@/lib/callSync';
import { touchBalance } from '@/lib/touchBalance';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCanEdit } from '@/composables/useCanEdit';
import type { Diagram } from '@/lib/diagram';
import callRoutes from '@/routes/gameplans/calls';
import { edit, gameday, index, score as saveScore } from '@/routes/gameplans';

type Play = { id: number; name: string; category: string; diagram: Diagram };
type Call = {
    id: number;
    play_id: number;
    play: string;
    result: string;
    yards: number | null;
    down: number | null;
    note: string | null;
    player_id: number | null;
};
type Stats = {
    calls: number;
    yards: number;
    touchdowns: number;
    success: number;
};

const props = defineProps<{
    gamePlan: {
        id: number;
        name: string;
        opponent: string | null;
        score_for: number | null;
        score_against: number | null;
        notes: string | null;
    };
    plays: Play[];
    players: { id: number; name: string; number: string | null }[];
    calls: Call[];
    stats: Record<number, Stats>;
    results: string[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Game plans', href: index() }] },
});

const canEdit = useCanEdit();
const wake = useWakeLock();
// Larger buttons and type for use on a sideline with gloves.
const sideline = ref(false);

const LABELS: Record<string, string> = {
    touchdown: 'TD',
    gain: 'Gain',
    no_gain: 'No gain',
    loss: 'Loss',
    incomplete: 'Incomplete',
    turnover: 'Turnover',
};
// An icon beside each result so the outcome never depends on color or a short label.
const ICONS = {
    touchdown: Star,
    gain: ArrowUpRight,
    no_gain: Minus,
    loss: ArrowDownRight,
    incomplete: X,
    turnover: RefreshCcw,
} as const;
const ICON_FALLBACK = Ban;
const ORDINAL: Record<number, string> = { 1: 'st', 2: 'nd', 3: 'rd', 4: 'th' };
const needsYards = new Set(['gain', 'loss']);

// Yards for the next logged gain or loss, adjusted with the stepper.
const yards = ref(5);
// Optional context attached to the next logged call.
const down = ref<number | null>(null);
const note = ref('');
// Who had the ball for the next logged call (optional).
const playerId = ref<number | null>(null);
// Calls logged while offline, kept in this browser until they can be sent.
const pending = ref<QueuedCall[]>(readQueue(props.gamePlan.id));
let flushing = false;

function persist() {
    writeQueue(props.gamePlan.id, pending.value);
}

const payload = (c: QueuedCall) => ({
    client_key: c.key,
    play_id: c.play_id,
    player_id: c.player_id,
    result: c.result,
    yards: c.yards,
    down: c.down,
    note: c.note,
});

/** Post one call and report how it ended (see SendOutcome). */
function send(call: QueuedCall): Promise<SendOutcome> {
    return new Promise((resolve) => {
        let outcome: SendOutcome = 'sent';

        router.post(callRoutes.store.url(props.gamePlan.id), payload(call), {
            preserveScroll: true,
            onError: () => {
                outcome = 'rejected';
            },
            onHttpException: (response) => {
                outcome = isRetryableStatus(response.status)
                    ? 'unreachable'
                    : 'rejected';
            },
            onNetworkError: () => {
                outcome = 'unreachable';
            },
            onFinish: () => resolve(outcome),
        });
    });
}

async function flush() {
    if (flushing || !pending.value.length || !navigator.onLine) {
        return;
    }

    flushing = true;

    try {
        const { remaining } = await drainQueue(pending.value, send);

        pending.value = remaining;
        persist();
    } finally {
        flushing = false;
    }
}

let retryTimer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    window.addEventListener('online', flush);
    // A weak signal can leave calls queued while the browser still says it is online.
    retryTimer = setInterval(flush, 15000);
    flush();
});
onBeforeUnmount(() => {
    window.removeEventListener('online', flush);
    clearInterval(retryTimer);
});

const called = computed(() => {
    const counts = new Map<number, number>();

    for (const call of [...props.calls, ...pending.value]) {
        counts.set(call.play_id, (counts.get(call.play_id) ?? 0) + 1);
    }

    return counts;
});

const balance = computed(() =>
    touchBalance(props.players, [...props.calls, ...pending.value]),
);
const mostTouches = computed(() =>
    Math.max(1, ...balance.value.map((p) => p.touches)),
);

function log(play: Play, result: string) {
    const amount = needsYards.has(result) ? yards.value : null;
    const entry: QueuedCall = {
        key: crypto.randomUUID(),
        play_id: play.id,
        play: play.name,
        player_id: playerId.value,
        result,
        yards: result === 'loss' && amount ? -Math.abs(amount) : amount,
        down: down.value,
        note: note.value.trim() || null,
    };

    const keepForLater = () => {
        pending.value.push(entry);
        persist();
        note.value = '';
        toast(`${play.name}: ${LABELS[result]}`, {
            description:
                'No connection. Saved on this device and will send when you are back online.',
        });
    };

    if (!navigator.onLine) {
        keepForLater();

        return;
    }

    router.post(callRoutes.store.url(props.gamePlan.id), payload(entry), {
        preserveScroll: true,
        onSuccess: () => {
            note.value = '';
            toast(`${play.name}: ${LABELS[result]}`, {
                duration: 6000,
                action: {
                    label: 'Undo',
                    onClick: () => {
                        const latest = props.calls[0];

                        if (latest && latest.play_id === play.id) {
                            undo(latest);
                        }
                    },
                },
            });
        },
        // The signal dropped between the tap and the server: keep the call instead of losing it.
        onNetworkError: keepForLater,
    });
}

function undo(call: Call) {
    router.delete(
        callRoutes.destroy.url({ gameplan: props.gamePlan.id, call: call.id }),
        {
            preserveScroll: true,
        },
    );
}

const score = useForm({
    score_for: props.gamePlan.score_for ?? ('' as number | ''),
    score_against: props.gamePlan.score_against ?? ('' as number | ''),
});

function saveScores() {
    score
        .transform((data) => ({
            score_for: data.score_for === '' ? null : data.score_for,
            score_against:
                data.score_against === '' ? null : data.score_against,
        }))
        .put(saveScore.url(props.gamePlan.id), { preserveScroll: true });
}

function avg(stats: Stats | undefined): string | null {
    return stats?.calls
        ? `${stats.success}/${stats.calls} worked · ${(stats.yards / stats.calls).toFixed(1)} yds`
        : null;
}
</script>

<template>
    <Head :title="`Game day – ${gamePlan.name}`" />

    <div class="flex flex-col gap-4 p-4" :class="sideline && 'text-lg'">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ gamePlan.name }}</h1>
                <p
                    v-if="gamePlan.opponent"
                    class="text-sm text-muted-foreground"
                >
                    vs {{ gamePlan.opponent }}
                </p>
            </div>
            <div class="flex items-center gap-1">
                <Button
                    size="sm"
                    :variant="sideline ? 'default' : 'outline'"
                    :aria-pressed="sideline"
                    @click="sideline = !sideline"
                >
                    <Maximize2 /> Sideline mode
                </Button>
                <Button
                    v-if="wake.supported"
                    size="sm"
                    :variant="wake.active.value ? 'default' : 'outline'"
                    :aria-pressed="wake.active.value"
                    title="Keep the screen awake"
                    @click="wake.toggle()"
                >
                    <Monitor /> Stay awake
                </Button>
            </div>
            <form class="flex items-center gap-2" @submit.prevent="saveScores">
                <label class="flex items-center gap-1 text-sm">
                    Us
                    <Input
                        v-model="score.score_for"
                        type="number"
                        min="0"
                        class="w-16"
                        :disabled="!canEdit"
                    />
                </label>
                <label class="flex items-center gap-1 text-sm">
                    Them
                    <Input
                        v-model="score.score_against"
                        type="number"
                        min="0"
                        class="w-16"
                        :disabled="!canEdit"
                    />
                </label>
                <Button
                    v-if="canEdit"
                    type="submit"
                    size="sm"
                    variant="outline"
                    :disabled="score.processing"
                >
                    Save score
                </Button>
            </form>
        </div>

        <div
            v-if="canEdit"
            class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm"
        >
            Yards for the next gain or loss
            <Button
                size="icon-sm"
                variant="outline"
                aria-label="Fewer yards"
                @click="yards = Math.max(0, yards - 1)"
            >
                <Minus />
            </Button>
            <span class="w-8 text-center text-lg font-semibold">{{
                yards
            }}</span>
            <Button
                size="icon-sm"
                variant="outline"
                aria-label="More yards"
                @click="yards = Math.min(99, yards + 1)"
            >
                <Plus />
            </Button>
        </div>

        <div
            v-if="canEdit"
            class="flex flex-wrap items-center gap-2 rounded-xl border px-3 py-2 text-sm"
        >
            Down
            <Button
                v-for="d in [1, 2, 3, 4]"
                :key="d"
                :size="sideline ? 'lg' : 'sm'"
                :class="sideline && 'size-14 text-base'"
                :variant="down === d ? 'default' : 'outline'"
                :aria-pressed="down === d"
                @click="down = down === d ? null : d"
                >{{ d }}</Button
            >
            <select
                v-if="players.length"
                v-model="playerId"
                aria-label="Player with the ball"
                class="h-9 rounded-md border border-input bg-background px-2 text-sm shadow-xs"
            >
                <option :value="null">Player (optional)</option>
                <option v-for="p in players" :key="p.id" :value="p.id">
                    {{ p.number ? `#${p.number} ` : '' }}{{ p.name }}
                </option>
            </select>
            <Input
                v-model="note"
                class="min-w-40 flex-1"
                maxlength="120"
                placeholder="Note for the next call, such as who caught it"
                aria-label="Call note"
            />
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="(play, i) in plays"
                :key="play.id"
                class="flex flex-col gap-2 rounded-xl border p-3"
            >
                <div class="flex items-center gap-2">
                    <span class="text-lg font-bold text-muted-foreground">{{
                        i + 1
                    }}</span>
                    <h2 class="flex-1 font-medium">{{ play.name }}</h2>
                    <Badge v-if="called.get(play.id)" variant="secondary">
                        Called {{ called.get(play.id) }}×
                    </Badge>
                </div>
                <div class="aspect-[5/6] w-full">
                    <DiagramCanvas :diagram="play.diagram" theme="paper" fit />
                </div>
                <p
                    v-if="avg(stats[play.id])"
                    class="text-xs text-muted-foreground"
                >
                    Season: {{ avg(stats[play.id]) }}
                </p>
                <div v-if="canEdit" class="flex flex-wrap gap-1">
                    <Button
                        v-for="r in results"
                        :key="r"
                        :size="sideline ? 'lg' : 'sm'"
                        :class="sideline && 'h-14 min-w-24 text-base'"
                        :variant="r === 'touchdown' ? 'default' : 'outline'"
                        @click="log(play, r)"
                    >
                        <component
                            :is="
                                ICONS[r as keyof typeof ICONS] ?? ICON_FALLBACK
                            "
                        />
                        {{ LABELS[r] }}
                    </Button>
                </div>
            </div>
        </div>

        <section
            v-if="pending.length"
            class="rounded-xl border border-warning/50 bg-warning/5"
        >
            <h2 class="flex items-center gap-2 border-b px-4 py-3 font-medium">
                <WifiOff class="size-4" />
                Waiting to send ({{ pending.length }})
            </h2>
            <ul class="divide-y text-sm">
                <li
                    v-for="call in [...pending].reverse()"
                    :key="call.key"
                    class="flex items-center gap-3 px-4 py-2"
                >
                    <span class="flex-1">{{ call.play }}</span>
                    <span>{{ LABELS[call.result] }}</span>
                    <span v-if="call.yards !== null" class="w-12 text-right">
                        {{ call.yards > 0 ? '+' : '' }}{{ call.yards }}
                    </span>
                </li>
            </ul>
            <p class="px-4 pb-3 text-xs text-muted-foreground">
                Saved on this device. They send on their own when the connection
                returns.
            </p>
        </section>

        <section
            v-if="players.length && calls.some((c) => c.player_id)"
            class="rounded-xl border"
        >
            <h2 class="border-b px-4 py-3 font-medium">Touches this game</h2>
            <ul class="divide-y text-sm">
                <li
                    v-for="p in balance"
                    :key="p.id"
                    class="flex items-center gap-3 px-4 py-2"
                >
                    <span class="w-40 truncate">
                        <span v-if="p.number" class="text-muted-foreground"
                            >#{{ p.number }}
                        </span>
                        {{ p.name }}
                    </span>
                    <span
                        class="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                    >
                        <span
                            class="block h-full rounded-full"
                            :class="
                                p.touches === 0 ? 'bg-warning' : 'bg-primary'
                            "
                            :style="{
                                width: `${(p.touches / mostTouches) * 100}%`,
                            }"
                        />
                    </span>
                    <span
                        class="w-6 text-right tabular-nums"
                        :class="p.touches === 0 && 'font-medium text-warning'"
                        >{{ p.touches }}</span
                    >
                </li>
            </ul>
        </section>

        <section v-if="calls.length" class="rounded-xl border">
            <h2 class="border-b px-4 py-3 font-medium">Calls this game</h2>
            <ol class="divide-y text-sm">
                <li
                    v-for="(call, i) in calls"
                    :key="call.id"
                    class="flex items-center gap-3 px-4 py-2"
                >
                    <span class="w-6 text-right text-muted-foreground">{{
                        calls.length - i
                    }}</span>
                    <span class="flex-1">
                        {{ call.play }}
                        <span
                            v-if="call.down || call.note"
                            class="text-xs text-muted-foreground"
                        >
                            <template v-if="call.down">
                                · {{ call.down }}{{ ORDINAL[call.down] }} down
                            </template>
                            <template v-if="call.note">
                                · {{ call.note }}</template
                            >
                        </span>
                    </span>
                    <span class="flex items-center gap-1">
                        <component
                            :is="
                                ICONS[call.result as keyof typeof ICONS] ??
                                ICON_FALLBACK
                            "
                            class="size-4"
                        />
                        {{ LABELS[call.result] }}
                    </span>
                    <span v-if="call.yards !== null" class="w-12 text-right">
                        {{ call.yards > 0 ? '+' : '' }}{{ call.yards }}
                    </span>
                    <Button
                        v-if="canEdit"
                        size="icon-sm"
                        variant="ghost"
                        aria-label="Undo this call"
                        @click="undo(call)"
                    >
                        <Undo2 />
                    </Button>
                </li>
            </ol>
        </section>

        <div class="flex gap-2 text-sm">
            <Button variant="outline" size="sm" as-child>
                <a :href="edit.url(gamePlan.id)">Edit plan</a>
            </Button>
        </div>
    </div>
</template>
