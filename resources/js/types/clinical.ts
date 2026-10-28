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
};
