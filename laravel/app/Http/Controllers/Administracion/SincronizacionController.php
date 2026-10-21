<?php

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\ConfigurarSincronizacionRequest;
use App\Ingesta\EstadoSincronizacion;
use App\Ingesta\ProgramarSincronizacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SincronizacionController extends Controller
{
    public function index(EstadoSincronizacion $estado): View
    {
        return view('administracion.sincronizacion', $estado->consultar());
    }

    public function estado(EstadoSincronizacion $estado): JsonResponse
    {
        $datos = $estado->consultar();
        // El paginador mantiene sus enlaces en la pantalla, no en el endpoint JSON.
        $datos['ejecuciones']->withPath(route('admin.sincronizacion.index'));

        return response()->json([
            'activa' => $datos['activa'] !== null,
            'fragmentos' => collect(['resumen', 'accion', 'historial'])->mapWithKeys(fn ($nombre) => [
                $nombre => view('administracion.sincronizacion.'.$nombre, $datos)->render(),
            ]),
        ]);
    }

    public function update(ConfigurarSincronizacionRequest $request, ProgramarSincronizacion $programador): RedirectResponse
    {
        $programador->configurar($request->user(), $request->validated());

        return to_route('admin.sincronizacion.index')->with('estado', 'Programación actualizada. Los trabajos ya solicitados conservan su ejecución.');
    }

    public function ejecutar(Request $request, ProgramarSincronizacion $programador): RedirectResponse
    {
        $ejecucion = $programador->solicitar($request->user());

        return to_route('admin.sincronizacion.index')->with('estado', "Solicitud #{$ejecucion->id} registrada. El estado se actualizará automáticamente mientras permanezcas en esta pantalla.");
    }
}
