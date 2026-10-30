import type { SelectOption } from './patients';

export type BillingService = { id: number; name: string; price: string };

export type BillingOptions = {
    services: BillingService[];
    paymentMethods: SelectOption[];
    cashOpen: boolean;
};

export type ChargeItemSummary = {
    description: string;
    quantity: string;
    unit_price: string;
    discount: string;
    subtotal: string;
};

export type ChargeSummary = {
    id: number;
    created_at: string | null;
    status: 'pendiente' | 'parcial' | 'pagado' | 'cancelado';
    status_label: string;
    total: string;
    paid: string;
    balance: string;
    notes: string | null;
    patient: {
        id: number;
        folio: string;
        full_name: string;
        phone: string | null;
    } | null;
    items: ChargeItemSummary[];
};

export type CashSummary = {
    id: number;
    is_open: boolean;
    opened_at: string;
    closed_at: string | null;
    opened_by: string | null;
    closed_by: string | null;
    opening_amount: string;
    totals: Record<string, string>;
    total_collected: string;
    expected_cash: string;
    counted_cash: string | null;
    difference: string | null;
    notes: string | null;
};

export type PaymentRow = {
    id: number;
    paid_at: string;
    amount: string;
    method: string;
    method_label: string;
    reference: string | null;
    received_by: string | null;
    charge_id: number;
    patient: string | null;
    patient_id: number | null;
    concept: string;
};

/** Paciente preseleccionado en el diálogo de cobro. */
export type ChargePatient = {
    id: number;
    folio: string;
    full_name: string;
    phone: string | null;
    age: number | null;
};
