<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('capacitacion', function (Blueprint $table) {
            // Nombre original del archivo subido por el usuario (evita usar el public_id de Cloudinary)
            $table->string('original_name')->nullable()->after('tipo_archivo');
        });
    }

    public function down(): void
    {
        Schema::table('capacitacion', function (Blueprint $table) {
            $table->dropColumn('original_name');
        });
    }
};
