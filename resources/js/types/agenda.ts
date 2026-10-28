export type CalendarView = 'day' | 'week' | 'month';

export type DayKey = 'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun';

export type WorkingHours = Record<DayKey, [string, string][]>;

export type AppointmentStatusDefinition = {
    value: string;
    label: string;
    color: string;
    transitions: string[];
};

export type AgendaService = {
    id: number;
    name: string;
    duration_minutes: number | null;
    color: string | null;
};

export type CalendarAppointment = {
    id: number;
    /** Hora local del consultorio: "YYYY-MM-DDTHH:mm:ss" */
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
    status: string;
    reason: string | null;
    notes: string | null;
    cancel_reason: string | null;
    patient: {
        id: number;
        folio: string;
        full_name: string;
        phone: string | null;
    } | null;
    service: { id: number; name: string; color: string | null } | null;
    consultation: { id: number; is_signed: boolean } | null;
};

export type CalendarBlock = {
    id: number;
    starts_at: string;
    ends_at: string;
    reason: string | null;
};

export type PatientSearchResult = {
    id: number;
    folio: string;
    full_name: string;
    phone: string | null;
    age: number | null;
};
