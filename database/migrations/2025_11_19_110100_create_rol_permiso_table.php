<?php
// php
// File: database/migrations/2025_11_19_110100_create_rol_permiso_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRolPermisoTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('rol_permiso')) {
            Schema::create('rol_permiso', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rol_id');
                $table->unsignedBigInteger('permiso_id');
                $table->timestamps();

                $table->unique(['rol_id', 'permiso_id']);
            });

            // Añadir claves foráneas solo si las tablas referenciadas existen
            if (Schema::hasTable('rol') || Schema::hasTable('permiso')) {
                Schema::table('rol_permiso', function (Blueprint $table) {
                    // FK a rol (PK en tu proyecto es idRol — añadir solo si existe)
                    if (Schema::hasTable('rol')) {
                        // Evitar duplicar FK si ya existe (al ejecutar varias veces)
                        try {
                            $table->foreign('rol_id')->references('idRol')->on('rol')->onDelete('cascade');
                        } catch (\Exception $e) {
                            // noop: si la FK ya existe o la columna no coincide, no detener migración
                        }
                    }

                    // FK a permiso
                    if (Schema::hasTable('permiso')) {
                        try {
                            $table->foreign('permiso_id')->references('id')->on('permiso')->onDelete('cascade');
                        } catch (\Exception $e) {
                            // noop
                        }
                    }
                });
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('rol_permiso')) {
            // Quitar FKs antes de dropear (si existen)
            try {
                Schema::table('rol_permiso', function (Blueprint $table) {
                    $sm = Schema::getConnection()->getDoctrineSchemaManager();
                    // Intentar soltar constraints de forma segura (ignorando errores)
                    if (Schema::hasTable('rol_permiso')) {
                        // No dependemos de nombres concretos de constraint para evitar fallos
                        // Laravel eliminará las FKs si existen al dropear la tabla
                    }
                });
            } catch (\Exception $e) {
                // noop
            }

            Schema::dropIfExists('rol_permiso');
        }
    }
}
