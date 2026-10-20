<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class ComprobarConexionSgd extends Command
{
    protected $signature = 'sgd:comprobar-conexion
        {--solo-configuracion : Revisar variables y driver sin contactar SQL Server}';

    protected $description = 'Comprueba la conexión del SGD mediante SELECT de metadatos, sin extraer documentos.';

    public function handle(): int
    {
        $name = config('sgd.connection');
        $connection = config('database.connections.'.$name, []);
        $schema = config('sgd.schema');
        $missing = [];

        foreach (['host' => 'SGD_DB_HOST', 'database' => 'SGD_DB_DATABASE', 'username' => 'SGD_DB_USERNAME', 'password' => 'SGD_DB_PASSWORD'] as $key => $variable) {
            if (trim((string) ($connection[$key] ?? '')) === '') {
                $missing[] = $variable;
            }
        }

        if (! is_string($schema) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema)) {
            $this->error('SGD_DB_SCHEMA debe contener un identificador válido.');

            return self::FAILURE;
        }

        $driverAvailable = $this->driverDisponible();

        if ($this->option('solo-configuracion')) {
            $this->line('Driver pdo_sqlsrv: '.($driverAvailable ? 'disponible' : 'pendiente'));
            $this->line('Variables requeridas: '.($missing === [] ? 'completas' : implode(', ', $missing).' pendientes'));
            $this->line('No se contactó SQL Server.');

            return self::SUCCESS;
        }

        if ($missing !== []) {
            $this->error('Configurar en .env: '.implode(', ', $missing).'.');

            return self::FAILURE;
        }

        if (! $driverAvailable) {
            $this->error('Falta habilitar pdo_sqlsrv en el PHP que ejecuta Artisan.');

            return self::FAILURE;
        }

        try {
            $database = DB::connection($name);
            $metadata = $database->selectOne(<<<'SQL'
                SELECT DB_NAME() AS base_datos,
                    CAST(SERVERPROPERTY('ProductVersion') AS nvarchar(128)) AS version,
                    IS_SRVROLEMEMBER('sysadmin') AS es_sysadmin,
                    IS_MEMBER('db_owner') AS es_db_owner,
                    IS_MEMBER('db_datawriter') AS es_escritor,
                    IS_MEMBER('db_ddladmin') AS es_ddl
                SQL);

            if ($metadata === null || strcasecmp($metadata->base_datos, $connection['database']) !== 0) {
                $this->error('La conexión no devolvió la base configurada para el SGD.');

                return self::FAILURE;
            }

            $this->info('Conexión verificada: '.$metadata->base_datos.' · SQL Server '.$metadata->version);

            if ($metadata->es_sysadmin || $metadata->es_db_owner || $metadata->es_escritor || $metadata->es_ddl) {
                $this->warn('La cuenta tiene privilegios administrativos o de escritura. Usar una cuenta dedicada de lectura para la ingesta automatizada.');
            }

            $rows = [];
            $canReadAll = true;

            foreach (config('sgd.tables', []) as $table) {
                $object = '['.$schema.'].['.$table.']';
                $permissions = $database->selectOne(
                    "SELECT OBJECT_ID(?) AS objeto_id, HAS_PERMS_BY_NAME(?, 'OBJECT', 'SELECT') AS puede_leer",
                    [$object, $object],
                );
                $canRead = $permissions !== null && $permissions->objeto_id !== null && (int) $permissions->puede_leer === 1;
                $canReadAll = $canReadAll && $canRead;
                $rows[] = [$schema.'.'.$table, $canRead ? 'Disponible' : 'No visible o sin SELECT'];
            }

            $this->table(['Objeto de origen', 'Lectura'], $rows);
            $this->line('Solo se consultaron metadatos; no se extrajeron documentos.');

            return $canReadAll ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $error) {
            $code = (string) $error->getCode();
            $state = preg_match('/^[A-Z0-9]{5}$/', $code) ? ' SQLSTATE '.$code.'.' : '';
            $this->error('No se pudo comprobar la conexión SGD.'.$state.' Revisar servidor, credenciales, driver y opciones TLS.');

            return self::FAILURE;
        } finally {
            DB::disconnect($name);
        }
    }

    protected function driverDisponible(): bool
    {
        return in_array('sqlsrv', PDO::getAvailableDrivers(), true);
    }
}
