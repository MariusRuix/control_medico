<script setup lang="ts">
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

/** Confirmación que pide un motivo (queda en la bitácora). */
const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    title: string;
    description?: string;
    confirmLabel?: string;
    placeholder?: string;
    error?: string;
    processing?: boolean;
}>();

const emit = defineEmits<{ confirm: [reason: string] }>();

const reason = ref('');

watch(open, (isOpen) => {
    if (isOpen) {
        reason.value = '';
    }
});

function confirm(): void {
    if (reason.value.trim().length >= 3) {
        emit('confirm', reason.value.trim());
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <form class="space-y-4" @submit.prevent="confirm">
                <DialogHeader>
                    <DialogTitle>{{ props.title }}</DialogTitle>
                    <DialogDescription v-if="description">{{
                        description
                    }}</DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <label for="reason_input" class="text-sm font-medium"
                        >Motivo *</label
                    >
                    <Input
                        id="reason_input"
                        v-model="reason"
                        maxlength="255"
                        :placeholder="placeholder"
                        autofocus
                    />
                    <InputError :message="error" />
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary"
                            >Volver</Button
                        >
                    </DialogClose>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing || reason.trim().length < 3"
                    >
                        {{ confirmLabel ?? 'Confirmar' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
