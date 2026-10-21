# Administración: usuarios, roles y permisos

Primera entrega implementada el 20 de octubre de 2026 (America/Lima).

## Alcance

La navegación incorpora Administración, visible según los permisos vigentes:

| Página | Ruta | Permiso |
|---|---|---|
| Usuarios | `/administracion/usuarios` | `usuarios.gestionar` |
| Roles y permisos | `/administracion/roles` | `roles.gestionar` |
| Auditoría | `/administracion/auditoria` | `roles.gestionar` |
| Sincronización SGD | `/administracion/sincronizacion` | `fuentes.gestionar` |

Todas las rutas requieren autenticación, cuenta activa y `plataforma.acceder`.
Las escrituras usan POST/PUT/PATCH con protección CSRF de Laravel.

Usuarios permite buscar y paginar cuentas, filtrar por estado, crear y editar,
activar/desactivar, asignar varios roles y restablecer contraseñas. La edición sin
contraseña conserva el hash existente. Una cuenta sin roles no tiene acceso.
No se incorporan eliminación de cuentas ni registro público.

Roles permite crear perfiles personalizados, editar nombre/descripción y asignar
permisos del catálogo implementado, agrupados por módulo. El código de un rol
existente es inmutable. No existe un formulario para inventar códigos de permiso:
un permiso nuevo requiere enum y comprobaciones en el código correspondiente.

## Responsabilidades

- `Http/Requests/Administracion`: autorización de entrada, normalización y validación.
- `Http/Controllers/Administracion`: consultas de pantalla, coordinación y respuestas.
- `Administracion/GestionarUsuario` y `GestionarRol`: operaciones transaccionales.
- `Administracion/ProtegerAccesoAdministrativo`: autorización vigente bajo bloqueo y protección del acceso administrativo.
- `Administracion/RegistrarAuditoria`: registro de metadatos dentro de la misma transacción.
- Blade: layouts y formularios; sin operaciones de escritura en las vistas.

Las escrituras bloquean las filas de roles en orden antes de modificar cuentas o
asignaciones, serializando las operaciones de este módulo en PostgreSQL. Se exige
que al finalizar exista una cuenta activa con los tres permisos
`plataforma.acceder`, `usuarios.gestionar` y `roles.gestionar`, incluso si los reúne
mediante varios roles. Si no existe, se revierte toda la operación.

Gestionar usuarios no concede capacidad para modificar roles ni tomar el control
de una cuenta con permisos de gestión de usuarios o roles: ambas operaciones
requieren además `roles.gestionar`. El permiso se comprueba en el servidor,
independientemente del estado de los controles del formulario.

Estas garantías se aplican a las acciones administrativas implementadas. SQL
manual y comandos externos que cambien asignaciones deben respetar el mismo
contrato; no quedan protegidos automáticamente por estas clases.

## Auditoría

La nueva tabla `eventos_auditoria` conserva responsable, acción, tipo/ID de registro,
fecha y metadatos de los cambios. Nunca registra la contraseña ni su hash. En
usuarios guarda nombres de campos modificados, IDs de roles anteriores/nuevos y
un indicador de cambio de contraseña. En roles conserva las asignaciones de
permisos anteriores y nuevas. Si el registro de auditoría falla, se revierte la
escritura de negocio.

La tabla no tiene rutas de edición o borrado. Esta entrega no implementa una
bitácora de acceso documental ni auditoría de comandos de consola existentes.

## Instalación y validación

Desde `laravel/`, aplicar las migraciones pendientes con `php artisan migrate`.
La nueva migración solo crea la tabla de auditoría; conserva cuentas y permisos
existentes. No hace falta repetir el seeder ni crear otra cuenta inicial.

```powershell
php artisan test --compact
php vendor/bin/phpunit -c phpunit.pgsql.xml --filter=AdministracionTest
npm run build
php artisan view:cache
php vendor/bin/pint --test app/Administracion app/Http/Controllers/Administracion app/Http/Requests/Administracion tests/Feature/AdministracionTest.php
```

La suite PostgreSQL usa esquemas temporales aislados mediante `DatabaseTestCase`.

Si hay un servidor local atendiendo peticiones en Windows, se pueden aislar las
vistas compiladas de las pruebas para evitar conflictos de archivos con ese
proceso. En la terminal de pruebas:

```powershell
$adminTestViews = Join-Path (Get-Location) 'storage/framework/testing-views'
New-Item -ItemType Directory -Force -Path $adminTestViews | Out-Null
$env:VIEW_COMPILED_PATH = $adminTestViews
```

## Continuación

La configuración de sincronización, el historial y la ejecución incremental manual
ya están implementados. Ver [operación y responsabilidades](SINCRONIZACION.md).

Pendientes para entregas posteriores: configuración general editable, asignación
de oficinas (`usuario_dependencia`) y aplicación uniforme del alcance al
explorador y a los informes. Los reportes siguen denegando el acceso sin alcance
institucional mientras no exista esa asignación. La nueva administración no
concede automáticamente acceso documental al administrador técnico.

El restablecimiento de contraseña actualiza la credencial. La invalidación
específica de otras sesiones al restablecerla y la obligación de cambiar la
contraseña inicial quedan pendientes; la desactivación de cuenta sí cierra el
acceso en la siguiente petición mediante el middleware existente.
