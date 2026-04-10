<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cambiar Cedula de INT a VARCHAR(20) nullable
        DB::statement('ALTER TABLE `usuario` MODIFY COLUMN `Cedula` VARCHAR(20) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `usuario` MODIFY COLUMN `Cedula` INT(11) NOT NULL DEFAULT 0');
    }
};
