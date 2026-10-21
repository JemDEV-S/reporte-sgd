# Sincronización administrativa del SGD

Implementada el 20 de octubre de 2026 (America/Lima).

## Uso

En Administración → Sincronización SGD, las cuentas con `fuentes.gestionar` pueden:

- Activar o pausar la programación automática y elegir 5, 10, 15, 30 o 60 minutos.
- Solicitar una sincronización incremental inmediata, incluso con la programación pausada.
- Consultar la publicación disponible, próxima ejecución prevista, actividad de los servicios y el historial paginado.

El botón responde después de guardar la solicitud, sin esperar a la ingesta. El
procesador la atiende cuando esté disponible. Solicitudes repetidas comparten la
misma reserva pendiente o en curso. La pantalla distingue “Pendiente de procesamiento”
de “Sincronizando…” y señala si falta actividad reciente del procesador.

Con JavaScript consulta un endpoint autorizado cada 5 segundos mientras exista
una solicitud activa y cada 15 segundos en reposo. Solo actualiza resumen, acción
manual e historial: conserva los cambios sin guardar del formulario de programación.
Suspende consultas en pestañas ocultas y se detiene si se revocan permisos o expira
la sesión. Ante fallos de red mantiene el estado visible y vuelve a intentar.
Sin JavaScript sigue disponible Actualizar estado.

Cambiar la frecuencia o reactivar la programación inicia un intervalo desde el
momento del cambio. Guardar los mismos valores no desplaza la próxima ejecución.
Pausar no cancela trabajos solicitados. La próxima fecha es una previsión; un
servicio detenido o una ejecución prolongada puede retrasarla. No se acumulan
solicitudes por cada intervalo perdido.

La ventana de solapamiento de lectura de 15 minutos de la ingesta incremental se
mantiene independiente de la frecuencia. Una instalación sin publicación inicial
con marca de agua necesita primero una ingesta completa por consola.

## Responsabilidades

| Componente | Responsabilidad |
|---|---|
| `ConfigurarSincronizacionRequest` | Autorizar y validar las opciones de programación |
| `SincronizacionController` | Consultar datos de pantalla y coordinar respuestas |
| `ProgramarSincronizacion` | Actualizar configuración, reservar solicitudes, encolar y auditar en una transacción |
| `PlanificarSincronizacion` | Registrar actividad del scheduler, recuperar procesos interrumpidos y comprobar vencimientos |
| `SincronizarSgd` | Trabajo persistente en conexión `ingesta`, cola `sgd` |
| `EjecutarSincronizacion` | Ejecutar ingesta, comprobar autorización manual vigente y registrar resultado |
| `BloqueoIngesta` | Exclusión compartida entre consola y workers |
| Servicios de ingesta existentes | Extraer datos y publicar la captura en su transacción documental |

La conexión `ingesta` utiliza la misma conexión PostgreSQL de la aplicación. La
reserva, el trabajo de cola y la auditoría se confirman juntos. La cola global
puede seguir siendo `sync`: este módulo elige su conexión explícitamente.

## Integridad y recuperación

`sincronizacion_sgd` contiene una fila de configuración. `sincronizaciones_sgd`
conserva origen, solicitante, tipo, fechas, estado, publicación y métricas. Un
índice único sobre la reserva activa impide dos solicitudes simultáneas; las
terminadas liberan esa reserva usando NULL.

En PostgreSQL se mantiene un advisory lock de sesión durante toda la ingesta,
incluida la lectura del origen. El bloqueo se delimita por base/esquema y no
vence por tiempo. Si muere el proceso, PostgreSQL libera el bloqueo. La siguiente
comprobación del scheduler puede marcar como interrumpido un intento ejecutándose
solo si logra adquirir el bloqueo. También recupera reservas de consola que
nunca iniciaron y llevan más de dos minutos pendientes. Una solicitud en cola
permanece pendiente si el worker está detenido.

Una entrega repetida del mismo trabajo no repite una ejecución ya finalizada.
Una ingesta fallida termina su intento y admite una solicitud nueva, sin reintentos
automáticos del negocio. El trabajo se libera durante 10 segundos si encuentra
el bloqueo ocupado. Los fallos o límites del worker pueden usar `failed_jobs`;
el historial administrativo es la referencia funcional.

Los estados de ejecución se guardan fuera de la transacción documental: una
reversión de la ingesta no borra el intento fallido. Una interrupción justo después
de publicar puede dejar la nueva publicación confirmada sin resultado de ejecución;
el estado interrumpido indica revisar la publicación vigente.

Las solicitudes manuales comprueban de nuevo la autorización al comenzar. Cambios
de configuración y nuevas solicitudes manuales generan auditoría. La pantalla y
el historial contienen mensajes resumidos; el log técnico registra el ID del
intento y la clase del error, sin copiar SQL o credenciales del driver.

## Instalación y procesos

### Desarrollo local

```powershell
composer run dev
```

Inicia Laravel, Vite y dos supervisores `node scripts/servicio.mjs scheduler|worker`.
Cada supervisor reinicia su proceso PHP después de tres segundos si termina,
incluidas salidas correctas por `queue:restart` o límites de memoria. Al detener
el arranque de desarrollo se detienen sus procesos. No se requieren servicios
adicionales abiertos manualmente en este modo. Su salida queda en la terminal;
en el arranque oculto utilizado en esta instalación, en `storage/logs/dev.stdout.log`
y `dev.stderr.log`.

### Servicios independientes

Para un despliegue que no utilice `composer run dev`, mantener scheduler y worker
mediante el supervisor del servidor. El arranque de desarrollo no instala una
tarea de inicio de Windows ni garantiza continuidad con el equipo apagado.

```powershell
php artisan migrate
php artisan schedule:work
# En otro proceso:
php artisan queue:work ingesta --queue=sgd --sleep=3 --timeout=3600 --tries=0 --memory=256
```

La migración de `jobs`, `job_batches` y `failed_jobs` se trasladó desde
`database/migrations/infraestructura` al directorio principal. No se deben ejecutar
dos copias de esa migración. La configuración nueva conserva una frecuencia
inicial de 15 minutos y mantiene las publicaciones anteriores.

En Windows puede utilizarse `scripts/Start-SgdWorker.ps1`, que evita supervisores
duplicados de la misma aplicación mediante un mutex y reinicia el worker al
terminar. El script no registra por sí mismo una tarea de inicio de Windows.
Para un arranque oculto desde PowerShell:

```powershell
$sgdWorkerScript = (Resolve-Path scripts/Start-SgdWorker.ps1).Path
Start-Process powershell.exe -ArgumentList @('-NoProfile', '-File', ('"' + $sgdWorkerScript + '"')) -WindowStyle Hidden
```

El timeout de 3600 segundos del worker necesita PCNTL para imponer un límite de
proceso; Windows normalmente carece de esa extensión. El bloqueo PostgreSQL
continúa protegiendo una ingesta prolongada. `retry_after=7200` evita una reserva
de cola menor al timeout configurado. Si un proceso queda bloqueado realmente,
la pantalla seguirá mostrando la ejecución; requiere revisión operativa del
proceso, sin liberar su reserva mientras conserve el bloqueo.

El scheduler y el worker registran sus señales de actividad por separado. El
worker las actualiza mientras espera trabajo; durante una ingesta se muestra el
estado en curso. Son señales de actividad reciente, no una prueba de conectividad
del SQL Server ni un indicador de progreso porcentual.

## Verificación

Las pruebas cubren permisos, frecuencia y pausa, auditoría transaccional, cola
persistente con cola global sync, deduplicación, ejecución por worker, errores
sin exposición de credenciales, revocación previa al procesamiento, recuperación
y exclusión mutua con dos conexiones PostgreSQL. SQLite omite únicamente la prueba
del advisory lock. PostgreSQL usa los esquemas aislados de `DatabaseTestCase`.

```powershell
php artisan test --compact
php vendor/bin/phpunit -c phpunit.pgsql.xml --filter=SincronizacionSgdTest
npm run build
php artisan view:cache
```
