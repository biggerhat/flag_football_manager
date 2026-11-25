<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Download, Upload } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    exportMethod as exportTeam,
    importMethod as importTeam,
} from '@/routes/team/backup';

const restoreForm = useForm<{ file: File | null }>({ file: null });

function restoreBackup(event: Event) {
    const input = event.target as HTMLInputElement;

    restoreForm.file = input.files?.[0] ?? null;

    if (restoreForm.file) {
        restoreForm.post(importTeam.url(), {
            forceFormData: true,
            onFinish: () => {
                input.value = '';
                restoreForm.reset();
            },
        });
    }
}
</script>

<template>
    <section class="rounded-xl border">
        <h2 class="border-b px-4 py-3 font-medium">Backup</h2>
        <div class="flex flex-col gap-2 p-4 text-sm">
            <p class="text-muted-foreground">
                Download everything this team holds (playbooks, plays,
                formations, drills, practices, game plans, calls and roster) as
                one file you can keep as a backup.
            </p>
            <Button variant="outline" size="sm" class="self-start" as-child>
                <a :href="exportTeam.url()"><Download /> Download backup</a>
            </Button>
            <p class="text-sm text-muted-foreground">
                Restoring adds everything from a backup file to this team.
                Nothing existing is overwritten.
            </p>
            <label
                class="inline-flex w-fit cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-accent"
            >
                <Upload class="size-4" />
                {{
                    restoreForm.processing
                        ? 'Restoring…'
                        : 'Restore from backup'
                }}
                <input
                    type="file"
                    accept="application/json,.json"
                    class="sr-only"
                    :disabled="restoreForm.processing"
                    @change="restoreBackup"
                />
            </label>
            <InputError :message="restoreForm.errors.file" />
        </div>
    </section>
</template>
