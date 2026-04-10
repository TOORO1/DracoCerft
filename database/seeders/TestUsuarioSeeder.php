<?php
// php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestUsuarioSeeder extends Seeder
{
    public function run()
    {

        DB::table('Estado')->updateOrInsert(
            ['idEstado' => 1],
            [
                'Nombre_estado' => 'Activo',
                'Descripcion' => 'Estado inicial',
            ]
        );


        $plainToken = Str::random(60);
        $hashedToken = hash('sha256', $plainToken);


        DB::table('Usuario')->updateOrInsert(
                    ['Correo' => 'test@example.com'],
            [
                'Cedula' => '00000000',
                'Nombre_Usuario' => 'Usuario Test',
                'password' => Hash::make('secret_password'),
                'api_token' => $hashedToken,
                'Fecha_Creacion' => now(),
                'Fecha_Ultima' => now(),
                'Fecha_Registro' => now(),
                'Estado_idEstado' => 1
            ]
        );


        $this->command->info('API token para test@example.com: ' . $plainToken);
    }
}
