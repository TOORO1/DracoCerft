<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Índice único en Correo → cada login hace WHERE Correo = ?
        // Sin índice hace full-table-scan en cada intento de autenticación
        Schema::table('usuario', function (Blueprint $table) {
            // Comprobar si el índice ya existe antes de crearlo
            $indexes = collect(DB::select("SHOW INDEX FROM usuario"))
                        ->pluck('Key_name')->toArray();

            if (!in_array('idx_usuario_correo', $indexes)) {
                $table->index('Correo', 'idx_usuario_correo');
            }
            if (!in_array('idx_usuario_api_token', $indexes)) {
                // api_token es SHA-256 (64 chars) → prefix(64) alcanza para BTREE
                $table->index('api_token', 'idx_usuario_api_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropIndex('idx_usuario_correo');
            $table->dropIndex('idx_usuario_api_token');
        });
    }
};
