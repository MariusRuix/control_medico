<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import ClinicSettingsController from '@/actions/App/Http/Controllers/Settings/ClinicSettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/settings/clinic';

type Images = {
    logo: string | null;
    signature: string | null;
    seal: string | null;
};

defineProps<{
    clinic: Record<string, string | null>;
    doctor: Record<string, string | null>;
    images: Images;
    missing: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Consultorio', href: edit() }],
    },
});

const imageFields: { key: keyof Images; label: string; hint: string }[] = [
    {
        key: 'signature',
        label: 'Firma',
        hint: 'PNG con fondo transparente, firma en tinta oscura.',
    },
    { key: 'seal', label: 'Sello', hint: 'Opcional.' },
    {
        key: 'logo',
        label: 'Logotipo',
        hint: 'Aparece en el encabezado de la receta.',
    },
];
</script>

<template>
    <Head title="Datos del consultorio" />

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Datos del consultorio"
            description="Aparecen en recetas y documentos impresos"
        />

        <div
            v-if="missing.length"
            class="flex gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            Para emitir recetas faltan: {{ missing.join(', ') }}.
        </div>

        <Form
            v-bind="ClinicSettingsController.update.form()"
            :reset-on-success="['logo', 'signature', 'seal', 'remove[]']"
            :options="{ preserveScroll: true }"
            class="space-y-8"
            v-slot="{ errors, processing }"
        >
            <section class="space-y-4">
                <h3 class="text-sm font-medium">Médico</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="doctor_name"
                            >Nombre como aparece en la receta *</Label
                        >
                        <Input
                            id="doctor_name"
                            name="doctor[name]"
                            :default-value="doctor.name ?? ''"
                            placeholder="Dra. Ana Pérez López"
                            required
                        />
                        <InputError :message="errors['doctor.name']" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="professional_license"
                            >Cédula profesional</Label
                        >
                        <Input
                            id="professional_license"
                            name="doctor[professional_license]"
                            inputmode="numeric"
                            maxlength="8"
                            :default-value="doctor.professional_license ?? ''"
                        />
                        <InputError
                            :message="errors['doctor.professional_license']"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="university"
                            >Institución que expidió el título</Label
                        >
                        <Input
                            id="university"
                            name="doctor[university]"
                            :default-value="doctor.university ?? ''"
                            placeholder="UNAM"
                        />
                        <InputError :message="errors['doctor.university']" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="specialty">Especialidad</Label>
                        <Input
                            id="specialty"
                            name="doctor[specialty]"
                            :default-value="doctor.specialty ?? ''"
                        />
                        <InputError :message="errors['doctor.specialty']" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="specialty_license"
                            >Cédula de especialidad</Label
                        >
                        <Input
                            id="specialty_license"
                            name="doctor[specialty_license]"
                            inputmode="numeric"
                            maxlength="8"
                            :default-value="doctor.specialty_license ?? ''"
                        />
                        <InputError
                            :message="errors['doctor.specialty_license']"
                        />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="text-sm font-medium">Consultorio</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="clinic_name">Nombre *</Label>
                        <Input
                            id="clinic_name"
                            name="clinic[name]"
                            :default-value="clinic.name ?? ''"
                            required
                        />
                        <InputError :message="errors['clinic.name']" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="clinic_address">Domicilio</Label>
                        <Input
                            id="clinic_address"
                            name="clinic[address]"
                            :default-value="clinic.address ?? ''"
                            placeholder="Calle, número, colonia, municipio, estado, C.P."
                        />
                        <InputError :message="errors['clinic.address']" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="clinic_phone">Teléfono</Label>
                        <Input
                            id="clinic_phone"
                            name="clinic[phone]"
                            type="tel"
                            :default-value="clinic.phone ?? ''"
                        />
                        <InputError :message="errors['clinic.phone']" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="clinic_email">Correo</Label>
                        <Input
                            id="clinic_email"
                            name="clinic[email]"
                            type="email"
                            :default-value="clinic.email ?? ''"
                        />
                        <InputError :message="errors['clinic.email']" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="text-sm font-medium">Imágenes</h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div
                        v-for="field in imageFields"
                        :key="field.key"
                        class="space-y-2 rounded-lg border p-3"
                    >
                        <Label :for="field.key">{{ field.label }}</Label>
                        <div
                            class="flex h-20 items-center justify-center rounded-md bg-muted/50"
                        >
                            <img
                                v-if="images[field.key]"
                                :src="images[field.key]!"
                                :alt="field.label"
                                class="max-h-16 max-w-full object-contain"
                            />
                            <span v-else class="text-xs text-muted-foreground"
                                >Sin imagen</span
                            >
                        </div>
                        <input
                            :id="field.key"
                            :name="field.key"
                            type="file"
                            accept="image/png,image/jpeg"
                            class="block w-full text-xs file:mr-2 file:rounded-md file:border-0 file:bg-secondary file:px-2 file:py-1 file:text-xs"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ field.hint }}
                        </p>
                        <label
                            v-if="images[field.key]"
                            class="flex items-center gap-2 text-xs"
                        >
                            <input
                                type="checkbox"
                                name="remove[]"
                                :value="field.key"
                                class="size-3.5"
                            />
                            Quitar
                        </label>
                        <InputError :message="errors[field.key]" />
                    </div>
                </div>
            </section>

            <Button type="submit" :disabled="processing">Guardar</Button>
        </Form>
    </div>
</template>
