# Migraciones técnicas pendientes

Estas migraciones no se descubren al ejecutar `php artisan migrate` desde la carpeta habitual. Las sesiones y la caché utilizan archivos, por lo que sus tablas todavía no se necesitan.

El 20 de octubre se trasladó `0001_01_01_000002_create_jobs_table.php` al directorio principal de migraciones para habilitar la cola persistente de sincronización SGD. La conexión de cola predeterminada conserva su configuración; el módulo de sincronización utiliza explícitamente `ingesta`, con almacenamiento en PostgreSQL y cola `sgd`.

Cuando se elija usar uno de esos servicios en base de datos, mover su migración a `database/migrations/` antes de ejecutar las migraciones normales. Así permanecerá disponible también para los comandos de reversión.
