export type SelectOption = {
    value: string;
    label: string;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};

export type PatientListItem = {
    id: number;
    folio: string;
    full_name: string;
    age: number | null;
    sex: string | null;
    phone: string | null;
    email: string | null;
    has_critical_allergies: boolean;
};

export type Patient = {
    id: number;
    folio: string;
    first_name: string;
    last_name: string;
    second_last_name: string | null;
    full_name: string;
    birth_date: string | null;
    age: number | null;
    sex: string | null;
    sex_label: string | null;
    curp: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    occupation: string | null;
    blood_type: string | null;
    emergency_name: string | null;
    emergency_phone: string | null;
    emergency_relationship: string | null;
    notes: string | null;
    created_at: string | null;
};

export type PatientHistory = {
    id: number;
    type: string;
    type_label: string;
    description: string;
    relative: string | null;
    is_critical: boolean;
    created_at: string | null;
};

export type ConsultationSummary = {
    id: number;
    consulted_at: string;
    reason: string | null;
    weight_kg: string | null;
    bmi: string | null;
    blood_pressure: string | null;
    is_signed: boolean;
    diagnoses: { code: string; description: string; type: string }[];
};

export type AppointmentSummary = {
    id: number;
    starts_at: string;
    service: string | null;
    reason: string | null;
    status: string;
    status_label: string;
    status_color: string;
};
