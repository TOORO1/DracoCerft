<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version', function (Blueprint $table) {
            if (! Schema::hasColumn('version', 'resource_type')) {
                $table->string('resource_type', 20)->default('raw')->after('url');
            }
        });

        // Registros anteriores quedan como 'raw' (documentos) por defecto
        DB::table('version')->update(['resource_type' => 'raw']);
    }

    public function down(): void
    {
        Schema::table('version', function (Blueprint $table) {
            if (Schema::hasColumn('version', 'resource_type')) {
                $table->dropColumn('resource_type');
            }
        });
    }
};
