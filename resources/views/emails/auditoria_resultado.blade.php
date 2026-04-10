<x-mail::message>
# ✅ Auditoría ISO Completada

Se ha finalizado una auditoría en el sistema **DracoCert**.

---

## 📋 Información de la Auditoría

| Campo | Detalle |
|-------|---------|
| **Título** | {{ $auditoria->titulo }} |
| **Auditor** | {{ $auditoria->auditor ?? 'No especificado' }} |
| **Fecha** | {{ $auditoria->fecha ? \Carbon\Carbon::parse($auditoria->fecha)->format('d/m/Y') : 'Sin fecha' }} |
| **Cumplimiento Global** | **{{ $pctGlobal }}%** |

---

## 📊 Resultados por Norma ISO

@foreach($resumen as $item)
### {{ $item['norma'] }}

<x-mail::table>
| Métrica | Resultado |
|---------|-----------|
| Cumplimiento | **{{ $item['pct'] }}%** |
| ✅ Conformes | {{ $item['conformes'] }} |
| ❌ No conformes | {{ $item['no_conformes'] }} |
| ⚠️ Observaciones | {{ $item['observaciones'] }} |
| ➖ N/A | {{ $item['na'] }} |
| Total cláusulas | {{ $item['total'] }} |
</x-mail::table>

@endforeach

---

<x-mail::button :url="$appUrl . '/auditoria'">
Ver auditoría completa
</x-mail::button>

*Este mensaje fue generado automáticamente por **DracoCert**.*

</x-mail::message>
