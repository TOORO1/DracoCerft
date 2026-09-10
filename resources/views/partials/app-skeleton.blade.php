{{--
    Partial: partials/app-skeleton.blade.php
    Skeleton visual del layout principal (sidebar + header + contenido).
    Se muestra INMEDIATAMENTE con el HTML — React lo reemplaza al montar.
    Esto hace que el LCP sea el skeleton (~0ms) en lugar de esperar React (~4s).

    Variable opcional: $skTitle — título visible en la barra superior del skeleton
--}}
<style>
/* Layout skeleton — solo el mínimo necesario para el primer render */
.sk-wrap{display:flex;min-height:100vh;overflow:hidden}
.sk-side{
    width:250px;min-height:100vh;flex-shrink:0;
    background:#1a1a2e;padding:20px 16px;
}
.sk-side-logo{height:52px;border-radius:10px;margin-bottom:28px}
.sk-side-section{height:11px;width:60px;border-radius:4px;margin-bottom:14px;opacity:.35;background:#fff}
.sk-side-item{height:38px;border-radius:8px;margin-bottom:8px;background:rgba(255,255,255,.08)}
.sk-main{flex:1;display:flex;flex-direction:column;min-width:0}
.sk-topbar{
    height:60px;background:#fff;
    border-bottom:1px solid #eee;
    display:flex;align-items:center;
    padding:0 24px;gap:12px;
    box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.sk-topbar-title{height:18px;width:160px}
.sk-topbar-right{margin-left:auto;display:flex;gap:10px;align-items:center}
.sk-topbar-bell{width:32px;height:32px;border-radius:50%}
.sk-topbar-avatar{width:36px;height:36px;border-radius:50%}
.sk-content{padding:28px 24px;display:flex;flex-direction:column;gap:20px}
.sk-page-title{height:24px;width:220px}
.sk-kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.sk-kpi-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.sk-kpi-icon{width:44px;height:44px;border-radius:10px}
.sk-kpi-val{height:28px;width:70px;margin-top:14px}
.sk-kpi-lbl{height:13px;width:90px;margin-top:10px}
.sk-chart-row{display:grid;grid-template-columns:2fr 1fr;gap:16px}
.sk-chart-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.sk-chart-header{height:18px;width:180px;margin-bottom:20px}
.sk-chart-body{height:160px}
@media(max-width:900px){
    .sk-kpi-row{grid-template-columns:repeat(2,1fr)}
    .sk-chart-row{grid-template-columns:1fr}
}
@media(max-width:768px){.sk-side{display:none}}
</style>

<div id="app-skeleton" aria-hidden="true">
    <div class="sk-wrap">
        {{-- Sidebar --}}
        <div class="sk-side">
            <div class="sk-side-logo sk-shimmer"></div>
            <div class="sk-side-section"></div>
            @for($i=0;$i<3;$i++)<div class="sk-side-item sk-shimmer"></div>@endfor
            <div class="sk-side-section" style="margin-top:20px"></div>
            @for($i=0;$i<4;$i++)<div class="sk-side-item sk-shimmer"></div>@endfor
        </div>

        {{-- Main area --}}
        <div class="sk-main">
            {{-- Top bar --}}
            <div class="sk-topbar">
                <div class="sk-topbar-title sk-shimmer"></div>
                <div class="sk-topbar-right">
                    <div class="sk-topbar-bell sk-shimmer"></div>
                    <div class="sk-topbar-avatar sk-shimmer"></div>
                </div>
            </div>

            {{-- Content --}}
            <div class="sk-content">
                <div class="sk-page-title sk-shimmer"></div>

                {{-- KPI cards --}}
                <div class="sk-kpi-row">
                    @for($i=0;$i<4;$i++)
                    <div class="sk-kpi-card">
                        <div class="sk-kpi-icon sk-shimmer"></div>
                        <div class="sk-kpi-val sk-shimmer"></div>
                        <div class="sk-kpi-lbl sk-shimmer"></div>
                    </div>
                    @endfor
                </div>

                {{-- Charts / table --}}
                <div class="sk-chart-row">
                    <div class="sk-chart-card">
                        <div class="sk-chart-header sk-shimmer"></div>
                        <div class="sk-chart-body sk-shimmer"></div>
                    </div>
                    <div class="sk-chart-card">
                        <div class="sk-chart-header sk-shimmer"></div>
                        <div class="sk-chart-body sk-shimmer"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
