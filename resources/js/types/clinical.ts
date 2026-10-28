import type { MedicalFileItem } from './files';
import type { PatientHistory, SelectOption } from './patients';

export type DiagnosisEntry = {
    cie10_code_id: number;
    code: string;
    description: string;
    type: 'principal' | 'secundario';
    notes: string | null;
};

export type VitalSigns = {
    weight_kg: string | number | null;
    height_cm: string | number | null;
    systolic_bp: number | null;
    diastolic_bp: number | null;
    heart_rate: number | null;
    respiratory_rate: number | null;
    temperature_c: string | number | null;
    oxygen_saturation: number | null;
    glucose_mg_dl: number | null;
};

export type SoapNote = {
    reason: string | null;
    subjective: string | null;
    objective: string | null;
    assessment: string | null;
    plan: string | null;
};

export type Consultation = VitalSigns &
    SoapNote & {
        id: number;
        consulted_at: string;
        signed_at: string | null;
        author: string | null;
        note_template_id: number | null;
        bmi: string | null;
        appointment: {
            id: number;
            starts_at: string;
            service: string | null;
        } | null;
        diagnoses: DiagnosisEntry[];
    };

export type ConsultationPatient = {
    id: number;
    folio: string;
    full_name: string;
    age: number | null;
    sex_label: string | null;
    blood_type: string | null;
    is_archived: boolean;
    has_email: boolean;
};

export type PreviousConsultation = {
    id: number;
    consulted_at: string;
    reason: string | null;
    assessment: string | null;
    plan: string | null;
    diagnoses: { code: string; description: string; type: string }[];
};

export type VitalTrendPoint = {
    date: string;
    weight_kg: string | null;
    bmi: string | null;
    systolic_bp: number | null;
    diastolic_bp: number | null;
    heart_rate: number | null;
    glucose_mg_dl: number | null;
};

export type NoteTemplate = {
    id: number;
    name: string;
    structure: Partial<Record<keyof SoapNote, string>>;
};

export type Cie10Result = { id: number; code: string; description: string };

export type ConsultationPageProps = {
    consultation: Consultation;
    patient: ConsultationPatient;
    criticalAllergies: string[];
    histories: PatientHistory[];
    historyTypes: SelectOption[];
    previous: PreviousConsultation[];
    trend: VitalTrendPoint[];
    templates: NoteTemplate[];
    prescriptions: IssuedPrescription[];
    favorites: MedicationOption[];
    prescriberMissing: string[];
    files: MedicalFileItem[];
    fileTypes: SelectOption[];
};

export type MedicationOption = {
    id: number;
    name: string;
    generic_name: string;
    route: string | null;
    is_favorite: boolean;
    default_dose: string | null;
    default_frequency: string | null;
    default_duration: string | null;
    default_instructions: string | null;
};

export type PrescriptionItemInput = {
    medication_id: number | null;
    medication_name: string;
    dose: string;
    route: string;
    frequency: string;
    duration: string;
    quantity: string;
    instructions: string;
    save_to_vademecum: boolean;
};

export type IssuedPrescription = {
    id: number;
    folio: string;
    issued_at: string | null;
    general_instructions: string | null;
    items: {
        medication_name: string;
        dose: string | null;
        route: string | null;
        frequency: string | null;
        duration: string | null;
        quantity: string | null;
        instructions: string | null;
    }[];
};
