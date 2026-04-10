<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE `version` ADD COLUMN `url` VARCHAR(1000) NULL DEFAULT NULL AFTER `public_id`'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE `version` DROP COLUMN `url`'
        );
    }
};
