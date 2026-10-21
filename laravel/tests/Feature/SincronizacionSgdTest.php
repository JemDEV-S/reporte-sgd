<?php

namespace Tests\Feature;

use App\Administracion\RegistrarAuditoria;
use App\Ingesta\BloqueoIngesta;
use App\Ingesta\EjecutarSincronizacion;
use App\Ingesta\IngestaIncrementalSgd;
use App\Ingesta\IngestaOcupada;
use App\Ingesta\ProgramarSincronizacion;
use App\Ingesta\ResultadoIncremental;
use App\Jobs\SincronizarSgd;
use App\Models\ConfiguracionSincronizacion;
use App\Models\Publicacion;
use App\Models\Rol;
use App\Models\SincronizacionSgd;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\DatabaseTestCase;

class SincronizacionSgdTest extends DatabaseTestCase
{
    private function administrador(): User
    {
        $this->seed(DatabaseSeeder::class);
        $usuario = User::factory()->create();
        $usuario->roles()->attach(Rol::where('codigo', 'administrador_tecnico')->sole());

        return $usuario;
    }

    public function test_pantalla_y_escrituras_exigen_permiso_de_fuentes(): void
    {
        $admin = $this->administrador();
        $this->get(route('admin.sincronizacion.index'))->assertRedirect(route('login'));
        $otro = User::factory()->create();
        $otro->roles()->attach(Rol::where('codigo', 'analista_institucional')->sole());
        $this->actingAs($otro)->get(route('admin.sincronizacion.index'))->assertForbidden();
        $this->put(route('admin.sincronizacion.update'), [])->assertForbidden();
        $this->post(route('admin.sincronizacion.ejecutar'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.sincronizacion.index'))->assertOk()->assertSee('Sincronizar ahora');
    }

    public function test_configuracion_valida_audita_y_calcula_proxima_fecha(): void
    {
        $admin = $this->administrador();
        $this->travelTo(now()->startOfMinute());
        $this->actingAs($admin)->put(route('admin.sincronizacion.update'), ['automatica' => 1, 'intervalo_minutos' => 5])
            ->assertSessionHasNoErrors()->assertRedirect();
        $config = ConfiguracionSincronizacion::findOrFail(1);
        $this->assertSame(5, $config->intervalo_minutos);
        $this->assertTrue($config->proxima_ejecucion_en->equalTo(now()->addMinutes(5)));
        $this->put(route('admin.sincronizacion.update'), ['automatica' => 1, 'intervalo_minutos' => 2])->assertSessionHasErrors('intervalo_minutos');
        $this->assertDatabaseCount('eventos_auditoria', 1);
        $this->put(route('admin.sincronizacion.update'), ['automatica' => 0, 'intervalo_minutos' => 5])->assertSessionHasNoErrors();
        $this->assertNull($config->fresh()->proxima_ejecucion_en);
        $this->travelBack();
    }

    public function test_boton_encola_sin_ejecutar_ingesta_y_no_duplica_solicitud(): void
    {
        $admin = $this->administrador();
        // La cola global sync no se modifica: esta operación usa database explícitamente.
        $this->assertSame('sync', config('queue.default'));
        $this->mock(IngestaIncrementalSgd::class)->shouldNotReceive('ejecutar');
        $this->actingAs($admin)->post(route('admin.sincronizacion.ejecutar'))->assertSessionHasNoErrors();
        $this->post(route('admin.sincronizacion.ejecutar'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('sincronizaciones_sgd', 1);
        $this->assertDatabaseHas('jobs', ['queue' => 'sgd']);
        $this->assertDatabaseHas('sincronizaciones_sgd', ['estado' => 'pendiente', 'origen' => 'manual', 'user_id' => $admin->id]);
        $this->assertDatabaseCount('eventos_auditoria', 1);
    }

    public function test_scheduler_respeta_frecuencia_pausa_y_reserva_existente(): void
    {
        $this->artisan('sgd:planificar')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        ConfiguracionSincronizacion::whereKey(1)->update(['proxima_ejecucion_en' => now()->subMinute()]);
        $this->artisan('sgd:planificar')->assertSuccessful();
        $this->artisan('sgd:planificar')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('sincronizaciones_sgd', ['origen' => 'automatica']);
        $this->assertNotNull(ConfiguracionSincronizacion::findOrFail(1)->scheduler_visto_en);
        ConfiguracionSincronizacion::whereKey(1)->update(['automatica' => false, 'proxima_ejecucion_en' => now()->subMinute()]);
        $this->artisan('sgd:planificar')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_pausa_permite_sincronizacion_manual(): void
    {
        $admin = $this->administrador();
        ConfiguracionSincronizacion::whereKey(1)->update(['automatica' => false]);
        $this->actingAs($admin)->post(route('admin.sincronizacion.ejecutar'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_worker_publica_resultado_y_reentrega_no_repite_ingesta(): void
    {
        $this->administrador();
        $publicacion = Publicacion::create(['fecha_publicacion' => now(), 'estado' => 'vigente']);
        $this->mock(IngestaIncrementalSgd::class)->shouldReceive('ejecutar')->once()
            ->andReturn(new ResultadoIncremental($publicacion, $publicacion->id, 12, 8, 4, 50));
        $ejecucion = app(ProgramarSincronizacion::class)->solicitar();
        $this->artisan('queue:work', ['connection' => 'ingesta', '--queue' => 'sgd', '--once' => true])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame('exitosa', $ejecucion->fresh()->estado);
        $this->assertNull($ejecucion->fresh()->activa);
        $this->assertSame(50, $ejecucion->fresh()->metricas['destinos']);
        (new SincronizarSgd($ejecucion->id))->handle(app(EjecutarSincronizacion::class));
        $this->get(route('login'))->assertOk();
    }

    public function test_error_se_conserva_en_historial_sin_exponer_secretos_y_permite_reintentar(): void
    {
        $this->mock(IngestaIncrementalSgd::class)->shouldReceive('ejecutar')->once()->andThrow(new RuntimeException('password=secreto; SQL privado'));
        $ejecucion = app(ProgramarSincronizacion::class)->solicitar();
        app(EjecutarSincronizacion::class)->ejecutar($ejecucion->id);
        $this->assertSame('fallida', $ejecucion->fresh()->estado);
        $this->assertStringNotContainsString('secreto', $ejecucion->fresh()->error_resumen);
        $this->assertNull($ejecucion->fresh()->activa);
        $otra = app(ProgramarSincronizacion::class)->solicitar();
        $this->assertNotSame($ejecucion->id, $otra->id);
    }

    public function test_scheduler_recupera_proceso_interrumpido_sin_bloqueo(): void
    {
        $ejecucion = SincronizacionSgd::create(['origen' => 'consola', 'estado' => 'ejecutando', 'activa' => 1, 'inicio_en' => now()->subMinute()]);
        app(ProgramarSincronizacion::class)->recuperarInterrumpidas();
        $this->assertSame('interrumpida', $ejecucion->fresh()->estado);
        $this->assertNull($ejecucion->fresh()->activa);
    }

    public function test_ingesta_activa_no_se_recupera_por_un_tiempo_fijo(): void
    {
        $ejecucion = SincronizacionSgd::create(['origen' => 'consola', 'estado' => 'ejecutando', 'activa' => 1, 'inicio_en' => now()->subHours(3)]);
        $this->mock(BloqueoIngesta::class)->shouldReceive('ejecutar')->once()->andThrow(new IngestaOcupada);
        app(ProgramarSincronizacion::class)->recuperarInterrumpidas();
        $this->assertSame('ejecutando', $ejecucion->fresh()->estado);
    }

    public function test_heartbeat_del_worker_se_limita_a_la_cola_de_ingesta(): void
    {
        event(new Looping('sync', 'default'));
        event(new Looping('ingesta', 'otra'));
        $this->assertNull(ConfiguracionSincronizacion::findOrFail(1)->worker_visto_en);
        event(new Looping('ingesta', 'sgd'));
        $this->assertNotNull(ConfiguracionSincronizacion::findOrFail(1)->worker_visto_en);
    }

    public function test_bloqueo_pgsql_impide_otro_proceso_y_libera_despues(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Bloqueo de sesión específico de PostgreSQL.');
        }
        $config = DB::connection()->getConfig();
        config(['database.connections.competidor_ingesta' => $config]);
        try {
            app(BloqueoIngesta::class)->ejecutar(function () {
                $adquirido = DB::connection('competidor_ingesta')->selectOne("SELECT pg_try_advisory_lock(hashtext(current_database()), hashtext(current_schema() || ':sirmd-ingesta')) AS adquirido")->adquirido;
                $this->assertFalse($adquirido);
            });
            $this->assertTrue(DB::connection('competidor_ingesta')->selectOne("SELECT pg_try_advisory_lock(hashtext(current_database()), hashtext(current_schema() || ':sirmd-ingesta')) AS adquirido")->adquirido);
        } finally {
            DB::purge('competidor_ingesta');
        }
    }

    public function test_error_de_auditoria_revierte_solicitud_y_trabajo_en_la_cola(): void
    {
        $admin = $this->administrador();
        $this->mock(RegistrarAuditoria::class)->shouldReceive('registrar')->once()->andThrow(new RuntimeException('Auditoría no disponible'));
        try {
            app(ProgramarSincronizacion::class)->solicitar($admin);
            $this->fail('La solicitud debió revertirse.');
        } catch (RuntimeException $error) {
            $this->assertSame('Auditoría no disponible', $error->getMessage());
        }
        $this->assertDatabaseCount('sincronizaciones_sgd', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_consola_comparte_reserva_y_rechaza_una_solicitud_activa(): void
    {
        app(ProgramarSincronizacion::class)->solicitar();
        $this->artisan('sgd:ingestar', ['--incremental' => true])->assertFailed();
        $this->assertDatabaseCount('sincronizaciones_sgd', 1);
    }

    public function test_comando_incremental_registra_resultado(): void
    {
        $publicacion = Publicacion::create(['fecha_publicacion' => now(), 'estado' => 'vigente']);
        $this->mock(IngestaIncrementalSgd::class)->shouldReceive('ejecutar')->once()
            ->andReturn(new ResultadoIncremental($publicacion, $publicacion->id, 2, 1, 1, 20));
        $this->artisan('sgd:ingestar', ['--incremental' => true])->assertSuccessful();
        $this->assertDatabaseHas('sincronizaciones_sgd', ['estado' => 'exitosa', 'origen' => 'consola']);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_revocacion_antes_de_procesar_impide_ingesta_manual(): void
    {
        $admin = $this->administrador();
        $ejecucion = app(ProgramarSincronizacion::class)->solicitar($admin);
        $admin->activo = false;
        $admin->save();
        $this->mock(IngestaIncrementalSgd::class)->shouldNotReceive('ejecutar');
        app(EjecutarSincronizacion::class)->ejecutar($ejecucion->id);
        $this->assertSame('fallida', $ejecucion->fresh()->estado);
        $this->assertNull($ejecucion->fresh()->activa);
        $this->assertStringContainsString('autorización', $ejecucion->fresh()->error_resumen);
    }

    public function test_estado_distingue_pendiente_sin_worker_en_curso_y_finalizado(): void
    {
        $admin = $this->administrador();
        $ejecucion = app(ProgramarSincronizacion::class)->solicitar($admin);
        $this->actingAs($admin)->get(route('admin.sincronizacion.index'))
            ->assertOk()->assertSee('Pendiente de procesamiento')->assertSee('todavía no se está sincronizando');
        $response = $this->getJson(route('admin.sincronizacion.estado'))->assertOk()->assertJsonPath('activa', true);
        $this->assertStringContainsString('Pendiente de procesamiento', $response->json('fragmentos.accion'));
        $this->assertStringNotContainsString('name="intervalo_minutos"', implode('', $response->json('fragmentos')));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $ejecucion->update(['estado' => 'ejecutando', 'inicio_en' => now()]);
        $response = $this->getJson(route('admin.sincronizacion.estado'))->assertOk();
        $this->assertStringContainsString('Sincronizando…', $response->json('fragmentos.accion'));
        $ejecucion->update(['estado' => 'exitosa', 'activa' => null, 'fin_en' => now()]);
        $response = $this->getJson(route('admin.sincronizacion.estado'))->assertOk()->assertJsonPath('activa', false);
        $this->assertStringContainsString('Sincronizar ahora', $response->json('fragmentos.accion'));
        $this->assertStringNotContainsString('disabled', $response->json('fragmentos.accion'));
    }

    public function test_seguimiento_exige_permiso_y_respeta_su_revocacion(): void
    {
        $admin = $this->administrador();
        $this->getJson(route('admin.sincronizacion.estado'))->assertUnauthorized();
        $this->actingAs($admin)->getJson(route('admin.sincronizacion.estado'))->assertOk();
        $rol = $admin->roles()->sole();
        $rol->permisos()->detach(DB::table('permisos')->where('codigo', 'fuentes.gestionar')->value('id'));
        $this->getJson(route('admin.sincronizacion.estado'))->assertForbidden();
    }
}
