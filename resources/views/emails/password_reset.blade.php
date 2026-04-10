<x-mail::message>
# 🔑 Restablecer contraseña — DracoCert

Hola **{{ $nombreUsuario }}**,

Recibimos una solicitud para restablecer la contraseña de tu cuenta en **DracoCert**.

Si realizaste esta solicitud, haz clic en el botón de abajo para crear una nueva contraseña:

<x-mail::button :url="$resetUrl" color="orange">
Restablecer mi contraseña
</x-mail::button>

> ⏰ Este enlace expirará en **{{ $expiraMinutos }} minutos**.

---

Si **no solicitaste** restablecer tu contraseña, ignora este correo. Tu cuenta permanece segura.

*— Equipo DracoCert*

</x-mail::message>
