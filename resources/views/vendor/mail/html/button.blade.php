@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
$colorMap = [
    'primary' => '#2d3748',
    'blue'    => '#2d3748',
    'green'   => '#48bb78',
    'success' => '#48bb78',
    'red'     => '#e53e3e',
    'error'   => '#e53e3e',
    'orange'  => '#ff8a00',
    'yellow'  => '#ff8a00',
];
$bg = $colorMap[$color] ?? $colorMap['primary'];
@endphp

<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}" style="padding: 16px 0;">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
{{--
  bgcolor en el <td> es la única forma 100% confiable en Outlook desktop,
  Outlook.com, Gmail, Apple Mail y todos los clientes de email.
  NO usar comentarios condicionales ni VML — causan problemas en New Outlook.
--}}
<td
    align="center"
    bgcolor="{{ $bg }}"
    style="border-radius:6px; background-color:{{ $bg }};"
>
    <a
        href="{{ $url }}"
        target="_blank"
        rel="noopener"
        style="
            background-color: {{ $bg }};
            border-radius: 6px;
            color: #ffffff;
            display: inline-block;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
            font-weight: bold;
            line-height: 1;
            padding: 14px 30px;
            text-align: center;
            text-decoration: none;
            -webkit-text-size-adjust: none;
        "
    >{{ $slot }}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
