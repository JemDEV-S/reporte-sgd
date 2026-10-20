<?php

namespace Tests\Feature;

use App\Enums\PermisoCodigo;
use App\Enums\RolCodigo;
use App\Models\DependenciaCaptura;
use App\Models\Permiso;
use App\Models\Publicacion;
use App\Models\Rol;
use App\Models\User;
use App\Reportes\PendientesDependencia\MotorPendientesDependencia;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PublicacionSinteticaSeeder;
use Tests\DatabaseTestCase;

/**
 * Modal de integrantes activos y conciliación de sus pendientes (§5). Cubre el
 * padrón (incluidos los de saldo cero), el grupo "otras asignaciones", la
 * identidad de conciliación, los endpoints y sus permisos/validaciones.
 */
class IntegrantesModalTest extends DatabaseTestCase
{
    // --- Motor: padrón y conciliación ---------------------------------------

    public function test_el_padron_incluye_integrantes_activos_sin_pendientes(): void
    {
        $this->sembrar();
        $padron = $this->motor()->padron('00021', porPagina: 100);

        // Abastecimiento tiene un integrante activo de saldo cero sembrado.
        $sinPendientes = collect($padron->integrantes->items())
            ->first(fn ($i) => $i->total === 0);

        $this->assertNotNull($sinPendientes, 'El padrón debe incluir al integrante de saldo cero.');
        $this->assertTrue($sinPendientes->enPadron);
    }

    public function test_la_suma_del_padron_y_otras_asignaciones_iguala_el_total_del_detalle(): void
    {
        $this->sembrar();
        $motor = $this->motor();

        // Oficina con incidencias: encargado (4) + inactivo fuera del padrón (3)
        // + destinatario nulo (2) = 9 destinos.
        $padron = $motor->padron('00099', porPagina: 100);

        $this->assertSame($motor->contarDetalle('00099'), $padron->totalDetalle);
        $this->assertSame($padron->totalDetalle, $padron->totalIntegrantes + $padron->totalOtras);
        $this->assertTrue($padron->concilia());
        $this->assertSame(9, $padron->totalDetalle);
        $this->assertSame(4, $padron->totalIntegrantes);
        $this->assertSame(5, $padron->totalOtras);
    }

    public function test_otras_asignaciones_separa_al_destinatario_fuera_del_padron_y_al_nulo(): void
    {
        $this->sembrar();
        $padron = $this->motor()->padron('00099', porPagina: 100);

        $otras = collect($padron->otrasAsignaciones);

        // Un destinatario inactivo fuera del padrón, con pendientes.
        $fueraDelPadron = $otras->first(fn ($o) => ! $o->esSinDestinatario);
        $this->assertNotNull($fueraDelPadron);
        $this->assertFalse($fueraDelPadron->enPadron);
        $this->assertSame(3, $fueraDelPadron->total);

        // El grupo de destinatario nulo, sin equipararlo a una persona.
        $sinDestinatario = $otras->first(fn ($o) => $o->esSinDestinatario);
        $this->assertNotNull($sinDestinatario);
        $this->assertNull($sinDestinatario->codigo);
        $this->assertSame(2, $sinDestinatario->total);
        $this->assertSame('Sin destinatario identificado', $sinDestinatario->motivo());
    }

    public function test_el_padron_conserva_las_vias_de_pertenencia(): void
    {
        $this->sembrar();
        $padron = $this->motor()->padron('00099', porPagina: 100);

        // El integrante de saldo cero pertenece SOLO por la vía "asignada".
        $asignado = collect($padron->integrantes->items())
            ->first(fn ($i) => $i->vias === ['asignada']);

        $this->assertNotNull($asignado, 'Debe conservarse la vía de pertenencia "asignada".');
        $this->assertSame(0, $asignado->total);
    }

    public function test_el_empleado_inactivo_no_pertenece_al_padron(): void
    {
        $this->sembrar();
        $motor = $this->motor();
        $padron = $motor->padron('00099', porPagina: 100);

        $inactivo = collect($padron->otrasAsignaciones)
            ->first(fn ($o) => ! $o->esSinDestinatario);

        $this->assertFalse($motor->enPadron('00099', $inactivo->codigo));
    }

    // --- Endpoints: permisos y fragmentos -----------------------------------

    public function test_el_endpoint_de_integrantes_requiere_permiso_personal(): void
    {
        $this->sembrar();
        // El analista tiene ámbito institucional pero NO datos.ver_personales.
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia.integrantes', '00021'))
            ->assertForbidden();
    }

    public function test_el_endpoint_de_integrantes_renderiza_el_padron_con_permiso(): void
    {
        $this->sembrar();
        $usuario = $this->usuarioConDatosPersonales();

        $respuesta = $this->actingAs($usuario)
            ->get(route('reportes.pendientes-dependencia.integrantes', '00099'));

        $respuesta->assertOk()
            ->assertSee('Padrón activo')
            ->assertSee('Otras asignaciones pendientes')
            ->assertSee('Sin destinatario identificado');

        // Es un FRAGMENTO para inyectar en el modal: no debe traer el layout
        // (si trajera <!DOCTYPE>/<html>, el JS mostraría una página sin estilos).
        $respuesta->assertDontSee('<!DOCTYPE', false)
            ->assertDontSee('<html', false);
    }

    public function test_el_endpoint_de_persona_lista_los_pendientes_de_un_integrante(): void
    {
        $this->sembrar();
        $usuario = $this->usuarioConDatosPersonales();
        $encargado = $this->encargadoDe('00099');

        $this->actingAs($usuario)
            ->get(route('reportes.pendientes-dependencia.integrantes.persona', [
                'dependencia' => '00099',
                'empleado' => $encargado,
            ]))
            ->assertOk()
            ->assertSee('Volver a integrantes')
            ->assertSee('Asignaciones pendientes');
    }

    public function test_una_persona_ajena_al_padron_sin_pendientes_da_404(): void
    {
        $this->sembrar();
        $usuario = $this->usuarioConDatosPersonales();

        $this->actingAs($usuario)
            ->get(route('reportes.pendientes-dependencia.integrantes.persona', [
                'dependencia' => '00099',
                'empleado' => '999999', // No existe en el padrón ni tiene pendientes.
            ]))
            ->assertNotFound();
    }

    public function test_el_grupo_sin_destinatario_lista_los_pendientes_nulos(): void
    {
        $this->sembrar();
        $usuario = $this->usuarioConDatosPersonales();

        $this->actingAs($usuario)
            ->get(route('reportes.pendientes-dependencia.integrantes.persona', [
                'dependencia' => '00099',
                'empleado' => '00000',
                'grupo' => 'sin_destinatario',
            ]))
            ->assertOk()
            ->assertSee('Sin destinatario identificado');
    }

    public function test_un_usuario_sin_ambito_institucional_no_accede_a_integrantes(): void
    {
        $this->sembrar();
        $usuario = $this->usuarioConRol(RolCodigo::UsuarioDependencia);

        $this->actingAs($usuario)
            ->get(route('reportes.pendientes-dependencia.integrantes', '00021'))
            ->assertForbidden();
    }

    public function test_la_pantalla_de_detalle_ofrece_ver_integrantes_con_permiso(): void
    {
        $this->sembrar();

        // Sin permiso personal, el enlace no aparece.
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);
        $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia.detalle', '00099'))
            ->assertOk()
            ->assertDontSee('Ver integrantes');

        // Con permiso personal, sí.
        $usuario = $this->usuarioConDatosPersonales();
        $this->actingAs($usuario)
            ->get(route('reportes.pendientes-dependencia.detalle', '00099'))
            ->assertOk()
            ->assertSee('Ver integrantes');
    }

    // --- Apoyos -------------------------------------------------------------

    private function motor(): MotorPendientesDependencia
    {
        return new MotorPendientesDependencia(Publicacion::vigente());
    }

    private function encargadoDe(string $codigo): string
    {
        return (string) DependenciaCaptura::query()
            ->where('publicacion_id', Publicacion::vigente()->id)
            ->where('codigo', $codigo)
            ->value('encargado_codigo');
    }

    private function sembrar(): void
    {
        $this->seed(PublicacionSinteticaSeeder::class);
    }

    private function usuarioConRol(RolCodigo $codigo): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Rol::where('codigo', $codigo->value)->sole());

        return $user;
    }

    /**
     * Usuario institucional al que su rol le concede, además, datos.ver_personales
     * y datos.ver_asuntos. Modela la decisión de producto (§4/§7.3) de habilitar
     * esos campos a un rol concreto, sin otorgarlos a todos los analistas.
     */
    private function usuarioConDatosPersonales(): User
    {
        $user = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);
        $rol = $user->roles()->sole();
        $rol->permisos()->syncWithoutDetaching([
            Permiso::where('codigo', PermisoCodigo::VerDatosPersonales->value)->sole()->id,
            Permiso::where('codigo', PermisoCodigo::VerAsuntos->value)->sole()->id,
        ]);

        return $user;
    }
}
