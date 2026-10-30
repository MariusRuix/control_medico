<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CircleCheck, CircleAlert, Info } from '@lucide/vue';
import { computed } from 'vue';
import ReminderSettingsController from '@/actions/App/Http/Controllers/Settings/ReminderSettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/settings/reminders';

const props = defineProps<{
    enabled: boolean;
    hoursBefore: number;
    channel: 'email' | 'whatsapp' | 'both';
    hoursOptions: number[];
    whatsappConfigured: boolean;
    mailer: string;
    pending: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Recordatorios', href: edit() }],
    },
});

const form = useForm({
    enabled: props.enabled,
    hours_before: props.hoursBefore,
    channel: props.channel,
});

const CHANNELS = computed(() => [
    { value: 'email', label: 'Correo electrónico', disabled: false },
    {
        value: 'whatsapp',
        label: 'WhatsApp',
        disabled: !props.whatsappConfigured,
    },
    {
        value: 'both',
        label: 'Correo y WhatsApp',
        disabled: !props.whatsappConfigured,
    },
]);

const mailIsLog = computed(() => ['log', 'array'].includes(props.mailer));

function hoursLabel(h: number): string {
    return h < 24
        ? `${h} horas antes`
        : h === 24
          ? '1 día antes'
          : `${h / 24} días antes`;
}

function submit(): void {
    form.put(ReminderSettingsController.update.url(), { preserveScroll: true });
}

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head title="Recordatorios" />

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Recordatorios de citas"
            description="Se envían automáticamente a los pacientes con cita agendada o confirmada"
        />

        <form class="max-w-xl space-y-6" @submit.prevent="submit">
            <label class="flex items-start gap-3 rounded-lg border p-4">
                <input
                    v-model="form.enabled"
                    type="checkbox"
                    class="mt-0.5 size-4"
                />
                <span>
                    <span class="block text-sm font-medium"
                        >Enviar recordatorios automáticos</span
                    >
                    <span class="block text-xs text-muted-foreground">
                        Cada 15 minutos se revisan las próximas citas que aún no
                        tienen recordatorio.
                    </span>
                </span>
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <label for="hours_before" class="text-sm font-medium"
                        >Anticipación</label
                    >
                    <select
                        id="hours_before"
                        v-model.number="form.hours_before"
                        :class="selectClass"
                    >
                        <option v-for="h in hoursOptions" :key="h" :value="h">
                            {{ hoursLabel(h) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.hours_before" />
                </div>
                <div class="grid gap-2">
                    <label for="channel" class="text-sm font-medium"
                        >Canal</label
                    >
                    <select
                        id="channel"
                        v-model="form.channel"
                        :class="selectClass"
                    >
                        <option
                            v-for="c in CHANNELS"
                            :key="c.value"
                            :value="c.value"
                            :disabled="c.disabled"
                        >
                            {{ c.label
                            }}{{ c.disabled ? ' (no configurado)' : '' }}
                        </option>
                    </select>
                    <InputError :message="form.errors.channel" />
                </div>
            </div>

            <ul class="space-y-2 text-sm">
                <li class="flex items-start gap-2">
                    <CircleAlert
                        v-if="mailIsLog"
                        class="mt-0.5 size-4 shrink-0 text-amber-600"
                    />
                    <CircleCheck
                        v-else
                        class="mt-0.5 size-4 shrink-0 text-emerald-600"
                    />
                    <span>
                        Correo:
                        <template v-if="mailIsLog">
                            <code class="rounded bg-muted px-1"
                                >MAIL_MAILER={{ mailer }}</code
                            >, los correos solo se escriben en el log. Configura
                            un servidor SMTP en
                            <code class="rounded bg-muted px-1">.env</code> para
                            enviarlos de verdad.
                        </template>
                        <template v-else>configurado ({{ mailer }}).</template>
                    </span>
                </li>
                <li class="flex items-start gap-2">
                    <CircleCheck
                        v-if="whatsappConfigured"
                        class="mt-0.5 size-4 shrink-0 text-emerald-600"
                    />
                    <Info
                        v-else
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    />
                    <span>
                        WhatsApp automático:
                        <template v-if="whatsappConfigured"
                            >configurado (WhatsApp Business Cloud
                            API).</template
                        >
                        <template v-else>
                            no configurado. Requiere una cuenta de WhatsApp
                            Business API y una plantilla aprobada. Mientras
                            tanto, en la agenda puedes usar el botón
                            <strong>WhatsApp</strong> de cada cita para enviarlo
                            desde tu teléfono.
                        </template>
                    </span>
                </li>
                <li
                    v-if="form.enabled && enabled"
                    class="flex items-start gap-2"
                >
                    <Info
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    />
                    <span
                        >{{ pending }} cita(s) recibirán recordatorio en la
                        próxima revisión.</span
                    >
                </li>
            </ul>

            <Button type="submit" :disabled="form.processing">Guardar</Button>
        </form>
    </div>
</template>
