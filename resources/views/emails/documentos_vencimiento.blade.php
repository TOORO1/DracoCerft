<x-mail::message>
# 📋 Alerta de Documentos ISO — DracoCert

Estimado Administrador,

Se ha generado una alerta automática sobre el estado de los documentos en el sistema **DracoCert**.

---

@if(count($caducados) > 0)
## ❌ Documentos CADUCADOS ({{ count($caducados) }})

Estos documentos han superado su fecha de caducidad y requieren renovación inmediata:

<x-mail::table>
| # | Documento | Caducó el |
|---|-----------|-----------|
@foreach($caducados as $i => $doc)
| {{ $i + 1 }} | {{ $doc->Nombre_Doc }} | {{ \Carbon\Carbon::parse($doc->Fecha_Caducidad)->format('d/m/Y') }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$appUrl . '/gestor_documentacion'" color="red">
Ver documentos caducados
</x-mail::button>

---
@endif

@if(count($porVencer) > 0)
## ⚠️ Documentos POR VENCER — próximos 30 días ({{ count($porVencer) }})

Estos documentos están próximos a vencer y deben ser renovados o revisados:

<x-mail::table>
| # | Documento | Vence el | Días restantes |
|---|-----------|----------|----------------|
@foreach($porVencer as $i => $doc)
| {{ $i + 1 }} | {{ $doc->Nombre_Doc }} | {{ \Carbon\Carbon::parse($doc->Fecha_Caducidad)->format('d/m/Y') }} | {{ max(0, (int) now()->diffInDays($doc->Fecha_Caducidad, false)) }} días |
@endforeach
</x-mail::table>

<x-mail::button :url="$appUrl . '/gestor_documentacion'" color="yellow">
Revisar documentos
</x-mail::button>

@endif

---

Este mensaje fue generado automáticamente por **DracoCert** — Sistema de Gestión ISO para PYMEs.
*No responda a este correo.*

</x-mail::message>
