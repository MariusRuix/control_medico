@php
    $withDoctor = $doctorName ? " con **{$doctorName}**" : '';
    $callUs = $clinicPhone ? " al **{$clinicPhone}**" : '';
@endphp
<x-mail::message>
# Hola, {{ $patientName }}

Te recordamos tu cita{!! $withDoctor !!}:

<x-mail::panel>
**{{ ucfirst($date) }}** a las **{{ $time }} h**
@if ($service)

{{ $service }}
@endif
@if ($clinicAddress)

{{ $clinicAddress }}
@endif
</x-mail::panel>

Te pedimos llegar 10 minutos antes. Si no puedes asistir o necesitas cambiar el horario, avísanos{!! $callUs !!} para ofrecer el espacio a otro paciente.

Atentamente,<br>
{{ $clinicName }}

<x-mail::subcopy>
Recibes este correo porque tienes una cita agendada en {{ $clinicName }}.
</x-mail::subcopy>
</x-mail::message>
