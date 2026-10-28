<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import ScheduleBlockController from '@/actions/App/Http/Controllers/Agenda/ScheduleBlockController';
import InputError from '@/components/InputError.vue';
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
import { Label } from '@/components/ui/label';
import {
    addDays,
    capitalize,
    formatters,
    parseLocal,
    timeOf,
} from '@/lib/agenda';
import type { CalendarBlock } from '@/types';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    /** Bloqueo existente (vista de detalle) o null para crear uno nuevo. */
    block: CalendarBlock | null;
    date: string;
    canManage: boolean;
}>();

const form = useForm({ starts_at: '', ends_at: '', reason: '' });

watch(open, (isOpen) => {
    if (isOpen && !props.block) {
        form.clearErrors();
        form.starts_at = `${props.date}T00:00`;
        form.ends_at = `${addDays(props.date, 1)}T00:00`;
        form.reason = '';
    }
});

function submit(): void {
    form.post(ScheduleBlockController.store.url(), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}

function remove(): void {
    if (!props.block) {
        return;
    }

    router.delete(ScheduleBlockController.destroy.url(props.block.id), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}

function describe(value: string): string {
    return `${capitalize(formatters.long.format(parseLocal(value)))}, ${timeOf(value)}`;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <template v-if="block">
                <DialogHeader>
                    <DialogTitle>{{
                        block.reason ?? 'Horario bloqueado'
                    }}</DialogTitle>
                    <DialogDescription>
                        Del {{ describe(block.starts_at) }}<br />al
                        {{ describe(block.ends_at) }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter v-if="canManage" class="gap-2">
                    <Button variant="secondary" @click="open = false"
                        >Cerrar</Button
                    >
                    <Button variant="destructive" @click="remove"
                        >Quitar bloqueo</Button
                    >
                </DialogFooter>
            </template>

            <form v-else class="space-y-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Bloquear horario</DialogTitle>
                    <DialogDescription>
                        Días festivos, vacaciones o ausencias. No se podrán
                        agendar citas en ese periodo.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="block_starts_at">Desde *</Label>
                        <Input
                            id="block_starts_at"
                            v-model="form.starts_at"
                            type="datetime-local"
                            required
                        />
                        <InputError :message="form.errors.starts_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="block_ends_at">Hasta *</Label>
                        <Input
                            id="block_ends_at"
                            v-model="form.ends_at"
                            type="datetime-local"
                            required
                        />
                        <InputError :message="form.errors.ends_at" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="block_reason">Motivo</Label>
                    <Input
                        id="block_reason"
                        v-model="form.reason"
                        maxlength="150"
                        placeholder="Ej. Congreso médico"
                    />
                    <InputError :message="form.errors.reason" />
                </div>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Bloquear</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
