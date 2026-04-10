<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: add the new key first (MySQL needs an index on auditoria_id before the old one is dropped)
        Schema::table('auditoria_evaluacion', function (Blueprint $table) {
            $table->unique(
                ['auditoria_id', 'norma_id', 'clausula_codigo'],
                'uq_auditoria_norma_clausula'
            );
        });

        // Step 2: drop the old key (now safe because the new one also covers auditoria_id)
        Schema::table('auditoria_evaluacion', function (Blueprint $table) {
            $table->dropUnique('uq_auditoria_clausula');
        });
    }

    public function down(): void
    {
        Schema::table('auditoria_evaluacion', function (Blueprint $table) {
            $table->unique(['auditoria_id', 'clausula_codigo'], 'uq_auditoria_clausula');
        });

        Schema::table('auditoria_evaluacion', function (Blueprint $table) {
            $table->dropUnique('uq_auditoria_norma_clausula');
        });
    }
};
