<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    icon: Component;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center"
    >
        <div
            class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
        >
            <component :is="icon" class="size-6" />
        </div>
        <h3 class="font-medium">{{ title }}</h3>
        <p v-if="description" class="max-w-md text-sm text-muted-foreground">
            {{ description }}
        </p>
        <div
            v-if="$slots.default"
            class="mt-2 flex flex-wrap justify-center gap-2"
        >
            <slot />
        </div>
    </div>
</template>
