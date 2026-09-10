{{--
    Partial: partials/page-data.blade.php
    Inyecta las variables globales de ventana necesarias en todas las páginas autenticadas.
    Incluir DESPUÉS de <body> o al final de <head>.

    Usa Cache::remember por request-key (usuario) para evitar múltiples queries de roles.
--}}
<script>
(function(){
    window.currentUser   = @json(\Illuminate\Support\Facades\Cache::remember(
        'page_data_user_name_' . optional(\Illuminate\Support\Facades\Auth::user())->idUsuario,
        300,
        fn() => optional(\Illuminate\Support\Facades\Auth::user())->Nombre_Usuario ?? 'Usuario'
    ));
    window.currentUserId = @json(optional(\Illuminate\Support\Facades\Auth::user())->getKey());
    window.currentRole   = @json(\Illuminate\Support\Facades\Cache::remember(
        'page_data_role_' . optional(\Illuminate\Support\Facades\Auth::user())->idUsuario,
        300,
        fn() => optional(optional(\Illuminate\Support\Facades\Auth::user())->roles()->first())->Nombre_rol ?? 'Usuario'
    ));
    window.sessionTimeoutMinutos = @json(\App\Models\SystemConfig::get('sesion_timeout_minutos', 60));
    window.backendUrl = "";

    // Recuperar token de sesión (para módulos que usan axios con Bearer)
    window.currentUserToken = "{{ session('api_token') ?? '' }}";
    try {
        const saved = localStorage.getItem('api_token');
        if (saved && saved.length && (!window.currentUserToken || window.currentUserToken === "")) {
            window.currentUserToken = saved;
        }
    } catch(e){}
})();
</script>
