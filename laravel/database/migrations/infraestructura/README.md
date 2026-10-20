# Migraciones técnicas pendientes

Estas migraciones no se descubren al ejecutar `php artisan migrate` desde la carpeta habitual. Las sesiones y la caché utilizan archivos y la cola es síncrona, por lo que estas tablas todavía no se necesitan.

Cuando se elija usar uno de esos servicios en base de datos, mover su migración a `database/migrations/` antes de ejecutar las migraciones normales. Así permanecerá disponible también para los comandos de reversión.
