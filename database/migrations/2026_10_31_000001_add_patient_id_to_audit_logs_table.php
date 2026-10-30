<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paciente al que pertenece cada registro de la bitácora, para poder
 * consultar "quién vio o cambió el expediente de X" sin recorrer cada tabla.
 */
return new class extends Migration
{
    /**
     * Tipo de registro => consulta que obtiene el paciente a partir de audit_logs.auditable_id.
     *
     * @var array<string, string>
     */
    private const array SOURCES = [
        'App\\Models\\Appointment' => 'select patient_id from appointments where appointments.id = audit_logs.auditable_id',
        'App\\Models\\Consultation' => 'select patient_id from consultations where consultations.id = audit_logs.auditable_id',
        'App\\Models\\MedicalFile' => 'select patient_id from medical_files where medical_files.id = audit_logs.auditable_id',
        'App\\Models\\Charge' => 'select patient_id from charges where charges.id = audit_logs.auditable_id',
        'App\\Models\\PatientHistory' => 'select patient_id from patient_histories where patient_histories.id = audit_logs.auditable_id',
        'App\\Models\\Prescription' => 'select c.patient_id from prescriptions p join consultations c on c.id = p.consultation_id where p.id = audit_logs.auditable_id',
        'App\\Models\\ConsultationAddendum' => 'select c.patient_id from consultation_addenda a join consultations c on c.id = a.consultation_id where a.id = audit_logs.auditable_id',
        'App\\Models\\Payment' => 'select ch.patient_id from payments py join charges ch on ch.id = py.charge_id where py.id = audit_logs.auditable_id',
    ];

    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('patient_id')->nullable()->after('auditable_id')->constrained()->nullOnDelete();
            $table->index(['patient_id', 'created_at']);
        });

        // Completa los registros anteriores.
        DB::table('audit_logs')
            ->where('auditable_type', 'App\\Models\\Patient')
            ->update(['patient_id' => DB::raw('auditable_id')]);

        foreach (self::SOURCES as $type => $query) {
            DB::table('audit_logs')
                ->where('auditable_type', $type)
                ->whereNull('patient_id')
                ->update(['patient_id' => DB::raw("({$query})")]);
        }
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['patient_id', 'created_at']);
            $table->dropConstrainedForeignId('patient_id');
        });
    }
};
