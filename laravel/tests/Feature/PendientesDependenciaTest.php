<?php

namespace Tests\Feature;

use App\Enums\RolCodigo;
use App\Models\Publicacion;
use App\Models\Rol;
use App\Models\User;
use App\Reportes\PendientesDependencia\FiltrosPendientes;
use App\Reportes\PendientesDependencia\MotorPendientesDependencia;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PublicacionSinteticaSeeder;
use Tests\DatabaseTestCase;

class PendientesDependenciaTest extends DatabaseTestCase
{
    public function test_el_motor_reproduce_las_cifras_conocidas_de_abastecimiento(): void
    {
        $resumen = $this->resumen();
        $ab = $resumen->fila('00021');

        $this->assertNotNull($ab);
        $this->assertSame(43, $ab->entradaNoLeidos);
        $this->assertSame(16, $ab->entradaRecibidos);
        $this->assertSame(304, $ab->delegadosNoLeidos);
        $this->assertSame(299, $ab->delegadosRecibidos);
        $this->assertSame(662, $ab->total());
    }

    public function test_las_prioridades_son_subconjunto_del_total_no_un_sumando(): void
    {
        $ab = $this->resumen()->fila('00021');

        $suma = $ab->prioridadNormal + $ab->prioridadUrgente + $ab->prioridadMuyUrgente;
        $this->assertSame($ab->total(), $suma);
    }

    public function test_el_total_institucional_coincide_con_los_destinos_capturados(): void
    {
        $resumen = $this->resumen();
        $publicacion = Publicacion::vigente();

        $destinosPendientes = $publicacion->destinos()
            ->where('tipo_destino', '01')
            ->where('activo', true)
            ->whereIn('estado_recepcion', ['0', '1'])
            ->count();

        $this->assertSame($destinosPendientes, $resumen->totales()->total());
    }

    public function test_el_acumulado_de_un_padre_suma_su_propia_cifra_y_la_de_sus_hijas(): void
    {
        $resumen = $this->resumen();
        $gerencia = $resumen->fila('00020'); // padre de 00021, 00022, 00023

        $sumaHijas = collect(['00021', '00022', '00023'])
            ->sum(fn (string $c) => $resumen->fila($c)->accTotal());

        $this->assertSame($gerencia->total() + $sumaHijas, $gerencia->accTotal());
    }

    public function test_el_filtro_de_bandeja_aplica_el_mismo_universo_que_las_cifras(): void
    {
        $this->sembrar();
        $publicacion = Publicacion::vigente();

        $soloEntrada = (new MotorPendientesDependencia(
            $publicacion,
            new FiltrosPendientes(bandeja: 'entrada'),
        ))->resumen()->fila('00021');

        // Con el filtro de entrada, la bandeja de delegados queda en cero.
        $this->assertSame(0, $soloEntrada->delegadosNoLeidos);
        $this->assertSame(0, $soloEntrada->delegadosRecibidos);
        $this->assertSame(59, $soloEntrada->total());
    }

    public function test_la_ruta_del_reporte_exige_permiso_de_reportes(): void
    {
        $this->sembrar();
        // El administrador técnico accede a la plataforma pero no tiene reportes.ver.
        $adminTecnico = $this->usuarioConRol(RolCodigo::AdministradorTecnico);

        $this->actingAs($adminTecnico)
            ->get(route('reportes.pendientes-dependencia'))
            ->assertForbidden();
    }

    public function test_la_ruta_cierra_la_sesion_de_quien_no_accede_a_la_plataforma(): void
    {
        $this->sembrar();
        $sinAcceso = User::factory()->create();

        $this->actingAs($sinAcceso)
            ->get(route('reportes.pendientes-dependencia'))
            ->assertRedirect(route('login'));
    }

    public function test_el_resumen_se_renderiza_para_un_analista(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia'))
            ->assertOk()
            ->assertSee('Pendientes por dependencia')
            ->assertSee('Total institucional');
    }

    public function test_el_drill_down_a_abastecimiento_muestra_sus_cifras(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia', ['dependencia' => '00021']))
            ->assertOk()
            ->assertSee('Subgerencia de Abastecimiento');
    }

    public function test_por_defecto_el_foco_muestra_solo_las_cifras_propias(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        // Gerencia de Admin. y Finanzas: propias 17, acumulado 757.
        $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia', ['dependencia' => '00020']))
            ->assertOk()
            ->assertSee('Solo las cifras propias de esta dependencia')
            ->assertDontSee('Incluye las cifras de sus subdependencias');
    }

    public function test_el_modo_acumulado_incluye_la_descendencia(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia', ['dependencia' => '00020', 'acumulado' => '1']))
            ->assertOk()
            ->assertSee('Incluye las cifras de sus subdependencias')
            ->assertSee('Acumulado con descendencia');
    }

    public function test_el_filtro_de_bandeja_desde_la_url_restringe_el_universo(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        // Solo entrada en Abastecimiento: total 59 (43 + 16); delegados quedan en cero.
        $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia', ['dependencia' => '00021', 'bandeja' => 'entrada']))
            ->assertOk()
            ->assertSee('59')
            ->assertDontSee('662'); // El total sin filtro no debe aparecer.
    }

    public function test_el_detalle_lista_destinos_y_la_antiguedad_suma_el_total(): void
    {
        $this->sembrar();
        $detalle = (new MotorPendientesDependencia(Publicacion::vigente()))->detalle('00021');

        $this->assertSame('Subgerencia de Abastecimiento', $detalle->nombre());
        $this->assertSame(662, $detalle->totalDestinos());
        // La distribución de antigüedad cubre exactamente todos los destinos.
        $this->assertSame(662, array_sum($detalle->distribucionAntiguedad));
    }

    public function test_el_detalle_respeta_los_filtros_del_resumen(): void
    {
        $this->sembrar();
        $filtros = new FiltrosPendientes(bandeja: 'entrada');
        $detalle = (new MotorPendientesDependencia(Publicacion::vigente(), $filtros))->detalle('00021');

        // Solo entrada en Abastecimiento: 59 destinos (43 + 16).
        $this->assertSame(59, $detalle->totalDestinos());
    }

    public function test_la_ruta_de_detalle_se_renderiza_para_un_analista(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia.detalle', '00021'))
            ->assertOk()
            ->assertSee('Subgerencia de Abastecimiento')
            ->assertSee('Antigüedad de los pendientes');
    }

    public function test_la_exportacion_exige_el_permiso_de_exportar(): void
    {
        $this->sembrar();
        // El usuario por dependencia ve reportes pero el administrador técnico
        // no; usamos a este último, que ni siquiera supera can:reportes.ver.
        $adminTecnico = $this->usuarioConRol(RolCodigo::AdministradorTecnico);

        $this->actingAs($adminTecnico)
            ->get(route('reportes.pendientes-dependencia.exportar', '00021'))
            ->assertForbidden();
    }

    public function test_la_exportacion_descarga_un_csv_con_los_destinos_del_detalle(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $respuesta = $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia.exportar', '00021'));

        $respuesta->assertOk();
        $respuesta->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'pendientes_00021_',
            $respuesta->headers->get('content-disposition'),
        );

        // El cuerpo tiene una fila de datos por cada destino del detalle, más la
        // cabecera de columnas: las cifras del archivo igualan a las de pantalla.
        $cuerpo = $respuesta->streamedContent();
        $detalle = (new MotorPendientesDependencia(Publicacion::vigente()))->detalle('00021');
        $filasDatos = substr_count($cuerpo, "\n") - $this->lineasCabecera();
        $this->assertSame($detalle->totalDestinos(), $filasDatos);
    }

    public function test_la_exportacion_respeta_los_filtros_igual_que_el_detalle(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $respuesta = $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia.exportar', ['dependencia' => '00021', 'bandeja' => 'entrada']));

        $respuesta->assertOk();
        $cuerpo = $respuesta->streamedContent();
        $filasDatos = substr_count($cuerpo, "\n") - $this->lineasCabecera();

        // Solo entrada en Abastecimiento: 59 destinos, igual que el detalle.
        $this->assertSame(59, $filasDatos);
    }

    public function test_el_detalle_muestra_numero_documental_legible_distinto_de_la_clave_tecnica(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia.detalle', '00021'))
            ->assertOk()
            // Encabezados de las columnas nuevas.
            ->assertSee('N.º de documento')
            ->assertSee('Asignaciones pendientes')
            // Número documental sintético con ceros y sigla de la dependencia.
            ->assertSee('-2026-DEP21')
            // La clave técnica sigue disponible, como información secundaria.
            ->assertSee('Clave técnica');
    }

    public function test_el_detalle_restringe_asunto_y_datos_personales_sin_permiso(): void
    {
        $this->sembrar();
        // El analista institucional tiene ámbito institucional, pero NO
        // datos.ver_asuntos ni datos.ver_personales.
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $respuesta = $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia.detalle', '00021'));

        $respuesta->assertOk()
            ->assertSee('Restringido')
            // El asunto sintético nunca se serializa sin permiso.
            ->assertDontSee('Asunto de prueba del documento');
    }

    public function test_la_exportacion_omite_columnas_sensibles_sin_permiso(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $cuerpo = $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia.exportar', '00021'))
            ->streamedContent();

        // Sin permisos de asunto ni personales, esas columnas no aparecen.
        $this->assertStringNotContainsString('Asunto', $cuerpo);
        $this->assertStringNotContainsString('Destinatario (nombre)', $cuerpo);
        // El ámbito de campos queda documentado en los metadatos.
        $this->assertStringContainsString('sin asuntos', $cuerpo);
        $this->assertStringContainsString('sin datos personales', $cuerpo);
    }

    public function test_un_usuario_sin_ambito_institucional_no_accede_al_detalle(): void
    {
        $this->sembrar();
        // UsuarioDependencia ve reportes pero no tiene datos.ver_institucionales;
        // mientras usuario_dependencia no exista, se le deniega el ámbito.
        $usuario = $this->usuarioConRol(RolCodigo::UsuarioDependencia);

        $this->actingAs($usuario)
            ->get(route('reportes.pendientes-dependencia.detalle', '00021'))
            ->assertForbidden();
    }

    public function test_el_detalle_sobre_una_publicacion_en_construccion_no_esta_disponible(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $enConstruccion = Publicacion::create([
            'fecha_publicacion' => now(),
            'perfil' => 'institucional',
            'version_reglas' => 'R01-R12',
            'estado' => 'construyendo',
        ]);

        $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia.detalle', ['dependencia' => '00021', 'publicacion' => $enConstruccion->id]))
            ->assertNotFound();
    }

    /**
     * Líneas del encabezado del CSV que no son filas de datos: los metadatos,
     * la línea en blanco de separación y la fila de nombres de columnas.
     */
    private function lineasCabecera(): int
    {
        return 10 /* metadatos */ + 1 /* separador */ + 1 /* columnas */;
    }

    public function test_el_reporte_general_lista_todas_las_dependencias_para_un_analista(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia.general'))
            ->assertOk()
            ->assertSee('Pendientes por dependencia — general')
            ->assertSee('Total institucional')
            ->assertSee('Subgerencia de Abastecimiento');
    }

    public function test_la_exportacion_general_csv_suma_el_total_institucional(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $respuesta = $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia.general.exportar'));

        $respuesta->assertOk();
        $respuesta->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $cuerpo = $respuesta->streamedContent();
        $resumen = (new MotorPendientesDependencia(Publicacion::vigente()))->resumen();

        // El archivo contiene una fila de datos por dependencia más la de totales.
        $filasDatos = substr_count($cuerpo, "\n") - $this->lineasCabeceraGeneral();
        $this->assertSame(count($resumen->filas) + 1 /* totales */, $filasDatos);
        $this->assertStringContainsString('TOTAL INSTITUCIONAL', $cuerpo);
    }

    public function test_la_exportacion_general_xlsx_descarga_un_archivo_excel(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $respuesta = $this->actingAs($analista)
            ->get(route('reportes.pendientes-dependencia.general.exportar', ['formato' => 'xlsx']));

        $respuesta->assertOk();
        $respuesta->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $this->assertStringContainsString(
            'pendientes_general_',
            $respuesta->headers->get('content-disposition'),
        );

        // El cuerpo es un .xlsx real: un ZIP cuyo primer bloque empieza con 'PK'.
        $cuerpo = $respuesta->streamedContent();
        $this->assertSame('PK', substr($cuerpo, 0, 2));
    }

    public function test_la_exportacion_general_exige_el_permiso_de_exportar(): void
    {
        $this->sembrar();
        $adminTecnico = $this->usuarioConRol(RolCodigo::AdministradorTecnico);

        $this->actingAs($adminTecnico)
            ->get(route('reportes.pendientes-dependencia.general.exportar'))
            ->assertForbidden();
    }

    /** Encabezado del CSV general: 9 metadatos + separador + columnas. */
    private function lineasCabeceraGeneral(): int
    {
        return 9 + 1 + 1;
    }

    private function resumen()
    {
        $this->sembrar();

        return (new MotorPendientesDependencia(Publicacion::vigente()))->resumen();
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
}
