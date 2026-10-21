<?php

namespace Tests\Feature;

use App\Administracion\RegistrarAuditoria;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\DatabaseTestCase;

class AdministracionTest extends DatabaseTestCase
{
    private function administrador(): User
    {
        $this->seed(DatabaseSeeder::class);
        $usuario = User::factory()->create();
        $usuario->roles()->attach(Rol::where('codigo', 'administrador_tecnico')->sole());

        return $usuario;
    }

    private function datos(User $usuario, array $cambios = []): array
    {
        return array_replace([
            'dni' => $usuario->dni,
            'nombre' => $usuario->nombre,
            'apellidos' => $usuario->apellidos,
            'email' => $usuario->email,
            'activo' => 1,
            'roles' => $usuario->roles()->pluck('roles.id')->all(),
        ], $cambios);
    }

    public function test_rutas_protegidas_y_navegacion_administrativa(): void
    {
        $admin = $this->administrador();
        $this->get(route('admin.usuarios.index'))->assertRedirect(route('login'));
        $analista = User::factory()->create();
        $analista->roles()->attach(Rol::where('codigo', 'analista_institucional')->sole());
        $this->actingAs($analista)->get(route('admin.usuarios.index'))->assertForbidden();
        $this->actingAs($analista)->post(route('admin.roles.store'), [])->assertForbidden();
        $this->actingAs($analista)->get(route('admin.auditoria'))->assertForbidden();
        $this->actingAs($admin)->get(route('home'))->assertOk()->assertSee('Administración', false);
        foreach (['admin.usuarios.index', 'admin.usuarios.create', 'admin.roles.index', 'admin.roles.create', 'admin.auditoria'] as $ruta) {
            $this->get(route($ruta))->assertOk();
        }
    }

    public function test_crear_usuario_normaliza_y_audita_sin_exponer_credenciales(): void
    {
        $admin = $this->administrador();
        $rol = Rol::where('codigo', 'analista_institucional')->sole();
        $this->actingAs($admin)->post(route('admin.usuarios.store'), [
            'dni' => '00112233', 'nombre' => '  Ana   María ', 'apellidos' => 'Pérez',
            'email' => ' ANA@EXAMPLE.COM ', 'activo' => 1, 'roles' => [$rol->id],
            'password' => 'ClaveSegura12345', 'password_confirmation' => 'ClaveSegura12345',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $usuario = User::where('dni', '00112233')->sole();
        $this->assertSame('ana@example.com', $usuario->email);
        $this->assertSame('Ana María', $usuario->nombre);
        $this->assertTrue(Hash::check('ClaveSegura12345', $usuario->password));
        $this->assertTrue($usuario->tienePermiso('reportes.ver'));
        $evento = DB::table('eventos_auditoria')->sole();
        $this->assertSame($admin->id, $evento->user_id);
        $this->assertStringNotContainsString('ClaveSegura', $evento->contexto);
        $this->assertStringNotContainsString($usuario->password, $evento->contexto);
        $this->get(route('admin.usuarios.edit', $usuario))->assertOk()->assertSee('ana@example.com')->assertDontSee($usuario->password);
        $this->get(route('admin.auditoria'))->assertOk()->assertSee('usuario.creado');
    }

    public function test_validacion_evitar_duplicados_roles_inexistentes_y_password_debil(): void
    {
        $admin = $this->administrador();
        $this->actingAs($admin)->post(route('admin.usuarios.store'), $this->datos($admin, [
            'dni' => '123', 'email' => mb_strtoupper($admin->email), 'roles' => [99999],
            'password' => 'corta', 'password_confirmation' => 'otra',
        ]))->assertSessionHasErrors(['dni', 'email', 'roles.0', 'password']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('eventos_auditoria', 0);
        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));
    }

    public function test_impide_desactivar_o_quitar_roles_del_ultimo_administrador(): void
    {
        $admin = $this->administrador();
        foreach ([['activo' => 0], ['roles' => []]] as $cambio) {
            $this->actingAs($admin)->put(route('admin.usuarios.update', $admin), $this->datos($admin, $cambio))
                ->assertSessionHasErrors('administracion');
            $this->assertTrue($admin->fresh()->activo);
            $this->assertTrue($admin->tienePermiso('roles.gestionar'));
        }
        $this->assertDatabaseCount('eventos_auditoria', 0);
    }

    public function test_impide_retirar_permisos_del_ultimo_rol_administrativo(): void
    {
        $admin = $this->administrador();
        $rol = $admin->roles()->sole();
        $this->actingAs($admin)->put(route('admin.roles.update', $rol), [
            'nombre' => 'Cambio rechazado', 'permisos' => [],
        ])->assertSessionHasErrors('administracion');
        $this->assertSame('Administrador técnico', $rol->fresh()->nombre);
        $this->assertTrue($admin->tienePermiso('usuarios.gestionar'));
        $this->assertDatabaseCount('eventos_auditoria', 0);
    }

    public function test_crea_rol_personalizado_y_conserva_codigo_estable(): void
    {
        $admin = $this->administrador();
        $permiso = Permiso::where('codigo', 'reportes.ver')->sole();
        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'codigo' => 'consulta_especial', 'nombre' => 'Consulta especial', 'permisos' => [$permiso->id],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $rol = Rol::where('codigo', 'consulta_especial')->sole();
        $this->get(route('admin.roles.edit', $rol))->assertOk()->assertSee('Consulta especial');
        $this->put(route('admin.roles.update', $rol), [
            'codigo' => 'nuevo_codigo', 'nombre' => 'Otro nombre',
        ])->assertSessionHasErrors('codigo');
        $this->put(route('admin.roles.update', $rol), [
            'nombre' => 'Consulta sin permisos', 'permisos' => [],
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $rol->permisos()->count());
        $this->assertDatabaseCount('eventos_auditoria', 2);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(0, $rol->permisos()->count());
    }

    public function test_admite_relevo_administrativo_y_revoca_cuenta_inactiva(): void
    {
        $admin = $this->administrador();
        $otro = User::factory()->create();
        $otro->roles()->attach($admin->roles()->sole());
        $this->actingAs($admin)->put(route('admin.usuarios.update', $otro), $this->datos($otro, ['activo' => 0]))
            ->assertSessionHasNoErrors();
        $this->actingAs($otro)->get(route('admin.usuarios.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_gestor_de_usuarios_no_puede_asignarse_roles_ni_tomar_cuenta_privilegiada(): void
    {
        $admin = $this->administrador();
        $rol = Rol::create(['codigo' => 'gestor_cuentas', 'nombre' => 'Gestor de cuentas', 'descripcion' => '']);
        $rol->permisos()->sync(Permiso::whereIn('codigo', ['plataforma.acceder', 'usuarios.gestionar'])->pluck('id'));
        $gestor = User::factory()->create();
        $gestor->roles()->attach($rol);
        $this->actingAs($gestor)->put(route('admin.usuarios.update', $admin), $this->datos($admin, [
            'password' => 'TomarCuenta12345', 'password_confirmation' => 'TomarCuenta12345',
        ]))->assertForbidden();
        $normal = User::factory()->create();
        $this->put(route('admin.usuarios.update', $normal), $this->datos($normal, ['roles' => $admin->roles()->pluck('roles.id')->all()]))
            ->assertForbidden();
        $this->assertDatabaseCount('eventos_auditoria', 0);
        $this->put(route('admin.usuarios.update', $normal), $this->datos($normal, ['nombre' => 'Actualizado']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Actualizado', $normal->fresh()->nombre);
    }

    public function test_busqueda_y_estado_filtran_usuarios(): void
    {
        $admin = $this->administrador();
        $usuario = User::factory()->create(['nombre' => 'BusquedaUnica', 'activo' => false]);
        $this->actingAs($admin)->get(route('admin.usuarios.index', ['buscar' => 'busquedaunica', 'estado' => 'inactivos']))
            ->assertOk()->assertSee($usuario->email)->assertDontSee($admin->email);
    }

    public function test_editar_sin_password_conserva_hash_y_restablecer_lo_cambia(): void
    {
        $admin = $this->administrador();
        $usuario = User::factory()->create();
        $hash = $usuario->password;
        $this->actingAs($admin)->put(route('admin.usuarios.update', $usuario), $this->datos($usuario, ['password' => null]))
            ->assertSessionHasNoErrors();
        $this->assertSame($hash, $usuario->fresh()->password);
        $this->put(route('admin.usuarios.update', $usuario), $this->datos($usuario, [
            'password' => 'NuevaClave123456', 'password_confirmation' => 'NuevaClave123456',
        ]))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NuevaClave123456', $usuario->fresh()->password));
    }

    public function test_fallo_de_auditoria_revierte_la_operacion_completa(): void
    {
        $admin = $this->administrador();
        $usuario = User::factory()->create();
        $nombre = $usuario->nombre;
        $this->mock(RegistrarAuditoria::class, function ($mock) {
            $mock->shouldReceive('registrar')->once()->andThrow(new RuntimeException('Auditoría no disponible'));
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->put(route('admin.usuarios.update', $usuario), $this->datos($usuario, ['nombre' => 'Cambio no confirmado']));
            $this->fail('La escritura debió fallar.');
        } catch (RuntimeException $error) {
            $this->assertSame('Auditoría no disponible', $error->getMessage());
        }
        $this->assertSame($nombre, $usuario->fresh()->nombre);
        $this->assertDatabaseCount('eventos_auditoria', 0);
    }

    public function test_retirar_permiso_se_aplica_en_la_siguiente_peticion(): void
    {
        $admin = $this->administrador();
        $otro = User::factory()->create();
        $otro->roles()->attach($admin->roles()->sole());
        $this->actingAs($admin)->put(route('admin.usuarios.update', $otro), $this->datos($otro, ['roles' => []]))
            ->assertSessionHasNoErrors();
        $this->actingAs($otro)->get(route('admin.roles.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_entrada_de_tipos_invalidos_se_rechaza_sin_error_de_servidor(): void
    {
        $admin = $this->administrador();
        $datosInvalidos = [
            'nombre' => ['no es texto'], 'apellidos' => ['no es texto'], 'email' => ['no es texto'],
            'roles' => [['no es un ID']], 'activo' => ['no es booleano'],
        ];
        $this->actingAs($admin)->post(route('admin.usuarios.store'), $datosInvalidos)->assertSessionHasErrors(['nombre', 'apellidos', 'email']);
        $this->withSession(['_old_input' => $datosInvalidos])->get(route('admin.usuarios.create'))->assertOk();
        $this->assertDatabaseCount('eventos_auditoria', 0);
    }
}
