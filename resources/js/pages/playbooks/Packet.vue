<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, Printer } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import PlaySheet from '@/components/play/PlaySheet.vue';
import { Button } from '@/components/ui/button';
import type { SheetItem } from '@/lib/diagram';
import { show } from '@/routes/playbooks';

type PacketPlay = SheetItem & { folder: string | null };

const props = defineProps<{
    team: string;
    playbook: { id: number; name: string; description: string | null };
    plays: PacketPlay[];
    generatedAt: string;
    shareUrl: string | null;
    public: boolean;
}>();

// Two plays to a letter page; the cover and contents get a page each.
let style: HTMLStyleElement | null = null;

onMounted(() => {
    style = document.createElement('style');
    style.textContent =
        '@page { size: letter portrait; margin: 0.5in; } @media print { body { background: #fff !important; } }';
    document.head.append(style);
});
onBeforeUnmount(() => style?.remove());

const print = () => window.print();

const downloadPdf = () => {
    window.location.href = `/pdf?to=${encodeURIComponent(window.location.pathname + window.location.search)}`;
};

const numbered = computed(() =>
    props.plays.map((play, index) => ({ play, number: index + 1 })),
);
</script>

<template>
    <Head :title="`${playbook.name} – ${team}`">
        <meta name="robots" content="noindex" />
    </Head>

    <div
        class="min-h-screen bg-neutral-100 py-4 text-neutral-900 print:bg-white print:py-0"
    >
        <div
            class="mx-auto mb-4 flex max-w-3xl items-center justify-between gap-2 px-4 print:hidden"
        >
            <Button v-if="!public" variant="outline" size="sm" as-child>
                <Link :href="show(playbook.id)"><ArrowLeft /> Back</Link>
            </Button>
            <span v-else class="text-sm text-neutral-600"
                >Shared by {{ team }}</span
            >
            <span class="flex gap-2">
                <Button
                    v-if="!public"
                    variant="outline"
                    size="sm"
                    @click="downloadPdf"
                >
                    <Download /> Download PDF
                </Button>
                <Button size="sm" @click="print"><Printer /> Print</Button>
            </span>
        </div>

        <main
            class="mx-auto flex max-w-3xl flex-col gap-4 px-4 print:max-w-none print:gap-0 print:p-0"
        >
            <section
                class="flex min-h-[9in] flex-col items-center justify-center gap-2 rounded-lg bg-white p-10 text-center shadow print:break-after-page print:rounded-none print:shadow-none"
            >
                <p class="text-sm tracking-widest text-neutral-500 uppercase">
                    {{ team }}
                </p>
                <h1 class="text-5xl font-black">{{ playbook.name }}</h1>
                <p
                    v-if="playbook.description"
                    class="max-w-md text-neutral-600"
                >
                    {{ playbook.description }}
                </p>
                <p class="mt-6 text-sm text-neutral-500">
                    {{ plays.length }}
                    {{ plays.length === 1 ? 'play' : 'plays' }} ·
                    {{ generatedAt }}
                </p>
            </section>

            <section
                class="min-h-[9in] rounded-lg bg-white p-10 shadow print:break-after-page print:rounded-none print:shadow-none"
            >
                <h2 class="mb-4 text-2xl font-bold">Contents</h2>
                <ol class="columns-2 gap-8 text-sm">
                    <li
                        v-for="item in numbered"
                        :key="item.play.id"
                        class="flex gap-2 py-0.5"
                    >
                        <span class="w-6 text-right font-bold">{{
                            item.number
                        }}</span>
                        <span class="flex-1 truncate">{{
                            item.play.name
                        }}</span>
                        <span class="text-neutral-500 capitalize">{{
                            item.play.category
                        }}</span>
                    </li>
                </ol>
            </section>

            <article
                v-for="item in numbered"
                :key="item.play.id"
                class="h-[4.5in] rounded-lg bg-white p-3 shadow print:h-[4.5in] print:break-inside-avoid print:rounded-none print:shadow-none"
            >
                <div class="flex h-full flex-col">
                    <div class="shrink-0 text-xs font-bold text-neutral-500">
                        {{ item.number }}
                    </div>
                    <div class="min-h-0 flex-1">
                        <PlaySheet
                            :play="item.play"
                            theme="paper"
                            size="lg"
                            :wide="true"
                            :show-notes="true"
                            :show-assignments="true"
                            :show-formation="true"
                            :show-details="true"
                            :show-route-labels="true"
                        />
                    </div>
                </div>
            </article>
        </main>
    </div>
</template>
