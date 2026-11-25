<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Clipboard, Dumbbell, Share2, Trophy } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

defineProps<{ inviteOnly?: boolean }>();

const page = usePage();

const features = [
    {
        icon: Clipboard,
        title: 'Design plays',
        text: 'Draw routes on a field, save formations and organise plays into playbooks.',
    },
    {
        icon: Dumbbell,
        title: 'Plan practices',
        text: 'Build a schedule from your drills and run it with a timer on the field.',
    },
    {
        icon: Trophy,
        title: 'Call the game',
        text: 'Sideline mode logs every call, tracks results and keeps your record.',
    },
    {
        icon: Share2,
        title: 'Keep families in the loop',
        text: 'Share a playbook, a schedule feed or a family page with a single link.',
    },
];
</script>

<template>
    <Head title="Flag Football Coach" />

    <div class="mx-auto flex min-h-svh max-w-3xl flex-col gap-10 px-6 py-10">
        <header class="flex items-center justify-between">
            <span class="text-lg font-semibold text-primary"
                >Flag Football Coach</span
            >
            <nav class="flex gap-2">
                <Button v-if="page.props.auth.user" as-child>
                    <Link :href="dashboard()">Dashboard</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Sign in</Link>
                    </Button>
                    <Button v-if="!inviteOnly" as-child>
                        <Link :href="register()">Get started</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <section class="flex flex-col gap-3 py-8">
            <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">
                Your whole season, in one playbook.
            </h1>
            <p class="max-w-xl text-lg text-muted-foreground">
                Plays, practices and game days for flag football coaches. Works
                on the sideline, even without signal.
            </p>
            <div v-if="!page.props.auth.user" class="flex gap-2 pt-2">
                <Button v-if="!inviteOnly" size="lg" as-child>
                    <Link :href="register()">Create your team</Link>
                </Button>
                <Button
                    size="lg"
                    :variant="inviteOnly ? 'default' : 'outline'"
                    as-child
                >
                    <Link :href="login()">Sign in</Link>
                </Button>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2">
            <div
                v-for="feature in features"
                :key="feature.title"
                class="flex flex-col gap-2 rounded-xl border p-4"
            >
                <component
                    :is="feature.icon"
                    class="size-6 text-primary"
                    aria-hidden="true"
                />
                <h2 class="font-medium">{{ feature.title }}</h2>
                <p class="text-sm text-muted-foreground">{{ feature.text }}</p>
            </div>
        </section>
    </div>
</template>
