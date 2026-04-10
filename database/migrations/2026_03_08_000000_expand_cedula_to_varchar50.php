<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE `usuario` MODIFY COLUMN `Cedula` VARCHAR(50) NULL DEFAULT NULL'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE `usuario` MODIFY COLUMN `Cedula` VARCHAR(20) NULL DEFAULT NULL'
        );
    }
};
