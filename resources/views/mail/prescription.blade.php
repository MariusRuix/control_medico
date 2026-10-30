@php
    $callUs = $clinicPhone ? " al **{$clinicPhone}**" : '';
@endphp
<x-mail::message>
# Hola, {{ $patientName }}

Te compartimos tu receta médica (folio **{{ $folio }}**) en el archivo PDF adjunto.

Sigue las indicaciones tal como están escritas. Si tienes dudas sobre tu tratamiento o presentas alguna reacción, comunícate con nosotros{!! $callUs !!}.

Atentamente,<br>
{{ $doctorName ?? $clinicName }}<br>
{{ $clinicName }}

<x-mail::subcopy>
Este correo contiene información médica confidencial dirigida únicamente a su destinatario.
</x-mail::subcopy>
</x-mail::message>
