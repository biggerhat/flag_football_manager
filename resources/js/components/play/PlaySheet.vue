<script setup lang="ts">
import { computed } from 'vue';
import DiagramCanvas from '@/components/play/DiagramCanvas.vue';
import { assignments } from '@/lib/diagram';
import type { SheetItem } from '@/lib/diagram';

const props = defineProps<{
    play: SheetItem;
    theme: 'paper' | 'bw' | 'dark';
    /** Bump the text up one step for reading from a distance. */
    large?: boolean;
    dark?: boolean;
    /** Text scale: larger when fewer plays share a page. */
    size: 'xl' | 'lg' | 'md' | 'sm';
    /** Wide cells put the text beside the diagram instead of under it. */
    wide: boolean;
    showNotes: boolean;
    showAssignments: boolean;
    showFormation: boolean;
    showDetails: boolean;
    showRouteLabels?: boolean;
    /** Player names by position label, shown beside each assignment. */
    names?: Record<string, string>;
}>();

const lines = computed(() =>
    assignments(props.play.diagram, props.play.category),
);

const subtitle = computed(() => {
    const parts: string[] = [];

    if (props.showDetails && props.play.category) {
        parts.push(props.play.category);

        if (props.play.difficulty) {
            parts.push(props.play.difficulty);
        }
    }

    if (props.showFormation && props.play.formation) {
        parts.push(props.play.formation);
    }

    return parts.join(' · ');
});

const titleClass = computed(
    () =>
        (props.large
            ? { xl: 'text-5xl', lg: 'text-4xl', md: 'text-2xl', sm: 'text-lg' }
            : { xl: 'text-3xl', lg: 'text-2xl', md: 'text-lg', sm: 'text-sm' })[
            props.size
        ],
);
const textClass = computed(
    () =>
        (props.large
            ? { xl: 'text-2xl', lg: 'text-xl', md: 'text-base', sm: 'text-xs' }
            : {
                  xl: 'text-lg',
                  lg: 'text-base',
                  md: 'text-sm',
                  sm: 'text-[10px]',
              })[props.size],
);
const mutedClass = computed(() =>
    props.dark ? 'text-neutral-300' : 'text-neutral-600',
);
</script>

<template>
    <article class="flex h-full min-h-0 flex-col gap-1 overflow-hidden">
        <header class="shrink-0 text-center">
            <h2 class="leading-tight font-bold" :class="titleClass">
                {{ play.name }}
            </h2>
            <p
                v-if="subtitle"
                :class="[mutedClass, large ? 'text-sm' : 'text-xs']"
                class="capitalize"
            >
                {{ subtitle }}
            </p>
        </header>

        <div
            class="flex min-h-0 flex-1 gap-3"
            :class="wide ? 'flex-row' : 'flex-col'"
        >
            <div
                class="relative min-h-0 min-w-0"
                :class="wide ? 'flex-[3]' : 'flex-1'"
            >
                <div class="absolute inset-0">
                    <DiagramCanvas
                        :diagram="play.diagram"
                        :theme="theme"
                        :route-labels="showRouteLabels"
                        fit
                    />
                </div>
            </div>

            <div
                v-if="
                    (showAssignments && lines.length) ||
                    (showNotes && play.notes)
                "
                class="shrink-0 overflow-hidden"
                :class="[wide ? 'flex-[2] self-center' : '', textClass]"
            >
                <ul v-if="showAssignments">
                    <li v-for="line in lines" :key="line.label">
                        <span class="font-bold">{{ line.label }}</span
                        ><template v-if="names?.[line.label]">
                            ({{ names[line.label] }})</template
                        >: {{ line.text }}
                    </li>
                </ul>
                <p
                    v-if="showNotes && play.notes"
                    class="whitespace-pre-line"
                    :class="[
                        dark ? 'text-neutral-200' : 'text-neutral-700',
                        showAssignments ? 'mt-1' : '',
                    ]"
                >
                    {{ play.notes }}
                </p>
            </div>
        </div>
    </article>
</template>
