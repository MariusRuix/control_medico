<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FilePlus2 } from '@lucide/vue';
import { ref } from 'vue';
import ConsultationAddendumController from '@/actions/App/Http/Controllers/Clinical/ConsultationAddendumController';
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
import { formatDateTime } from '@/lib/format';
import type { Addendum } from '@/types';

const props = defineProps<{
    consultationId: number;
    addenda: Addendum[];
    canAdd: boolean;
}>();

const writing = ref(false);
const confirmOpen = ref(false);
const form = useForm({ body: '' });

function submit(): void {
    form.post(ConsultationAddendumController.store.url(props.consultationId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            writing.value = false;
            confirmOpen.value = false;
        },
        onError: () => (confirmOpen.value = false),
    });
}
</script>

<template>
    <section v-if="addenda.length || canAdd" class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2
                class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Notas aclaratorias
            </h2>
            <Button
                v-if="canAdd && !writing"
                size="sm"
                variant="outline"
                class="print:hidden"
                @click="writing = true"
            >
                <FilePlus2 /> Agregar nota aclaratoria
            </Button>
        </div>

        <ol v-if="addenda.length" class="space-y-3">
            <li
                v-for="a in addenda"
                :key="a.id"
                class="rounded-lg border-l-4 border-amber-400 bg-amber-50/60 p-3 dark:bg-amber-500/10"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    {{ formatDateTime(a.created_at)
                    }}<template v-if="a.author"> · {{ a.author }}</template>
                </p>
                <p class="mt-1 text-sm leading-relaxed whitespace-pre-line">
                    {{ a.body }}
                </p>
            </li>
        </ol>
        <p
            v-else-if="!writing"
            class="text-sm text-muted-foreground print:hidden"
        >
            Para corregir o complementar esta nota firmada, agrega una nota
            aclaratoria. La nota original no se modifica.
        </p>

        <form
            v-if="writing"
            class="space-y-2 rounded-lg border p-3 print:hidden"
            @submit.prevent="confirmOpen = true"
        >
            <label for="addendum_body" class="text-sm font-medium"
                >Nueva nota aclaratoria</label
            >
            <textarea
                id="addendum_body"
                v-model="form.body"
                rows="4"
                maxlength="5000"
                autofocus
                placeholder="Ej. Se corrige la dosis indicada en el plan: amoxicilina 500 mg cada 8 horas (no cada 12)."
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
            />
            <InputError :message="form.errors.body" />
            <div class="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="writing = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    size="sm"
                    :disabled="form.body.trim().length < 5"
                    >Agregar</Button
                >
            </div>
        </form>

        <Dialog v-model:open="confirmOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>¿Agregar la nota aclaratoria?</DialogTitle>
                    <DialogDescription>
                        Quedará con tu nombre, la fecha y hora, y no podrá
                        modificarse ni eliminarse.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child
                        ><Button variant="secondary"
                            >Revisar</Button
                        ></DialogClose
                    >
                    <Button :disabled="form.processing" @click="submit"
                        >Agregar</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
