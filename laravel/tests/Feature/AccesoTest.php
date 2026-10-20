<?php

namespace Tests\Feature;

use App\Enums\PermisoCodigo;
use App\Enums\RolCodigo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\DatabaseTestCase;

class AccesoTest extends DatabaseTestCase
{
    public function test_usuario_conserva_dni_y_separa_nombre_de_apellidos(): void
    {
        $user = User::factory()->create([
            'dni' => '00123456',
            'nombre' => '  María   Elena ',
            'apellidos' => ' Pérez   Ramos ',
            'email' => '  MARIA@EXAMPLE.COM ',
            'password' => 'clave-solo-de-pruebas',
        ])->fresh();

        $this->assertSame('00123456', $user->dni);
        $this->assertSame('María Elena', $user->nombre);
        $this->assertSame('Pérez Ramos', $user->apellidos);
        $this->assertSame('María Elena Pérez Ramos', $user->nombre_completo);
        $this->assertSame($user->nombre_completo, $user->name);
        $this->assertSame('maria@example.com', $user->email);
        $this->assertTrue(Hash::check('clave-solo-de-pruebas', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
        $this->assertFalse(Schema::hasColumn('users', 'name'));
    }

    public function test_dni_es_unico(): void
    {
        User::factory()->create(['dni' => '00123456']);
        $this->expectException(QueryException::class);
        User::factory()->create(['dni' => '00123456']);
    }

    public function test_base_rechaza_correo_duplicado_aunque_se_omita_normalizacion_del_modelo(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $attributes = User::factory()->make()->getAttributes();
        $attributes['email'] = ' ANA@EXAMPLE.COM ';

        $this->expectException(QueryException::class);
        DB::table('users')->insert($attributes);
    }

    public function test_postgres_rechaza_dni_no_numerico(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Restricción de formato específica de PostgreSQL.');
        }

        $this->expectException(QueryException::class);
        User::factory()->create(['dni' => 'AB123456']);
    }

    public function test_postgres_rechaza_dni_con_menos_de_ocho_digitos(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Restricción de formato específica de PostgreSQL.');
        }

        $this->expectException(QueryException::class);
        User::factory()->create(['dni' => '1234567']);
    }

    public function test_datos_iniciales_son_repetibles_y_no_crean_usuarios(): void
    {
        $this->seed(DatabaseSeeder::class);
        $roleIds = Rol::orderBy('codigo')->pluck('id', 'codigo')->all();
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('permisos', 15);
        $this->assertDatabaseCount('permiso_rol', 20);
        $this->assertDatabaseCount('users', 0);
        $this->assertSame($roleIds, Rol::orderBy('codigo')->pluck('id', 'codigo')->all());
    }

    public function test_repetir_seeder_conserva_permisos_modificados_manualmente(): void
    {
        $this->seed(DatabaseSeeder::class);
        $rol = $this->rol(RolCodigo::AnalistaInstitucional);
        $institucional = Permiso::where('codigo', PermisoCodigo::VerDatosInstitucionales->value)->sole();
        $aprobacion = Permiso::where('codigo', PermisoCodigo::AprobarInformes->value)->sole();
        $rol->permisos()->detach($institucional);
        $rol->permisos()->attach($aprobacion);

        $this->seed(DatabaseSeeder::class);

        $this->assertFalse($rol->permisos()->whereKey($institucional->id)->exists());
        $this->assertTrue($rol->permisos()->whereKey($aprobacion->id)->exists());
    }

    public function test_administrador_tecnico_no_recibe_acceso_documental_automatico(): void
    {
        $user = $this->usuarioConRol(RolCodigo::AdministradorTecnico);
        $gate = Gate::forUser($user);

        $this->assertTrue($gate->allows('usuarios.gestionar'));
        $this->assertTrue($gate->allows('fuentes.gestionar'));
        $this->assertFalse($gate->allows('datos.ver_institucionales'));
        $this->assertFalse($gate->allows('datos.ver_personales'));
        $this->assertFalse($gate->allows('datos.ver_asuntos'));
        $this->assertFalse($gate->allows('reportes.ejecutar'));
    }

    public function test_analista_tiene_alcance_institucional_sin_administracion_ni_datos_sensibles(): void
    {
        $user = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);
        $gate = Gate::forUser($user);

        $this->assertTrue($gate->allows('reportes.ejecutar'));
        $this->assertTrue($gate->allows('datos.ver_institucionales'));
        $this->assertTrue($gate->allows('informes.generar'));
        $this->assertFalse($gate->allows('usuarios.gestionar'));
        $this->assertFalse($gate->allows('datos.ver_personales'));
        $this->assertFalse($gate->allows('informes.aprobar'));
    }

    public function test_usuario_dependencia_no_recibe_alcance_institucional(): void
    {
        $user = $this->usuarioConRol(RolCodigo::UsuarioDependencia);

        $this->assertTrue($user->tieneRol(RolCodigo::UsuarioDependencia));
        $this->assertTrue($user->tienePermiso(PermisoCodigo::ExportarReportes));
        $this->assertFalse($user->tienePermiso(PermisoCodigo::VerDatosInstitucionales));
        $this->assertFalse($user->tienePermiso(PermisoCodigo::VerDatosPersonales));
    }

    public function test_usuario_sin_rol_invitado_y_permiso_desconocido_son_denegados(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->tienePermiso(PermisoCodigo::AccederPlataforma));
        $this->assertFalse(Gate::forUser($user)->allows('plataforma.acceder'));
        $this->assertFalse(Gate::forUser($user)->allows('permiso.inexistente'));
        $this->assertFalse(Gate::allows('plataforma.acceder'));
    }

    public function test_desactivar_usuario_revoca_acceso_incluso_con_instancia_cargada(): void
    {
        $user = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);
        $this->assertTrue($user->tienePermiso('reportes.ejecutar'));
        DB::table('users')->where('id', $user->id)->update(['activo' => false]);

        $this->assertFalse($user->tienePermiso('reportes.ejecutar'));
        $this->assertFalse($user->tieneRol(RolCodigo::AnalistaInstitucional));
        $this->assertFalse(Gate::forUser($user)->allows('plataforma.acceder'));
    }

    public function test_revocacion_de_permiso_no_utiliza_relaciones_en_cache(): void
    {
        $user = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);
        $user->load('roles.permisos');
        $this->assertTrue($user->tienePermiso('reportes.exportar'));
        $permiso = Permiso::where('codigo', 'reportes.exportar')->sole();
        $this->rol(RolCodigo::AnalistaInstitucional)->permisos()->detach($permiso);

        $this->assertFalse($user->tienePermiso('reportes.exportar'));
    }

    public function test_usuario_puede_combinar_roles_sin_privilegios_implicitos(): void
    {
        $user = $this->usuarioConRol(RolCodigo::AdministradorTecnico);
        $user->roles()->attach($this->rol(RolCodigo::AnalistaInstitucional));

        $this->assertTrue($user->tienePermiso('usuarios.gestionar'));
        $this->assertTrue($user->tienePermiso('datos.ver_institucionales'));
        $this->assertFalse($user->tienePermiso('datos.ver_personales'));
    }

    public function test_asignacion_de_rol_no_admite_duplicados(): void
    {
        $user = $this->usuarioConRol(RolCodigo::UsuarioDependencia);
        $this->expectException(QueryException::class);
        $user->roles()->attach($this->rol(RolCodigo::UsuarioDependencia));
    }

    public function test_asignacion_de_rol_requiere_un_rol_existente(): void
    {
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        $user->roles()->attach(999999);
    }

    public function test_no_se_elimina_un_usuario_con_roles_asignados(): void
    {
        $user = $this->usuarioConRol(RolCodigo::UsuarioDependencia);
        $this->expectException(QueryException::class);
        $user->delete();
    }

    public function test_migraciones_de_acceso_se_pueden_revertir_y_reaplicar(): void
    {
        // Revierte todas las migraciones; las de acceso quedan sin sus tablas.
        $this->artisan('migrate:rollback', [
            '--database' => DB::getDefaultConnection(),
            '--force' => true,
        ])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('roles'));
        $this->assertFalse(Schema::hasTable('permisos'));
        $this->assertFalse(Schema::hasTable('rol_usuario'));
        $this->assertFalse(Schema::hasTable('permiso_rol'));

        $this->artisan('migrate', [
            '--database' => DB::getDefaultConnection(),
            '--force' => true,
        ])->assertSuccessful();
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('roles', 3);
    }

    private function usuarioConRol(RolCodigo $codigo): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach($this->rol($codigo));

        return $user;
    }

    private function rol(RolCodigo $codigo): Rol
    {
        return Rol::where('codigo', $codigo->value)->sole();
    }
}
