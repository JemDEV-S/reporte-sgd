<?php

namespace Tests\Feature;

use App\Console\Commands\ComprobarConexionSgd;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ConexionSgdTest extends TestCase
{
    private ComprobarConexionSgd $command;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.sgd.host' => 'servidor-ficticio.invalid',
            'database.connections.sgd.database' => 'IDOSGD_MDSJ',
            'database.connections.sgd.username' => 'lector_ficticio',
            'database.connections.sgd.password' => 'secreto-sintetico-de-prueba',
            'sgd.schema' => 'IDOSGD',
        ]);

        $this->command = new class extends ComprobarConexionSgd
        {
            public bool $driverAvailable = true;

            protected function driverDisponible(): bool
            {
                return $this->driverAvailable;
            }
        };

        $this->app->make(Kernel::class)->registerCommand($this->command);
    }

    public function test_revision_de_configuracion_no_contacta_sql_server(): void
    {
        $this->command->driverAvailable = false;
        config(['database.connections.sgd.host' => '']);
        DB::shouldReceive('connection')->never();

        $this->artisan('sgd:comprobar-conexion', ['--solo-configuracion' => true])
            ->expectsOutput('Driver pdo_sqlsrv: pendiente')
            ->expectsOutput('Variables requeridas: SGD_DB_HOST pendientes')
            ->expectsOutput('No se contactó SQL Server.')
            ->assertSuccessful();
    }

    public function test_credenciales_incompletas_impiden_la_conexion(): void
    {
        config(['database.connections.sgd.password' => '']);
        DB::shouldReceive('connection')->never();

        $this->artisan('sgd:comprobar-conexion')
            ->expectsOutput('Configurar en .env: SGD_DB_PASSWORD.')
            ->assertFailed();
    }

    public function test_driver_ausente_impide_la_conexion(): void
    {
        $this->command->driverAvailable = false;
        DB::shouldReceive('connection')->never();

        $this->artisan('sgd:comprobar-conexion')
            ->expectsOutput('Falta habilitar pdo_sqlsrv en el PHP que ejecuta Artisan.')
            ->assertFailed();
    }

    public function test_esquema_invalido_se_rechaza_antes_de_contactar_el_servidor(): void
    {
        config(['sgd.schema' => 'IDOSGD]; SELECT 1']);
        DB::shouldReceive('connection')->never();

        $this->artisan('sgd:comprobar-conexion')
            ->expectsOutput('SGD_DB_SCHEMA debe contener un identificador válido.')
            ->assertFailed();
    }

    public function test_diagnostico_usa_select_parametrizados_y_preserva_conexion_principal(): void
    {
        $originalDefault = config('database.default');
        $this->mockReadableServer();

        $this->assertSame(0, Artisan::call('sgd:comprobar-conexion'));
        $output = Artisan::output();
        $this->assertSame($originalDefault, config('database.default'));
        $this->assertStringContainsString('Conexión verificada: IDOSGD_MDSJ', $output);
        $this->assertStringContainsString('Solo se consultaron metadatos', $output);
    }

    public function test_diagnostico_advierte_privilegios_sin_bloquear_comprobacion_autorizada(): void
    {
        $this->mockReadableServer(privileged: true);

        $this->assertSame(0, Artisan::call('sgd:comprobar-conexion'));
        $this->assertStringContainsString('La cuenta tiene privilegios administrativos o de escritura.', Artisan::output());
    }

    public function test_una_tabla_no_visible_produce_diagnostico_fallido(): void
    {
        $this->mockReadableServer(missingTable: 'TDTR_REFERENCIA');

        $this->assertSame(1, Artisan::call('sgd:comprobar-conexion'));
        $this->assertStringContainsString('No visible o sin SELECT', Artisan::output());
    }

    public function test_errores_de_conexion_no_exponen_el_mensaje_original_ni_credenciales(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('selectOne')->once()
            ->andThrow(new RuntimeException('Conexión fallida con secreto-sintetico-de-prueba'));
        DB::shouldReceive('connection')->with('sgd')->once()->andReturn($connection);
        DB::shouldReceive('disconnect')->with('sgd')->once();

        $this->assertSame(1, Artisan::call('sgd:comprobar-conexion'));
        $output = Artisan::output();
        $this->assertStringNotContainsString('secreto-sintetico-de-prueba', $output);
        $this->assertStringContainsString('No se pudo comprobar la conexión SGD.', $output);
    }

    private function mockReadableServer(bool $privileged = false, ?string $missingTable = null): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('selectOne')
            ->once()
            ->withArgs(fn (string $sql) => str_starts_with(trim($sql), 'SELECT DB_NAME()'))
            ->andReturn((object) [
                'base_datos' => 'IDOSGD_MDSJ',
                'version' => '15.0.2000.5',
                'es_sysadmin' => $privileged ? 1 : 0,
                'es_db_owner' => 0,
                'es_escritor' => 0,
                'es_ddl' => 0,
            ]);

        foreach (config('sgd.tables') as $table) {
            $object = '[IDOSGD].['.$table.']';
            $connection->shouldReceive('selectOne')
                ->once()
                ->withArgs(fn (string $sql, array $bindings) => str_starts_with(trim($sql), 'SELECT ') && $bindings === [$object, $object])
                ->andReturn((object) [
                    'objeto_id' => $table === $missingTable ? null : 1,
                    'puede_leer' => $table === $missingTable ? null : 1,
                ]);
        }

        DB::shouldReceive('connection')->with('sgd')->once()->andReturn($connection);
        DB::shouldReceive('disconnect')->with('sgd')->once();
    }
}
