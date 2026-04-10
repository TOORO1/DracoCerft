<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SystemConfig;
use App\Models\ActivityLog;

class ConfiguracionController extends Controller
{
    /**
     * GET /api/configuracion
     * Devuelve todos los parámetros agrupados.
     */
    public function index()
    {
        $items = SystemConfig::orderBy('grupo')->orderBy('id')->get();

        $grupos = $items->groupBy('grupo')->map(fn($g) => $g->values());

        return response()->json($grupos);
    }

    /**
     * PUT /api/configuracion/{clave}
     * Actualiza un único parámetro. Solo Administrador.
     */
    public function update(Request $request, string $clave)
    {
        $config = SystemConfig::where('clave', $clave)->first();

        if (! $config) {
            return response()->json(['error' => 'Parámetro no encontrado'], 404);
        }

        $request->validate(['valor' => 'required|string|max:500']);

        $valor = trim($request->input('valor'));

        // ── Validaciones específicas por tipo ─────────────────────────────────
        if ($config->tipo === 'int') {
            if (! ctype_digit($valor) && ! (str_starts_with($valor, '-') && ctype_digit(substr($valor, 1)))) {
                return response()->json(['error' => 'El valor debe ser un número entero'], 422);
            }
            // Validaciones de rango para claves críticas
            if ($clave === 'doc_max_size_mb' && ((int)$valor < 1 || (int)$valor > 500)) {
                return response()->json(['error' => 'El tamaño debe estar entre 1 y 500 MB'], 422);
            }
            if ($clave === 'cap_max_size_mb' && ((int)$valor < 1 || (int)$valor > 500)) {
                return response()->json(['error' => 'El tamaño debe estar entre 1 y 500 MB'], 422);
            }
            if ($clave === 'doc_max_versiones' && (int)$valor < 0) {
                return response()->json(['error' => 'El número de versiones no puede ser negativo'], 422);
            }
            if (in_array($clave, ['doc_dias_vigencia_default', 'cap_dias_vigencia_default']) && (int)$valor < 1) {
                return response()->json(['error' => 'La vigencia debe ser al menos 1 día'], 422);
            }
            if ($clave === 'doc_dias_alerta_vencimiento' && ((int)$valor < 1 || (int)$valor > 365)) {
                return response()->json(['error' => 'Los días de alerta deben estar entre 1 y 365'], 422);
            }
            if ($clave === 'sesion_timeout_minutos' && ((int)$valor < 5 || (int)$valor > 1440)) {
                return response()->json(['error' => 'El timeout debe estar entre 5 y 1440 minutos'], 422);
            }
        }

        if ($config->tipo === 'bool' && ! in_array($valor, ['0', '1'])) {
            return response()->json(['error' => 'El valor debe ser 0 o 1'], 422);
        }

        if ($config->tipo === 'list') {
            // Normalizar: minúsculas, sin espacios, sin puntos
            $valor = implode(',', array_filter(array_map(
                fn($ext) => strtolower(trim(ltrim(trim($ext), '.'))),
                explode(',', $valor)
            )));
            if (empty($valor)) {
                return response()->json(['error' => 'Debe especificar al menos un tipo de archivo'], 422);
            }
        }

        SystemConfig::set($clave, $valor);

        ActivityLog::record(
            'config_update',
            'configuracion',
            "Parámetro [{$clave}] actualizado a: {$valor}"
        );

        return response()->json([
            'ok'     => true,
            'clave'  => $clave,
            'valor'  => $valor,
        ]);
    }

    /**
     * POST /api/configuracion/reset/{clave}
     * Restaura un parámetro a su valor por defecto.
     */
    public function reset(string $clave)
    {
        $defaults = [
            'doc_max_size_mb'           => '50',
            'doc_tipos_permitidos'      => 'pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,gif,txt,csv,zip,rar',
            'doc_max_versiones'         => '10',
            'doc_dias_vigencia_default' => '365',
            'doc_dias_alerta_vencimiento' => '30',
            'cap_max_size_mb'           => '100',
            'cap_dias_vigencia_default' => '365',
            'sesion_timeout_minutos'    => '60',
            'nombre_empresa'            => 'Mi Empresa',
            'alertas_email_activo'      => '1',
            'reporte_logo_url'          => '',
            'reporte_pie_pagina'        => 'Generado por DracoCert — Software de Gestión ISO',
        ];

        if (! array_key_exists($clave, $defaults)) {
            return response()->json(['error' => 'Parámetro no encontrado'], 404);
        }

        SystemConfig::set($clave, $defaults[$clave]);

        ActivityLog::record(
            'config_reset',
            'configuracion',
            "Parámetro [{$clave}] restaurado al valor por defecto"
        );

        return response()->json(['ok' => true, 'clave' => $clave, 'valor' => $defaults[$clave]]);
    }
}
