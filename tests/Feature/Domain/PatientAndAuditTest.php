<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PatientAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_curp_is_encrypted_at_rest_and_searchable_by_hash(): void
    {
        $patient = Patient::query()->create([
            'folio' => 'P-000001',
            'first_name' => 'Ana',
            'last_name' => 'López',
            'curp' => 'lopa900101mdfpnn09',
        ]);

        $raw = DB::table('patients')->where('id', $patient->id)->value('curp');

        $this->assertNotSame('lopa900101mdfpnn09', $raw);
        $this->assertSame(Patient::hashCurp('LOPA900101MDFPNN09'), $patient->fresh()?->curp_hash);
    }

    public function test_bmi_is_calculated_on_save(): void
    {
        $patient = Patient::query()->create(['folio' => 'P-000002', 'first_name' => 'Luis', 'last_name' => 'Pérez']);

        $consultation = Consultation::query()->create([
            'patient_id' => $patient->id,
            'consulted_at' => now(),
            'weight_kg' => 80,
            'height_cm' => 175,
        ]);

        $this->assertSame('26.12', $consultation->fresh()?->bmi);
    }

    public function test_audit_log_never_stores_encrypted_values(): void
    {
        $this->actingAs(User::factory()->create());

        $patient = Patient::query()->create([
            'folio' => 'P-000003',
            'first_name' => 'Eva',
            'last_name' => 'Ruiz',
            'curp' => 'RUXE800101MDFZXV01',
        ]);

        $log = AuditLog::query()->where('action', 'created')->where('auditable_id', $patient->id)->firstOrFail();

        $this->assertSame(AuditLogger::MASK, $log->new_values['curp'] ?? null);
        $this->assertSame(AuditLogger::MASK, $log->new_values['curp_hash'] ?? null);
        $this->assertSame('Eva', $log->new_values['first_name'] ?? null);
    }
}
