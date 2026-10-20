<?php

namespace Tests\Feature;

use App\Enums\RolCodigo;
use App\Informes\EvidenciaInforme;
use App\Informes\GeneradorInforme;
use App\Models\Informe;
use App\Models\Permiso;
use App\Models\Publicacion;
use App\Models\Rol;
use App\Models\User;
use App\Reportes\PendientesDependencia\MotorPendientesDependencia;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PublicacionSinteticaSeeder;
use Tests\DatabaseTestCase;

class InformeTest extends DatabaseTestCase
{
    public function test_la_evidencia_toma_las_cifras_verificadas_del_motor(): void
    {
        $this->sembrar();
        $publicacion = Publicacion::vigente();

        $evidencia = EvidenciaInforme::institucional($publicacion);
        $totalMotor = (new MotorPendientesDependencia($publicacion))->resumen()->totales()->total();

        // El total del informe coincide con el total institucional del reporte.
        $this->assertSame($totalMotor, (int) $evidencia->valor('resumen')['total']);

        // El resumen por nivel reparte exactamente el total (partición sin pérdida).
        $sumaNiveles = collect($evidencia->valor('por_nivel'))->sum('total');
        $this->assertSame($totalMotor, (int) $sumaNiveles);
    }

    public function test_el_ranking_separa_entrada_y_delegados_sin_perder_el_total(): void
    {
        $this->sembrar();
        $evidencia = EvidenciaInforme::institucional(Publicacion::vigente());

        foreach ($evidencia->valor('ranking') as $fila) {
            // Cada fila reparte su total en las cuatro celdas base
            // (entrada/delegados × no leído/sin atender), sin pérdida.
            $suma = (int) $fila['entrada_no_leidos'] + (int) $fila['entrada_sin_atender']
                + (int) $fila['delegados_no_leidos'] + (int) $fila['delegados_sin_atender'];
            $this->assertSame(
                (int) $fila['total'],
                $suma,
                "La fila {$fila['nombre']} no cuadra el desglose por bandeja y estado con el total.",
            );

            // Y los subtotales por bandeja cuadran con el desglose.
            $this->assertSame(
                (int) $fila['entrada'],
                (int) $fila['entrada_no_leidos'] + (int) $fila['entrada_sin_atender'],
            );
            $this->assertSame(
                (int) $fila['delegados'],
                (int) $fila['delegados_no_leidos'] + (int) $fila['delegados_sin_atender'],
            );
        }
    }

    public function test_la_generacion_es_idempotente_por_publicacion(): void
    {
        $this->sembrar();
        $autor = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);
        $publicacion = Publicacion::vigente();

        $primero = (new GeneradorInforme)->generar($publicacion, $autor);
        $segundo = (new GeneradorInforme)->generar($publicacion, $autor);

        $this->assertTrue($primero->is($segundo));
        $this->assertSame(1, Informe::count());
    }

    public function test_generar_crea_un_borrador_con_el_encabezado_dado(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $this->actingAs($analista)
            ->post(route('informes.generar'), [
                'numero' => 'N° 000200-2026-MDSJ/OTI',
                'destinatario' => 'Gerente Municipal',
                'remitente' => 'Director OTI',
            ])
            ->assertRedirect();

        $informe = Informe::firstWhere('numero', 'N° 000200-2026-MDSJ/OTI');
        $this->assertNotNull($informe);
        $this->assertTrue($informe->esBorrador());
        $this->assertSame('Gerente Municipal', $informe->destinatario);
    }

    public function test_un_analista_sin_permiso_de_aprobar_no_puede_aprobar(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);
        $informe = (new GeneradorInforme)->generar(Publicacion::vigente(), $analista);

        // El analista genera y ve, pero no tiene informes.aprobar.
        $this->actingAs($analista)
            ->post(route('informes.aprobar', $informe))
            ->assertForbidden();

        $this->assertTrue($informe->fresh()->esBorrador());
    }

    public function test_un_aprobador_cierra_el_informe_registrando_autor_y_fecha(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);
        $aprobador = $this->usuarioConRol(RolCodigo::AdministradorTecnico);
        // El administrador técnico base no aprueba; le damos el permiso directo al rol.
        $this->otorgarAprobacion($aprobador);

        $informe = (new GeneradorInforme)->generar(Publicacion::vigente(), $analista);

        $this->actingAs($aprobador)
            ->post(route('informes.aprobar', $informe))
            ->assertRedirect();

        $informe->refresh();
        $this->assertTrue($informe->esAprobado());
        $this->assertSame($aprobador->id, $informe->aprobado_por);
        $this->assertNotNull($informe->aprobado_en);
    }

    public function test_la_descarga_word_entrega_un_docx_valido(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);
        $informe = (new GeneradorInforme)->generar(Publicacion::vigente(), $analista);

        $respuesta = $this->actingAs($analista)->get(route('informes.descargar', $informe));

        $respuesta->assertOk();
        $respuesta->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
        // Un .docx es un ZIP: empieza con la firma 'PK'.
        $this->assertSame('PK', substr($respuesta->streamedContent(), 0, 2));
    }

    public function test_la_lista_de_informes_exige_el_permiso_de_ver(): void
    {
        $this->sembrar();
        // El administrador técnico base no tiene informes.ver.
        $adminTecnico = $this->usuarioConRol(RolCodigo::AdministradorTecnico);

        $this->actingAs($adminTecnico)
            ->get(route('informes.index'))
            ->assertForbidden();
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

    private function otorgarAprobacion(User $user): void
    {
        $rol = $user->roles()->first();
        // Un aprobador también ve los informes que aprueba.
        $permisos = Permiso::whereIn('codigo', ['informes.ver', 'informes.aprobar'])->pluck('id');
        $rol->permisos()->syncWithoutDetaching($permisos->all());
    }
}
