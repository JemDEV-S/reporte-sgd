<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\DatabaseTestCase;

class CreateFirstAdministratorTest extends DatabaseTestCase
{
    private function command(string $dni = '00123456', string $confirmation = 'ClavePrueba123')
    {
        return $this->artisan('sirmd:crear-administrador')
            ->expectsQuestion('DNI', $dni)
            ->expectsQuestion('Nombre', 'María Elena')
            ->expectsQuestion('Apellidos', 'Pérez Ramos')
            ->expectsQuestion('Correo electrónico', ' MARIA@EXAMPLE.COM ')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, letras y números)', 'ClavePrueba123')
            ->expectsQuestion('Confirmar contraseña', $confirmation);
    }

    public function test_crea_primera_cuenta_con_hash_y_solo_rol_tecnico(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->command()->assertSuccessful();
        $user = User::sole();
        $this->assertSame('00123456', $user->dni);
        $this->assertSame('maria@example.com', $user->email);
        $this->assertTrue(Hash::check('ClavePrueba123', $user->password));
        $this->assertTrue($user->activo);
        $this->assertTrue($user->tienePermiso('plataforma.acceder'));
        $this->assertTrue($user->tienePermiso('usuarios.gestionar'));
        $this->assertFalse($user->tienePermiso('reportes.ver'));
        $this->assertCount(1, $user->roles);
        $this->artisan('sirmd:crear-administrador')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_rechaza_datos_invalidos_sin_crear_cuentas(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->command('123', 'diferente')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_no_modifica_permisos_revocados(): void
    {
        $this->seed(DatabaseSeeder::class);
        $role = Rol::where('codigo', 'administrador_tecnico')->sole();
        $role->permisos()->detach();
        $this->artisan('sirmd:crear-administrador')->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertCount(0, $role->permisos);
    }

    public function test_rechaza_duplicados_sin_alterar_usuario_existente(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['dni' => '00123456']);
        $this->command()->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertCount(0, $user->roles);
    }
}
