<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receta Médica REC-#{{ $prescription->id }} — SIGCM</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color: #111827; margin: 24px auto; max-width: 780px; line-height: 1.5; }
        .toolbar { display: flex; justify-content: space-between; margin-bottom: 20px; padding: 12px 16px; background: #f3f4f6; border-radius: 8px; }
        .btn { background: #0f766e; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; }
        .header { border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .header h1 { margin: 0; font-size: 20px; color: #0f766e; }
        .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 20px; margin-bottom: 20px; font-size: 14px; }
        .item { border: 1px solid #d1d5db; border-radius: 6px; padding: 10px 14px; margin-bottom: 10px; }
        .notes { background: #f9fafb; border-left: 4px solid #0f766e; padding: 10px 14px; margin-top: 16px; font-size: 14px; }
        .signature { margin-top: 40px; border-top: 1px solid #9ca3af; width: 280px; padding-top: 6px; font-size: 13px; }
        @media print {
            .toolbar { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span><strong>SIGCM</strong> · Prescripción médica</span>
        <button type="button" class="btn" onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <div class="header">
        <div>
            <h1>Receta Médica REC-#{{ $prescription->id }}</h1>
            <div>Estado: {{ __("enums.prescription_status.{$prescription->status}") }}</div>
        </div>
        <div style="text-align: right; font-size: 13px;">
            <div><strong>Fecha de emisión:</strong> {{ $prescription->issued_at?->format('d/m/Y H:i') ?? 'Borrador' }}</div>
            <div><strong>Consulta:</strong> #{{ $prescription->consultation_id }}</div>
        </div>
    </div>

    <div class="meta">
        <div><strong>Paciente:</strong> {{ $prescription->consultation?->patient?->full_name }}</div>
        <div>
            <strong>Documento:</strong>
            {{ $prescription->consultation?->patient?->document_type && $prescription->consultation?->patient?->document_number ? "{$prescription->consultation->patient->document_type}-{$prescription->consultation->patient->document_number}" : 'Sin documento' }}
        </div>
        <div><strong>Médico:</strong> {{ $prescription->consultation?->doctor?->full_name }}</div>
        <div><strong>Especialidad:</strong> {{ $prescription->consultation?->doctor?->specialty ?: 'Medicina general' }}</div>
    </div>

    <div>
        @foreach ($prescription->items as $index => $item)
            <div class="item">
                <div><strong>{{ $index + 1 }}. {{ $item->medication_name }}</strong></div>
                <div style="font-size: 14px;">
                    Dosis: {{ $item->dose }} · {{ $item->route }} · {{ $item->frequency }}
                    @if ($item->duration_days)
                        · Duración: {{ $item->duration_days }} días
                    @endif
                    @if ($item->quantity)
                        · Cantidad: {{ $item->quantity }}
                    @endif
                </div>
                @if ($item->instructions)
                    <div style="font-size: 13px; color: #374151; margin-top: 4px;">Indicaciones: {{ $item->instructions }}</div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($prescription->notes)
        <div class="notes">
            <strong>Indicaciones generales:</strong> {{ $prescription->notes }}
        </div>
    @endif

    <div class="signature">
        {{ $prescription->consultation?->doctor?->full_name }}<br>
        Matrícula profesional: {{ $prescription->consultation?->doctor?->professional_license }}
    </div>
</body>
</html>
