<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\Reminders\PhoneNumber;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    private Patient $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-28 10:00:00'));
        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
        Mail::fake();

        $this->ana = Patient::query()->create([
            'folio' => 'P-000001', 'first_name' => 'Ana', 'last_name' => 'López',
            'email' => 'ana@example.com', 'phone' => '55 1234 5678',
        ]);
    }

    private function appointment(Patient $patient, string $startsAt, AppointmentStatus $status = AppointmentStatus::Scheduled): Appointment
    {
        $start = CarbonImmutable::parse($startsAt);

        return Appointment::query()->create([
            'patient_id' => $patient->id,
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(30),
            'status' => $status,
        ]);
    }

    private function enable(string $channel = 'email', int $hours = 24): void
    {
        app(SettingsService::class)->setMany([
            'notifications.reminders_enabled' => true,
            'notifications.reminder_hours_before' => $hours,
            'notifications.reminder_channel' => $channel,
        ]);
    }

    public function test_nothing_is_sent_while_disabled(): void
    {
        $this->appointment($this->ana, '2026-10-28 18:00');

        $this->artisan('appointments:send-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_only_appointments_inside_the_window_get_a_single_email(): void
    {
        $this->enable();
        $due = $this->appointment($this->ana, '2026-10-29 09:00');
        $later = $this->appointment($this->ana, '2026-10-30 09:00');
        $cancelled = $this->appointment($this->ana, '2026-10-28 16:00', AppointmentStatus::Cancelled);

        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->artisan('appointments:send-reminders')->assertSuccessful();

        Mail::assertSent(AppointmentReminderMail::class, 1);
        Mail::assertSent(AppointmentReminderMail::class, fn (AppointmentReminderMail $m): bool => $m->hasTo('ana@example.com')
            && $m->appointment->is($due));
        $this->assertNotNull($due->fresh()?->reminder_sent_at);
        $this->assertNull($later->fresh()?->reminder_sent_at);
        $this->assertNull($cancelled->fresh()?->reminder_sent_at);
    }

    public function test_patients_without_email_are_skipped_once(): void
    {
        $this->enable();
        $luis = Patient::query()->create(['folio' => 'P-000002', 'first_name' => 'Luis', 'last_name' => 'Soto']);
        $appointment = $this->appointment($luis, '2026-10-28 18:00');

        $this->artisan('appointments:send-reminders')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNotNull($appointment->fresh()?->reminder_sent_at);
    }

    public function test_whatsapp_template_is_sent_with_normalized_phone(): void
    {
        config(['services.whatsapp.token' => 'test-token', 'services.whatsapp.phone_number_id' => '123']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $this->enable('both');
        $this->appointment($this->ana, '2026-10-28 18:30');

        $this->artisan('appointments:send-reminders')->assertSuccessful();

        Mail::assertSent(AppointmentReminderMail::class, 1);
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/123/messages')
            && $r['to'] === '525512345678'
            && $r['template']['name'] === 'recordatorio_cita'
            && $r['template']['components'][0]['parameters'][0]['text'] === 'Ana'
            && $r['template']['components'][0]['parameters'][2]['text'] === '18:30'
            && $r->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_reception_can_send_a_reminder_manually(): void
    {
        $this->enable();
        $recepcion = User::factory()->create()->assignRole(Role::Recepcion->value);
        $appointment = $this->appointment($this->ana, '2026-11-05 10:00');

        $this->actingAs($recepcion)
            ->post(route('appointments.reminder', $appointment))
            ->assertSessionHasNoErrors();

        Mail::assertSent(AppointmentReminderMail::class, 1);
        $this->assertNotNull($appointment->fresh()?->reminder_sent_at);

        $luis = Patient::query()->create(['folio' => 'P-000002', 'first_name' => 'Luis', 'last_name' => 'Soto']);
        $this->post(route('appointments.reminder', $this->appointment($luis, '2026-11-05 11:00')))
            ->assertSessionHasErrors(['reminder' => 'El paciente no tiene correo registrado.']);
    }

    public function test_settings_require_whatsapp_configuration_for_whatsapp_channel(): void
    {
        $medico = User::factory()->create()->assignRole(Role::Medico->value);

        $this->actingAs($medico)
            ->put(route('settings.reminders.update'), ['enabled' => true, 'hours_before' => 24, 'channel' => 'whatsapp'])
            ->assertSessionHasErrors('channel');

        $this->put(route('settings.reminders.update'), ['enabled' => true, 'hours_before' => 12, 'channel' => 'email'])
            ->assertSessionHasNoErrors();

        $settings = app(SettingsService::class);
        $this->assertTrue($settings->get('notifications.reminders_enabled'));
        $this->assertSame(12, $settings->get('notifications.reminder_hours_before'));
    }

    public function test_reminder_email_renders_in_spanish(): void
    {
        app(SettingsService::class)->setMany(['clinic.phone' => '55 5555 1234', 'doctor.name' => 'Dra. Laura Méndez']);
        $appointment = $this->appointment($this->ana, '2026-10-29 09:30');

        $html = (new AppointmentReminderMail($appointment))->render();

        $this->assertStringContainsString('Hola, Ana', $html);
        $this->assertStringContainsString('Jueves 29 de octubre de 2026', $html);
        $this->assertStringContainsString('09:30 h', $html);
        $this->assertStringContainsString('55 5555 1234', $html);
        $this->assertStringContainsString('Todos los derechos reservados.', $html);
    }

    public function test_phone_numbers_are_normalized_for_whatsapp(): void
    {
        $this->assertSame('525512345678', PhoneNumber::toInternational('55 1234 5678'));
        $this->assertSame('525512345678', PhoneNumber::toInternational('+52 1 55 1234 5678'));
        $this->assertSame('14155552671', PhoneNumber::toInternational('+1 (415) 555-2671'));
        $this->assertNull(PhoneNumber::toInternational('123'));
        $this->assertNull(PhoneNumber::toInternational(null));
    }
}
