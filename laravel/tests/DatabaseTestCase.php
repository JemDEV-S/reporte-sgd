<?php

namespace Tests;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

abstract class DatabaseTestCase extends TestCase
{
    private ?string $testSchema = null;

    private ?string $originalConnection = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $connection = DB::connection();

            if ($connection->getDriverName() === 'pgsql') {
                $this->originalConnection = DB::getDefaultConnection();
                $this->testSchema = 'sirmd_test_'.bin2hex(random_bytes(8));
                $connection->statement('CREATE SCHEMA "'.$this->testSchema.'"');

                $configuration = $connection->getConfig();
                unset($configuration['name']);
                $configuration['search_path'] = $this->testSchema;
                $configuration['url'] = null;
                config([
                    'database.connections.testing_isolated' => $configuration,
                    'database.default' => 'testing_isolated',
                ]);

                if (DB::connection()->getName() !== 'testing_isolated'
                    || DB::connection()->selectOne('SELECT current_schema() AS schema')->schema !== $this->testSchema) {
                    throw new RuntimeException('No se pudo aislar el esquema de pruebas.');
                }
            } elseif ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
                throw new RuntimeException('Las pruebas requieren SQLite en memoria o un esquema PostgreSQL aislado.');
            }

            $this->artisan('migrate', [
                '--database' => DB::getDefaultConnection(),
                '--force' => true,
            ])->assertSuccessful();
        } catch (Throwable $error) {
            $this->cleanPostgresSchema();

            throw $error;
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanPostgresSchema();
        } finally {
            parent::tearDown();
        }
    }

    private function cleanPostgresSchema(): void
    {
        if ($this->testSchema === null) {
            return;
        }

        if (! preg_match('/^sirmd_test_[a-f0-9]{16}$/', $this->testSchema)) {
            throw new RuntimeException('Nombre de esquema de prueba inválido.');
        }

        DB::disconnect('testing_isolated');
        DB::connection($this->originalConnection)->statement('DROP SCHEMA IF EXISTS "'.$this->testSchema.'" CASCADE');
        $this->testSchema = null;
    }
}
