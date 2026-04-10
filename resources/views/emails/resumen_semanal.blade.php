<x-mail::message>
# 📊 Resumen Semanal DracoCert

Período: **{{ $semana }}**

---

## 🎯 Cumplimiento Global ISO: {{ $pctGlobal }}%

---

## 📈 KPIs de la Semana

<x-mail::table>
| Indicador | Valor |
|-----------|-------|
| 📄 Total Documentos | {{ $kpis['total_docs'] }} |
| ✅ Documentos Vigentes | {{ $kpis['vigentes'] }} |
| 📋 Auditorías realizadas esta semana | {{ count($auditorias) }} |
| 🔍 Hallazgos nuevos esta semana | {{ count($hallazgos) }} |
| 👥 Usuarios activos | {{ $kpis['usuarios'] }} |
</x-mail::table>

---

@if(count($auditorias) > 0)
## 🔍 Auditorías de la Semana

<x-mail::table>
| Auditoría | Auditor | Fecha |
|-----------|---------|-------|
@foreach($auditorias as $a)
| {{ $a->titulo }} | {{ $a->auditor ?? 'N/E' }} | {{ $a->fecha ? \Carbon\Carbon::parse($a->fecha)->format('d/m/Y') : '—' }} |
@endforeach
</x-mail::table>

@endif

@if(count($hallazgos) > 0)
## ⚠️ Hallazgos Registrados Esta Semana

<x-mail::table>
| Hallazgo | Prioridad |
|----------|-----------|
@foreach($hallazgos as $h)
| {{ $h->titulo }} | {{ strtoupper($h->prioridad) }} |
@endforeach
</x-mail::table>

@endif

@if(count($caducados) > 0)
## ❌ Documentos Caducados ({{ count($caducados) }})

@foreach($caducados as $d)
- **{{ $d->Nombre_Doc }}** — venció el {{ \Carbon\Carbon::parse($d->Fecha_Caducidad)->format('d/m/Y') }}
@endforeach

@endif

@if(count($porVencer) > 0)
## ⏰ Documentos por Vencer en 30 Días ({{ count($porVencer) }})

@foreach($porVencer as $d)
- **{{ $d->Nombre_Doc }}** — vence el {{ \Carbon\Carbon::parse($d->Fecha_Caducidad)->format('d/m/Y') }}
@endforeach

@endif

---

<x-mail::button :url="$appUrl . '/dashboard'">
Ver Dashboard completo
</x-mail::button>

*Resumen generado automáticamente cada lunes por **DracoCert**.*

</x-mail::message>
