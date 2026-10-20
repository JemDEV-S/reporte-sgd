<?php

namespace Tests\Feature;

use App\Enums\RolCodigo;
use App\Models\Dependencia;
use App\Models\DestinoCaptura;
use App\Models\Publicacion;
use App\Models\Rol;
use App\Models\User;
use App\Reportes\PendientesDependencia\ContextoReporte;
use App\Reportes\PendientesDependencia\EvolucionPendientes;
use App\Reportes\PendientesDependencia\FiltrosPendientes;
use Database\Seeders\DatabaseSeeder;
use Tests\DatabaseTestCase;

class EvolucionPendientesTest extends DatabaseTestCase
{
    public function test_elige_el_ultimo_corte_diario_de_lima_sin_mezclar_reglas_ni_cortes_futuros(): void
    {
        $this->corte('2026-10-10 10:00:00-05:00', 1);
        $ultimoDelDia = $this->corte('2026-10-10 23:30:00-05:00', 3);
        $this->corte('2026-10-11 10:00:00-05:00', 15, ['version_reglas' => 'otra']);
        $this->corte('2026-10-11 11:00:00-05:00', 12, ['perfil' => 'otro']);
        $this->corte('2026-10-11 12:00:00-05:00', 10, ['estado' => 'construyendo']);
        $this->corte('2026-08-01 10:00:00-05:00', 8);
        $actual = $this->corte('2026-10-12 10:00:00-05:00', 2, ['estado' => 'vigente']);
        $this->corte('2026-10-13 10:00:00-05:00', 6);

        $serie = $this->serie($actual);

        $this->assertSame(['2026-10-10', '2026-10-12'], array_column($serie, 'fecha'));
        $this->assertSame([3, 2], array_column($serie, 'total'));
        $this->assertSame('10/10/2026 23:30', $serie[0]['corte']);
        $this->assertNotSame($actual->id, $ultimoDelDia->id);
    }

    public function test_el_historico_respeta_estado_bandeja_prioridad_y_dependencia(): void
    {
        $actual = $this->corte('2026-10-12 10:00:00-05:00', 2, ['estado' => 'vigente']);
        $base = $actual->destinos()->first();
        foreach ([['estado_recepcion' => '1'], ['prioridad' => '3'], ['dependencia_codigo' => '00002'], ['destinatario_empleado' => 'otro'], ['activo' => false], ['tipo_destino' => '02']] as $indice => $cambio) {
            $fila = $base->replicate();
            $fila->fill(['destino' => 'extra-'.$indice, ...$cambio])->save();
        }

        $this->assertSame([2], array_column($this->serie($actual, new FiltrosPendientes(
            prioridad: '1', estadoRecepcion: '0', bandeja: 'entrada', dependenciasAutorizadas: ['00001'],
        )), 'total'));
        $this->assertSame([0], array_column($this->serie($actual, new FiltrosPendientes(dependenciasAutorizadas: [])), 'total'));
    }

    public function test_busca_sin_tildes_y_la_exportacion_general_coincide_con_el_filtro(): void
    {
        $actual = $this->prepararPantalla();
        $parametros = ['buscar' => 'ALCALDIA', 'estado' => '0', 'publicacion' => $actual->id];

        $this->get(route('reportes.pendientes-dependencia', $parametros))
            ->assertOk()->assertViewHas('resultados', fn ($filas) => count($filas) === 1 && $filas[0]->codigo === '00001')
            ->assertSee('estado=0', false);
        $this->get(route('reportes.pendientes-dependencia.general', $parametros))
            ->assertOk()->assertSee('Total de resultados')->assertDontSee('Oficina de prueba');
        $respuesta = $this->get(route('reportes.pendientes-dependencia.general.exportar', $parametros))->assertOk();
        $csv = $respuesta->streamedContent();
        $this->assertStringContainsString('Alcaldía', $csv);
        $this->assertStringContainsString('TOTAL DE RESULTADOS', $csv);
        $this->assertStringContainsString('Búsqueda de dependencia', $csv);
        $this->assertStringNotContainsString('Oficina de prueba', $csv);
    }

    public function test_busqueda_sin_resultados_y_parametros_invalidos_tienen_respuesta_clara(): void
    {
        $this->prepararPantalla();
        $this->get(route('reportes.pendientes-dependencia.general', ['buscar' => 'inexistente']))
            ->assertOk()->assertSee('No hay dependencias que coincidan');
        $this->getJson(route('reportes.pendientes-dependencia', ['buscar' => ['incorrecto']]))->assertUnprocessable();
        $this->getJson(route('reportes.pendientes-dependencia', ['periodo' => '999']))->assertUnprocessable();
    }

    public function test_serie_sin_datos_con_un_corte_y_con_huecos_se_renderiza_sin_inventar_valores(): void
    {
        $actual = $this->prepararPantalla();
        $this->get(route('reportes.pendientes-dependencia'))->assertOk()->assertSee('El histórico está comenzando');
        $this->corte('2026-10-10 10:00:00-05:00', 4);
        $respuesta = $this->get(route('reportes.pendientes-dependencia'))->assertOk()->assertSee('Ver tabla de valores');
        $this->assertSame([4, 2], array_column($respuesta['evolucion'], 'total'));
        // Available cuts remain connected without introducing intermediate measurements.
        $respuesta->assertSee('<polyline', false);
        $this->assertCount(2, $respuesta['evolucion']);
        $this->corte('2026-10-11 10:00:00-05:00', 3);
        $this->get(route('reportes.pendientes-dependencia'))->assertOk()->assertSee('<polyline', false);
        $html = view('components.evolucion-pendientes', ['puntos' => [], 'dias' => 30, 'subdependencias' => false, 'publicacion' => $actual->id])->render();
        $this->assertStringContainsString('Sin cortes en este período', $html);
    }

    public function test_el_modo_acumulado_suma_las_subdependencias_en_cada_corte(): void
    {
        $this->prepararPantalla();
        $padre = Dependencia::where('codigo', '00001')->sole();
        Dependencia::where('codigo', '00002')->update(['dependencia_padre_id' => $padre->id, 'nivel' => 1]);
        $base = Publicacion::vigente()->destinos()->first()->replicate();
        $base->fill(['dependencia_codigo' => '00002', 'destino' => 'hija'])->save();

        $this->get(route('reportes.pendientes-dependencia', ['dependencia' => '00001']))
            ->assertOk()->assertViewHas('evolucion', fn ($serie) => $serie[0]['total'] === 2);
        $this->get(route('reportes.pendientes-dependencia', ['dependencia' => '00001', 'acumulado' => '1']))
            ->assertOk()->assertViewHas('evolucion', fn ($serie) => $serie[0]['total'] === 3);
    }

    public function test_los_agregados_y_la_busqueda_exigen_ambito_de_datos(): void
    {
        $this->prepararPantalla();
        $usuario = auth()->user();
        $rol = $usuario->roles()->first();
        $rol->permisos()->detach($rol->permisos()->where('codigo', 'datos.ver_institucionales')->first()->id);
        $usuario->unsetRelation('roles');

        $this->get(route('reportes.pendientes-dependencia', ['buscar' => 'Alcaldía']))->assertForbidden();
        $this->get(route('reportes.pendientes-dependencia.general'))->assertForbidden();
        $this->get(route('reportes.pendientes-dependencia.general.exportar'))->assertForbidden();
    }

    private function prepararPantalla(): Publicacion
    {
        $this->seed(DatabaseSeeder::class);
        $usuario = User::factory()->create();
        $usuario->roles()->attach(Rol::where('codigo', RolCodigo::AnalistaInstitucional->value)->sole());
        $this->actingAs($usuario);
        Dependencia::create(['codigo' => '00001', 'nombre' => 'Alcaldía', 'nivel' => 0, 'activa' => true]);
        Dependencia::create(['codigo' => '00002', 'nombre' => 'Oficina de prueba', 'nivel' => 0, 'activa' => true]);

        return $this->corte('2026-10-12 10:00:00-05:00', 2, ['estado' => 'vigente']);
    }

    private function corte(string $fecha, int $total, array $cambios = []): Publicacion
    {
        $publicacion = Publicacion::create([
            'fecha_publicacion' => $fecha, 'perfil' => 'institucional', 'version_reglas' => 'R01-R12',
            'estado' => 'historica', ...$cambios,
        ]);
        for ($i = 0; $i < $total; $i++) {
            DestinoCaptura::create([
                'publicacion_id' => $publicacion->id, 'anio' => '2026', 'emision' => (string) ($i + 1),
                'destino' => '1', 'tipo_destino' => '01', 'dependencia_codigo' => '00001',
                'destinatario_empleado' => 'empleado', 'encargado_empleado' => 'empleado',
                'estado_recepcion' => '0', 'activo' => true, 'prioridad' => '1',
            ]);
        }

        return $publicacion;
    }

    private function serie(Publicacion $actual, ?FiltrosPendientes $filtros = null): array
    {
        return (new EvolucionPendientes)->obtener(
            ContextoReporte::institucional($actual, $filtros ?? new FiltrosPendientes), ['00001'], 30,
        );
    }
}
