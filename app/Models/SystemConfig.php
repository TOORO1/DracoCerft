<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemConfig extends Model
{
    protected $table    = 'system_config';
    protected $fillable = ['clave', 'valor', 'tipo', 'grupo', 'etiqueta', 'descripcion', 'unidad'];

    // ── Tiempo de caché en segundos (5 minutos) ───────────────────────────────
    private const CACHE_TTL = 300;

    /**
     * Obtiene el valor de una clave de configuración.
     * Tipifica automáticamente según el campo `tipo`.
     */
    public static function get(string $clave, mixed $default = null): mixed
    {
        $all = static::allCached();

        if (! array_key_exists($clave, $all)) {
            return $default;
        }

        $item = $all[$clave];

        return match ($item['tipo']) {
            'int'  => (int) $item['valor'],
            'bool' => (bool) ((int) $item['valor']),
            default => $item['valor'],   // string | list
        };
    }

    /**
     * Actualiza el valor de una clave y limpia la caché.
     */
    public static function set(string $clave, mixed $valor): void
    {
        static::where('clave', $clave)->update([
            'valor'      => (string) $valor,
            'updated_at' => now(),
        ]);
        static::clearCache();
    }

    /**
     * Devuelve todos los registros indexados por clave, desde caché.
     */
    public static function allCached(): array
    {
        return Cache::remember('system_config_all', self::CACHE_TTL, function () {
            return static::all()
                ->keyBy('clave')
                ->map(fn($r) => ['valor' => $r->valor, 'tipo' => $r->tipo])
                ->toArray();
        });
    }

    /**
     * Limpia la caché de configuración (llamar siempre que se guarde un valor).
     */
    public static function clearCache(): void
    {
        Cache::forget('system_config_all');
    }
}
