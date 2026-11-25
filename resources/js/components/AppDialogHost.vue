<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { activeDialog } from '@/composables/useDialog';

const value = ref('');

watch(activeDialog, (dialog) => {
    value.value = dialog?.kind === 'prompt' ? dialog.defaultValue : '';
});

function close(accepted: boolean) {
    const dialog = activeDialog.value;
    activeDialog.value = null;

    if (dialog?.kind === 'confirm') {
        dialog.resolve(accepted);
    } else if (dialog) {
        dialog.resolve(accepted ? value.value : null);
    }
}
</script>

<template>
    <Dialog
        :open="activeDialog !== null"
        @update:open="(open) => !open && close(false)"
    >
        <DialogContent v-if="activeDialog" :show-close-button="false">
            <form class="grid gap-4" @submit.prevent="close(true)">
                <DialogHeader>
                    <DialogTitle>{{ activeDialog.title }}</DialogTitle>
                    <DialogDescription>{{
                        activeDialog.description ?? ''
                    }}</DialogDescription>
                </DialogHeader>
                <Input
                    v-if="activeDialog.kind === 'prompt'"
                    v-model="value"
                    v-focus
                    :aria-label="activeDialog.title"
                />
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="close(false)"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        :variant="
                            activeDialog.kind === 'confirm' &&
                            activeDialog.destructive
                                ? 'destructive'
                                : 'default'
                        "
                        :disabled="
                            activeDialog.kind === 'prompt' && !value.trim()
                        "
                        >{{ activeDialog.confirmText }}</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
