# Aplicación Laravel del SIRMD

Esta carpeta contiene la aplicación web, el backend y los futuros comandos y workers PHP de la plataforma.

Para ejecutar desde esta carpeta:

```powershell
composer run dev
```

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

El incremental está programado cada 15 minutos en `routes/console.php`. Para que
corra solo, el entorno debe ejecutar `php artisan schedule:run` **cada minuto**.

En Windows, crear una tarea en el Programador de tareas que ejecute cada minuto:

```powershell
schtasks /Create /SC MINUTE /MO 1 /TN "SIRMD schedule" ^
  /TR "php C:\ruta\al\proyecto\laravel\artisan schedule:run"
```

En Linux, una entrada de cron:

```cron
* * * * * cd /ruta/al/proyecto/laravel && php artisan schedule:run >> /dev/null 2>&1
```

La salida de la ingesta se agrega en `storage/logs/ingesta-sgd.log`.
