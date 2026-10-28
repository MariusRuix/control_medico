<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Ban, FileText, Mail, Plus, TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import PrescriptionController from '@/actions/App/Http/Controllers/Clinical/PrescriptionController';
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
import { usePermissions } from '@/composables/usePermissions';
import { formatDateTime } from '@/lib/format';
import { pdf } from '@/routes/prescriptions';
import { edit as clinicSettings } from '@/routes/settings/clinic';
import type { IssuedPrescription, MedicationOption } from '@/types';
import PrescriptionDialog from './PrescriptionDialog.vue';

const props = defineProps<{
    consultationId: number;
    prescriptions: IssuedPrescription[];
    favorites: MedicationOption[];
    criticalAllergies: string[];
    prescriberMissing: string[];
    patientHasEmail: boolean;
}>();

const { can } = usePermissions();
const canPrescribe = can('prescriptions.manage');
const canConfigure = can('settings.manage');

const dialogOpen = ref(false);
const voiding = ref<IssuedPrescription | null>(null);
const voidReason = ref('');
const voidErrors = ref<Record<string, string>>({});
const sending = ref<number | null>(null);

function email(prescription: IssuedPrescription): void {
    router.post(
        PrescriptionController.email.url(prescription.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (sending.value = prescription.id),
            onFinish: () => (sending.value = null),
        },
    );
}

function confirmVoid(): void {
    if (!voiding.value) {
        return;
    }

    router.delete(PrescriptionController.destroy.url(voiding.value.id), {
        data: { reason: voidReason.value },
        preserveScroll: true,
        onSuccess: () => {
            voiding.value = null;
            voidReason.value = '';
        },
        onError: (e) => (voidErrors.value = e),
    });
}

function describe(item: IssuedPrescription['items'][number]): string {
    return [
        item.dose,
        item.route ? `vía ${item.route.toLowerCase()}` : null,
        item.frequency,
        item.duration ? `durante ${item.duration}` : null,
    ]
        .filter(Boolean)
        .join(', ');
}
</script>

<template>
    <section class="space-y-3 rounded-xl border p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-medium">Recetas</h2>
            <Button
                v-if="canPrescribe"
                size="sm"
                variant="outline"
                :disabled="prescriberMissing.length > 0"
                @click="dialogOpen = true"
            >
                <Plus /> Nueva receta
            </Button>
        </div>

        <div
            v-if="canPrescribe && prescriberMissing.length"
            class="flex gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            <p>
                Para emitir recetas faltan: {{ prescriberMissing.join(', ') }}.
                <Link
                    v-if="canConfigure"
                    :href="clinicSettings()"
                    class="font-medium underline underline-offset-4"
                >
                    Completar datos del consultorio
                </Link>
            </p>
        </div>

        <p
            v-if="prescriptions.length === 0"
            class="text-sm text-muted-foreground"
        >
            Sin recetas en esta consulta.
        </p>

        <article
            v-for="p in prescriptions"
            :key="p.id"
            class="space-y-2 rounded-lg border p-3"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm">
                    <span class="font-mono font-semibold">{{ p.folio }}</span>
                    <span class="text-muted-foreground">
                        · {{ formatDateTime(p.issued_at) }}</span
                    >
                </p>
                <div class="flex flex-wrap gap-1">
                    <Button size="sm" variant="outline" as-child>
                        <a :href="pdf.url(p.id)" target="_blank" rel="noopener"
                            ><FileText /> PDF</a
                        >
                    </Button>
                    <Button
                        v-if="canPrescribe"
                        size="sm"
                        variant="ghost"
                        :disabled="!patientHasEmail || sending === p.id"
                        :title="
                            patientHasEmail
                                ? 'Enviar al correo del paciente'
                                : 'El paciente no tiene correo registrado'
                        "
                        @click="email(p)"
                    >
                        <Mail /> {{ sending === p.id ? 'Enviando…' : 'Enviar' }}
                    </Button>
                    <Button
                        v-if="canPrescribe"
                        size="sm"
                        variant="ghost"
                        class="text-destructive"
                        @click="voiding = p"
                    >
                        <Ban /> Anular
                    </Button>
                </div>
            </div>
            <ol class="list-inside list-decimal space-y-0.5 text-sm">
                <li v-for="(item, i) in p.items" :key="i">
                    <span class="font-medium">{{ item.medication_name }}</span>
                    <span class="text-muted-foreground">
                        — {{ describe(item) }}</span
                    >
                </li>
            </ol>
        </article>

        <PrescriptionDialog
            v-model:open="dialogOpen"
            :consultation-id="consultationId"
            :favorites="favorites"
            :critical-allergies="criticalAllergies"
        />

        <Dialog
            :open="voiding !== null"
            @update:open="
                (o) => {
                    if (!o) voiding = null;
                }
            "
        >
            <DialogContent v-if="voiding">
                <DialogHeader>
                    <DialogTitle
                        >¿Anular la receta {{ voiding.folio }}?</DialogTitle
                    >
                    <DialogDescription>
                        La receta deja de ser válida. El folio no se reutiliza y
                        el motivo queda en la bitácora.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <label for="void_reason" class="text-sm font-medium"
                        >Motivo *</label
                    >
                    <Input
                        id="void_reason"
                        v-model="voidReason"
                        maxlength="255"
                        placeholder="Ej. Error en la dosis"
                    />
                    <InputError :message="voidErrors.reason" />
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancelar</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        :disabled="voidReason.trim().length < 3"
                        @click="confirmVoid"
                        >Anular</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
