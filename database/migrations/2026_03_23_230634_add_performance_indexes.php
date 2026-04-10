<?php
// File: database/migrations/2026_03_23_230634_add_performance_indexes.php
// Índices de rendimiento para las tablas más consultadas

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── activity_logs ────────────────────────────────────────────
        // Usado en: tendencia de actividad (WHERE DATE(created_at))
        // y en: actividad reciente (ORDER BY created_at DESC)
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('created_at',  'idx_activity_created_at');
            $table->index('usuario_id',  'idx_activity_usuario_id');
            $table->index('modulo',      'idx_activity_modulo');
        });

        // ── auditoria_evaluacion ─────────────────────────────────────
        // Usado masivamente en: getEvaluacion, resumenEvaluacion,
        // complianceStats, finalizarAuditoria, DashboardController
        Schema::table('auditoria_evaluacion', function (Blueprint $table) {
            // Composite: cubrirá WHERE auditoria_id=? AND norma_id=?
            $table->index(['auditoria_id', 'norma_id'], 'idx_eval_auditoria_norma');
            // Cubre: WHERE norma_id=? JOIN auditorias (para buscar última auditoría por norma)
            $table->index('norma_id', 'idx_eval_norma_id');
            // Cubre: WHERE estado=? (conformes, no_conformes, etc.)
            $table->index('estado', 'idx_eval_estado');
        });

        // ── documento ────────────────────────────────────────────────
        // Usado en: whereBetween/where Fecha_Caducidad (KPIs, docs por vencer)
        Schema::table('documento', function (Blueprint $table) {
            $table->index('Fecha_Caducidad', 'idx_doc_caducidad');
        });

        // ── auditorias ───────────────────────────────────────────────
        // Usado en: ORDER BY created_at DESC (buscar última auditoría por norma)
        Schema::table('auditorias', function (Blueprint $table) {
            $table->index('created_at', 'idx_auditorias_created_at');
        });

        // ── hallazgos ────────────────────────────────────────────────
        // Usado en: WHERE prioridad='alta' AND created_at >= (notifications)
        Schema::table('hallazgos', function (Blueprint $table) {
            $table->index(['prioridad', 'created_at'], 'idx_hallazgos_prio_fecha');
        });

        // ── documento_has_norma ──────────────────────────────────────
        // Usado en: WHERE Norma_idNorma=? (compliance por norma)
        // y en: WHERE Documento_idDocumento=? (show documento)
        Schema::table('documento_has_norma', function (Blueprint $table) {
            $table->index('Norma_idNorma',        'idx_docnorma_norma');
            $table->index('Documento_idDocumento', 'idx_docnorma_doc');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_activity_created_at');
            $table->dropIndex('idx_activity_usuario_id');
            $table->dropIndex('idx_activity_modulo');
        });

        Schema::table('auditoria_evaluacion', function (Blueprint $table) {
            $table->dropIndex('idx_eval_auditoria_norma');
            $table->dropIndex('idx_eval_norma_id');
            $table->dropIndex('idx_eval_estado');
        });

        Schema::table('documento', function (Blueprint $table) {
            $table->dropIndex('idx_doc_caducidad');
        });

        Schema::table('auditorias', function (Blueprint $table) {
            $table->dropIndex('idx_auditorias_created_at');
        });

        Schema::table('hallazgos', function (Blueprint $table) {
            $table->dropIndex('idx_hallazgos_prio_fecha');
        });

        Schema::table('documento_has_norma', function (Blueprint $table) {
            $table->dropIndex('idx_docnorma_norma');
            $table->dropIndex('idx_docnorma_doc');
        });
    }
};
