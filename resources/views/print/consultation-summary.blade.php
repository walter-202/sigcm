<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resumen de Atención #{{ $consultation->id }} — SIGCM</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color: #111827; margin: 24px auto; max-width: 820px; line-height: 1.5; }
        .toolbar { display: flex; justify-content: space-between; margin-bottom: 20px; padding: 12px 16px; background: #f3f4f6; border-radius: 8px; }
        .btn { background: #0f766e; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration: none; font-size: 14px; }
        .header { border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header h1 { margin: 0; font-size: 20px; color: #0f766e; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 20px; margin-bottom: 20px; font-size: 14px; }
        .section { margin-bottom: 18px; }
        .section h2 { font-size: 15px; color: #0f766e; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; margin-bottom: 8px; }
        .box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; margin-bottom: 8px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 6px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .signature { margin-top: 36px; border-top: 1px solid #9ca3af; width: 280px; padding-top: 6px; font-size: 13px; }
        @media print {
            .toolbar { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span><strong>SIGCM</strong> · Documento clínico para impresión</span>
        <button type="button" class="btn" onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <div class="header">
        <div>
            <h1>Resumen de Atención Ambulatoria</h1>
            <div>Atención #{{ $consultation->id }} · Estado: {{ $consultation->status?->getLabel() }}</div>
        </div>
        <div style="text-align: right; font-size: 13px;">
            <div><strong>Fecha de atención:</strong> {{ $consultation->occurred_at?->format('d/m/Y H:i') }}</div>
            @if ($consultation->signed_at)
                <div><strong>Firmada el:</strong> {{ $consultation->signed_at->format('d/m/Y H:i') }}</div>
            @endif
        </div>
    </div>

    <div class="grid">
        <div><strong>Paciente:</strong> {{ $consultation->patient?->full_name }}</div>
        <div>
            <strong>Historia clínica:</strong>
            {{ $consultation->patient?->clinicalRecord ? 'HC-' . str_pad((string) $consultation->patient->clinicalRecord->id, 4, '0', STR_PAD_LEFT) : 'Pendiente' }}
        </div>
        <div>
            <strong>Documento:</strong>
            {{ $consultation->patient?->document_type && $consultation->patient?->document_number ? "{$consultation->patient->document_type}-{$consultation->patient->document_number}" : 'Sin documento' }}
        </div>
        <div><strong>Médico tratante:</strong> {{ $consultation->doctor?->full_name }} ({{ $consultation->doctor?->professional_license }})</div>
    </div>

    <div class="section">
        <h2>{{ __('sigcm.consultation.sections.clinical_note') }}</h2>
        <div class="box"><strong>{{ __('sigcm.consultation.sections.anamnesis_label') }}:</strong><br>{{ $consultation->chief_complaint }}</div>
        @if ($consultation->subjective)
            <div class="box"><strong>Historia de la enfermedad actual:</strong><br>{{ $consultation->subjective }}</div>
        @endif
        @if ($consultation->objective)
            <div class="box"><strong>{{ __('sigcm.consultation.sections.physical_exam_label') }}:</strong><br>{{ $consultation->objective }}</div>
        @endif
        @if ($consultation->assessment)
            <div class="box"><strong>{{ __('sigcm.consultation.sections.diagnostic_impression_label') }}:</strong><br>{{ $consultation->assessment }}</div>
        @endif
        @if ($consultation->plan)
            <div class="box"><strong>{{ __('sigcm.consultation.sections.management_plan_label') }}:</strong><br>{{ $consultation->plan }}</div>
        @endif
    </div>

    @if ($consultation->observations->isNotEmpty())
        <div class="section">
            <h2>{{ __('sigcm.consultation.sections.vitals') }}</h2>
            <table>
                <thead><tr><th>Parámetro</th><th>Valor</th><th>Unidad</th><th>Hora</th></tr></thead>
                <tbody>
                    @foreach ($consultation->observations as $obs)
                        <tr>
                            <td>{{ $obs->code }}</td>
                            <td>{{ $obs->value_text ?? $obs->value_number }}</td>
                            <td>{{ $obs->unit_code ?: '-' }}</td>
                            <td>{{ $obs->observed_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($consultation->diagnoses->isNotEmpty())
        <div class="section">
            <h2>{{ __('sigcm.consultation.sections.diagnoses') }}</h2>
            <table>
                <thead><tr><th>Código</th><th>Diagnóstico</th><th>Certeza</th><th>Principal</th></tr></thead>
                <tbody>
                    @foreach ($consultation->diagnoses as $dx)
                        <tr>
                            <td>{{ $dx->code_system }} {{ $dx->code ?: '-' }}</td>
                            <td>{{ $dx->diagnosis_text }}</td>
                            <td>{{ $dx->certainty }}</td>
                            <td>{{ $dx->is_primary ? 'Sí' : 'No' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($consultation->amendments->isNotEmpty())
        <div class="section">
            <h2>Enmiendas posteriores a la firma</h2>
            @foreach ($consultation->amendments as $amendment)
                <div class="box">
                    <strong>{{ $amendment->created_at?->format('d/m/Y H:i') }} · {{ $amendment->author?->name }}:</strong>
                    {{ $amendment->reason }} — {{ $amendment->additional_note }}
                </div>
            @endforeach
        </div>
    @endif

    <div class="signature">
        {{ $consultation->doctor?->full_name }}<br>
        Matrícula profesional: {{ $consultation->doctor?->professional_license }}
    </div>
</body>
</html>
