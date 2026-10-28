export type MedicalFileItem = {
    id: number;
    type: string;
    type_label: string;
    title: string;
    original_name: string;
    mime_type: string;
    is_image: boolean;
    is_pdf: boolean;
    size_bytes: number;
    study_date: string | null;
    notes: string | null;
    consultation_id: number | null;
    uploaded_by: string | null;
    created_at: string | null;
};
