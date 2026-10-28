@php
    /** @var \App\Models\Prescription $prescription */
    /** @var \App\Models\Consultation $consultation */
    /** @var \App\Models\Patient $patient */
    $clinic = $profile['clinic'];
    $doctor = $profile['doctor'];
    $issued = $prescription->issued_at ?? now();
    $vitals = array_filter([
        'Peso' => $consultation->weight_kg ? $consultation->weight_kg.' kg' : null,
        'Talla' => $consultation->height_cm ? $consultation->height_cm.' cm' : null,
        'TA' => $consultation->systolic_bp ? $consultation->systolic_bp.'/'.$consultation->diastolic_bp.' mmHg' : null,
        'Temp.' => $consultation->temperature_c ? $consultation->temperature_c.' °C' : null,
    ]);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Receta {{ $prescription->folio }}</title>
    <style>
        @page { margin: 28px 36px 60px 36px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 10.5px; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .clinic-name { font-size: 15px; font-weight: bold; color: #111827; }
        .doctor-name { font-size: 13px; font-weight: bold; margin-top: 2px; }
        .muted { color: #6b7280; }
        .small { font-size: 9px; }
        .folio { text-align: right; }
        .folio .number { font-size: 13px; font-weight: bold; }
        .rule { border-top: 2px solid #111827; margin: 10px 0 8px; }
        .box { border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 10px; }
        .patient td { padding: 2px 0; }
        .label { color: #6b7280; font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; }
        .alert { margin-top: 8px; padding: 6px 10px; border: 1px solid #fca5a5; background: #fef2f2; color: #991b1b; border-radius: 6px; }
        .rx { font-size: 22px; font-weight: bold; font-family: 'DejaVu Serif', serif; margin: 14px 0 6px; }
        .items td { padding: 7px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .items .n { width: 18px; font-weight: bold; color: #6b7280; }
        .med { font-size: 11.5px; font-weight: bold; }
        .instructions { margin-top: 12px; }
        .signature { margin-top: 40px; text-align: center; }
        .signature img { max-height: 70px; max-width: 220px; }
        .signature .line { border-top: 1px solid #111827; width: 260px; margin: 4px auto 0; padding-top: 4px; }
        .footer { position: fixed; bottom: -40px; left: 0; right: 0; font-size: 8px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 4px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            @if($logo)
                <td style="width: 70px;"><img src="{{ $logo }}" style="max-width: 60px; max-height: 60px;" alt=""></td>
            @endif
            <td>
                <div class="clinic-name">{{ $clinic['name'] ?? config('app.name') }}</div>
                <div class="doctor-name">{{ $doctor['name'] ?? '' }}</div>
                @if(!empty($doctor['specialty']))
                    <div>{{ $doctor['specialty'] }}@if(!empty($doctor['specialty_license'])) · Céd. esp. {{ $doctor['specialty_license'] }}@endif</div>
                @endif
                <div class="muted">
                    @if(!empty($doctor['university'])){{ $doctor['university'] }} · @endif
                    Céd. prof. {{ $doctor['professional_license'] ?? '—' }}
                </div>
            </td>
            <td class="folio" style="width: 170px;">
                <div class="label">Folio</div>
                <div class="number">{{ $prescription->folio }}</div>
                <div class="label" style="margin-top: 6px;">Fecha</div>
                <div>{{ $issued->translatedFormat('d \d\e F \d\e Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <div class="box">
        <table class="patient">
            <tr>
                <td style="width: 55%;">
                    <div class="label">Paciente</div>
                    <strong>{{ $patient->full_name }}</strong>
                </td>
                <td>
                    <div class="label">Edad</div>
                    {{ $patient->age !== null ? $patient->age.' años' : '—' }}
                </td>
                <td>
                    <div class="label">Sexo</div>
                    {{ $patient->sex?->label() ?? '—' }}
                </td>
                <td>
                    <div class="label">Expediente</div>
                    {{ $patient->folio }}
                </td>
            </tr>
            @if($vitals)
                <tr>
                    <td colspan="4" style="padding-top: 6px;">
                        @foreach($vitals as $label => $value)
                            <span class="label">{{ $label }}</span> {{ $value }}@if(!$loop->last) &nbsp;·&nbsp; @endif
                        @endforeach
                    </td>
                </tr>
            @endif
        </table>
    </div>

    @if(count($allergies))
        <div class="alert"><strong>Alergias:</strong> {{ implode(' · ', $allergies) }}</div>
    @endif

    <div class="rx">Rx</div>

    <table class="items">
        @foreach($prescription->items as $item)
            <tr>
                <td class="n">{{ $loop->iteration }}.</td>
                <td>
                    <div class="med">{{ $item->medication_name }}</div>
                    <div>
                        {{ collect([$item->dose, $item->route ? 'vía '.mb_strtolower($item->route) : null, $item->frequency, $item->duration ? 'durante '.$item->duration : null])->filter()->implode(', ') }}
                    </div>
                    @if($item->instructions)
                        <div class="muted">{{ $item->instructions }}</div>
                    @endif
                </td>
                <td style="width: 90px; text-align: right;">
                    @if($item->quantity)
                        <div class="label">Cantidad</div>
                        {{ $item->quantity }}
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    @if($prescription->general_instructions)
        <div class="instructions">
            <div class="label">Indicaciones generales</div>
            <div style="white-space: pre-line;">{{ $prescription->general_instructions }}</div>
        </div>
    @endif

    <div class="signature">
        @if($signature)
            <img src="{{ $signature }}" alt="Firma">
        @else
            <div style="height: 60px;"></div>
        @endif
        <div class="line">
            <strong>{{ $doctor['name'] ?? '' }}</strong><br>
            <span class="small">Céd. prof. {{ $doctor['professional_license'] ?? '—' }}</span>
        </div>
        @if($seal)
            <img src="{{ $seal }}" alt="Sello" style="max-height: 80px; margin-top: 6px;">
        @endif
    </div>

    <div class="footer">
        <table>
            <tr>
                <td>
                    {{ $clinic['address'] ?? '' }}
                    @if(!empty($clinic['phone'])) · Tel. {{ $clinic['phone'] }}@endif
                    @if(!empty($clinic['email'])) · {{ $clinic['email'] }}@endif
                </td>
                <td style="text-align: right; width: 210px;">
                    Verificación: {{ strtoupper(substr((string) $prescription->signature_hash, 0, 16)) }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
