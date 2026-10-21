<?php

use App\Http\Controllers\Administracion\AuditoriaController;
use App\Http\Controllers\Administracion\RolController;
use App\Http\Controllers\Administracion\SincronizacionController;
use App\Http\Controllers\Administracion\UsuarioController;
use App\Http\Controllers\ExploradorController;
use App\Http\Controllers\InformeController;
use App\Http\Controllers\PendientesDependenciaController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\SessionController;
use App\Http\Middleware\EnsurePlatformAccess;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', EnsurePlatformAccess::class])->group(function () {
    Route::get('/', PlatformController::class)->name('home');

    Route::prefix('administracion')->name('admin.')->group(function () {
        Route::middleware('can:fuentes.gestionar')->group(function () {
            Route::get('sincronizacion', [SincronizacionController::class, 'index'])->name('sincronizacion.index');
            Route::get('sincronizacion/estado', [SincronizacionController::class, 'estado'])->name('sincronizacion.estado');
            Route::put('sincronizacion', [SincronizacionController::class, 'update'])->name('sincronizacion.update');
            Route::post('sincronizacion/ejecutar', [SincronizacionController::class, 'ejecutar'])->name('sincronizacion.ejecutar');
        });
        Route::middleware('can:usuarios.gestionar')->group(function () {
            Route::resource('usuarios', UsuarioController::class)
                ->parameters(['usuarios' => 'usuario'])->except(['show', 'destroy']);
        });
        Route::middleware('can:roles.gestionar')->group(function () {
            Route::resource('roles', RolController::class)
                ->parameters(['roles' => 'rol'])->except(['show', 'destroy']);
            Route::get('auditoria', AuditoriaController::class)->name('auditoria');
        });
    });

    // Catálogo de reportes. El primer reporte: pendientes por dependencia.
    Route::middleware('can:reportes.ver')->group(function () {
        Route::get('/reportes/pendientes-dependencia', [PendientesDependenciaController::class, 'resumen'])
            ->name('reportes.pendientes-dependencia');
        // Reporte general plano (todas las dependencias, sin jerarquía) y su exportación.
        Route::get('/reportes/pendientes-dependencia/general', [PendientesDependenciaController::class, 'general'])
            ->name('reportes.pendientes-dependencia.general');
        Route::get('/reportes/pendientes-dependencia/general/exportar', [PendientesDependenciaController::class, 'exportarGeneral'])
            ->name('reportes.pendientes-dependencia.general.exportar');
        Route::get('/reportes/pendientes-dependencia/{dependencia}', [PendientesDependenciaController::class, 'detalle'])
            ->where('dependencia', '[0-9A-Za-z]{1,5}')
            ->name('reportes.pendientes-dependencia.detalle');
        Route::get('/reportes/pendientes-dependencia/{dependencia}/exportar', [PendientesDependenciaController::class, 'exportarDetalle'])
            ->where('dependencia', '[0-9A-Za-z]{1,5}')
            ->name('reportes.pendientes-dependencia.exportar');
        // Modal de integrantes (§5): padrón activo con conciliación y pendientes
        // por persona. Fragmentos cargados bajo demanda; también sirven como
        // fallback GET sin JS.
        Route::get('/reportes/pendientes-dependencia/{dependencia}/integrantes', [PendientesDependenciaController::class, 'integrantes'])
            ->where('dependencia', '[0-9A-Za-z]{1,5}')
            ->name('reportes.pendientes-dependencia.integrantes');
        Route::get('/reportes/pendientes-dependencia/{dependencia}/integrantes/{empleado}/pendientes', [PendientesDependenciaController::class, 'pendientesPersona'])
            ->where('dependencia', '[0-9A-Za-z]{1,5}')
            ->where('empleado', '[0-9A-Za-z]{1,10}')
            ->name('reportes.pendientes-dependencia.integrantes.persona');
    });

    // Informes inteligentes (beta): borradores a partir de agregados verificados.
    Route::middleware('can:informes.ver')->group(function () {
        Route::get('/informes', [InformeController::class, 'index'])->name('informes.index');
        Route::post('/informes', [InformeController::class, 'generar'])->name('informes.generar');
        Route::get('/informes/{informe}', [InformeController::class, 'show'])->name('informes.show');
        Route::get('/informes/{informe}/word', [InformeController::class, 'descargar'])->name('informes.descargar');
        Route::post('/informes/{informe}/aprobar', [InformeController::class, 'aprobar'])->name('informes.aprobar');
    });

    // Explorador de datos (beta): consultas dinámicas sobre pendientes.
    Route::middleware('can:explorador.usar')->group(function () {
        Route::get('/explorador', [ExploradorController::class, 'index'])->name('explorador.index');
        Route::post('/explorador/consultas', [ExploradorController::class, 'guardar'])->name('explorador.guardar');
        Route::get('/explorador/consultas/{consulta}/cargar', [ExploradorController::class, 'cargar'])
            ->name('explorador.cargar');
    });

    Route::get('/areas/{area}', PlatformController::class)->name('platform.area');
});
