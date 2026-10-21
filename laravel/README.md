# Aplicación Laravel del SIRMD

Esta carpeta contiene la aplicación web, el backend y los futuros comandos y workers PHP de la plataforma.

Para ejecutar desde esta carpeta:

```powershell
composer run dev
```

Este comando inicia Laravel, Vite, el scheduler y el worker de sincronización.
Los dos servicios se supervisan con `scripts/servicio.mjs` y se reinician si
terminan, incluso después de `php artisan queue:restart` o por límites de memoria.
No hace falta iniciar otros procesos de scheduler o worker durante el desarrollo.

La instalación, configuración de PostgreSQL y verificaciones se describen en el [README del proyecto](../README.md). La decisión sobre PHP y posibles componentes Python está documentada en [viabilidad tecnológica](../docs/viabilidad-tecnologica.md).

## Ingesta del SGD

El reporte de pendientes se calcula sobre publicaciones de datos extraídos del SGD.

```powershell
# Primera carga completa (relee todo el universo de pendientes):
php artisan sgd:ingestar

# Actualización incremental (solo cambios desde la última marca de agua):
php artisan sgd:ingestar --incremental

# Piloto de una sola dependencia:
php artisan sgd:ingestar --dependencia=00021
```

Cada ejecución crea una publicación nueva y la marca como vigente; la anterior
queda como histórica. La incremental clona la última vigente y aplica solo los
cambios (altas, cambios y bajas) desde `FE_USE_MOD`, con 15 minutos de
solapamiento de seguridad.

### Programación automática

La frecuencia se administra en **Administración → Sincronización SGD**, con 15
minutos por defecto. El scheduler comprueba la programación guardada cada minuto.
El entorno debe ejecutar `php artisan schedule:run` **cada minuto**, o mantener
activo `php artisan schedule:work` durante el desarrollo.

En Windows, crear una tarea en el Programador de tareas que ejecute cada minuto:

```powershell
schtasks /Create /SC MINUTE /MO 1 /TN "SIRMD schedule" ^
  /TR "php C:\ruta\al\proyecto\laravel\artisan schedule:run"
```

En Linux, una entrada de cron:

```cron
* * * * * cd /ruta/al/proyecto/laravel && php artisan schedule:run >> /dev/null 2>&1
```

Fuera del arranque de desarrollo integrado, debe mantenerse un worker dedicado:

```powershell
php artisan queue:work ingesta --queue=sgd --sleep=3 --timeout=3600 --tries=0 --memory=256
```

En Windows, `powershell -NoProfile -File scripts/Start-SgdWorker.ps1` lo supervisa
y lo reinicia si termina. Para continuidad tras reiniciar el equipo, registrar
este script en el servicio de arranque del entorno. Detalles y limitaciones en
[SINCRONIZACION.md](SINCRONIZACION.md).

El historial estructurado se consulta en Administración. La salida del scheduler
se agrega en `storage/logs/ingesta-sgd.log`.

## Administración

Usuarios, roles/permisos y auditoría están disponibles en el menú Administración,
según las capacidades de la cuenta. Ver [alcance, arquitectura y controles](ADMINISTRACION.md).
La nueva tabla de auditoría requiere aplicar las migraciones con `php artisan migrate`.
