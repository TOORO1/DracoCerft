<?php
// File: database/migrations/2026_03_14_100000_make_norma_requisito_nullable.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeNormaRequisitoNullable extends Migration
{
    public function up()
    {
        // Make Requisitos_idRequisitos nullable so normas can be created without requiring a requisito
        Schema::table('norma', function (Blueprint $table) {
            $table->integer('Requisitos_idRequisitos')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('norma', function (Blueprint $table) {
            $table->integer('Requisitos_idRequisitos')->nullable(false)->change();
        });
    }
}
