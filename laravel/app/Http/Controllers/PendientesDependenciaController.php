<?php

namespace App\Http\Controllers;

use App\Models\Publicacion;
use App\Reportes\PendientesDependencia\ContextoReporte;
use App\Reportes\PendientesDependencia\EscritorCsv;
use App\Reportes\PendientesDependencia\EscritorXlsx;
use App\Reportes\PendientesDependencia\EvolucionPendientes;
use App\Reportes\PendientesDependencia\ExportacionDetalle;
use App\Reportes\PendientesDependencia\ExportacionGeneral;
use App\Reportes\PendientesDependencia\FiltrosPendientes;
use App\Reportes\PendientesDependencia\MotorPendientesDependencia;
use App\Reportes\PendientesDependencia\ResumenPendientes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PendientesDependenciaController extends Controller
{
    /**
     * Resumen jerárquico con drill-down. El parámetro ?dependencia= fija el
     * nodo actual; sin él se muestra el nivel institucional (raíces).
     */
    public function resumen(Request $request): View
    {
        $publicacion = $this->publicacionFijada($request);
        $areas = $this->areas();

        if ($publicacion === null) {
            return view('reportes.pendientes-dependencia.sin-datos', [
                'areas' => $areas,
                'area' => 'reportes',
            ]);
        }

        $contexto = ContextoReporte::resolver($request->user(), $publicacion, $this->filtrosDesde($request));
        $filtros = $contexto->filtros;
        $resumen = (new MotorPendientesDependencia($publicacion, $filtros, $contexto))->resumen();
        $busqueda = $this->busquedaDependencias($request);

        $codigoActual = $request->query('dependencia');
        $nodoActual = is_string($codigoActual) ? $resumen->fila($codigoActual) : null;

        // Por defecto se muestran solo las cifras propias de cada dependencia;
        // el acumulado con descendencia es opcional (?acumulado=1).
        $acumulado = $request->boolean('acumulado');
        $dias = $this->periodoDesde($request);
        $codigos = $nodoActual === null ? array_keys($resumen->filas) : [$nodoActual->codigo];
        if ($nodoActual !== null && $acumulado) {
            $codigos = array_keys(array_filter($resumen->filas, fn ($fila) => collect($resumen->ruta($fila->codigo))->contains('codigo', $nodoActual->codigo)
            ));
        }
        $evolucion = (new EvolucionPendientes)->obtener($contexto, $codigos, $dias);
        $resultados = $busqueda === null ? [] : $resumen->buscar($busqueda);

        return view('reportes.pendientes-dependencia.resumen', [
            'areas' => $areas,
            'area' => 'reportes',
            'resumen' => $resumen,
            'nodoActual' => $nodoActual,
            'busqueda' => $busqueda,
            'resultados' => $resultados,
            'rutasBusqueda' => collect($resultados)->mapWithKeys(fn ($fila) => [$fila->codigo => collect($resumen->ruta($fila->codigo))->pluck('nombre')->implode(' / ')])->all(),
            'evolucion' => $evolucion,
            'dias' => $dias,
            'filtros' => $filtros,
            'acumulado' => $acumulado,
            'request' => $request,
        ]);
    }

    /**
     * Detalle de una dependencia: la lista de destinos pendientes con sus claves
     * y antigüedad, más la distribución por tramo. Comparte filtros con el
     * resumen para que las cifras coincidan.
     */
    public function detalle(Request $request, string $dependencia): View|Response
    {
        $publicacion = $this->publicacionFijada($request);
        $areas = $this->areas();

        if ($publicacion === null) {
            return view('reportes.pendientes-dependencia.sin-datos', [
                'areas' => $areas,
                'area' => 'reportes',
            ]);
        }

        $filtros = $this->filtrosDesde($request);
        $contexto = ContextoReporte::resolver($request->user(), $publicacion, $filtros);
        abort_unless($contexto->permiteDependencia($dependencia), 403, 'Dependencia fuera del ámbito autorizado.');

        $detalle = (new MotorPendientesDependencia($publicacion, $contexto->filtros, $contexto))->detalle($dependencia);

        // Conserva los filtros en los enlaces de paginación.
        $detalle->destinos->appends($request->query());
        $dias = $this->periodoDesde($request);
        $evolucion = (new EvolucionPendientes)->obtener($contexto, [$dependencia], $dias);

        return response()
            ->view('reportes.pendientes-dependencia.detalle', [
                'areas' => $areas,
                'area' => 'reportes',
                'detalle' => $detalle,
                'evolucion' => $evolucion,
                'dias' => $dias,
                'contexto' => $contexto,
                'filtros' => $contexto->filtros,
                'request' => $request,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Padrón de integrantes activos de una dependencia y la conciliación de sus
     * pendientes (§5). Devuelve un fragmento HTML (para el modal cargado bajo
     * demanda) que también sirve como fallback GET sin JS.
     *
     * El padrón es dato personal (nombres): requiere datos.ver_personales; sin
     * él, 403 (§7.3).
     */
    public function integrantes(Request $request, string $dependencia): View|Response
    {
        $contexto = $this->contextoIntegrantes($request, $dependencia);

        $motor = new MotorPendientesDependencia($this->publicacionDe($contexto), $contexto->filtros, $contexto);
        $busqueda = $this->busquedaDesde($request);
        $padron = $motor->padron($dependencia, busqueda: $busqueda);
        $padron->integrantes->appends($request->query());

        return response()
            ->view('reportes.pendientes-dependencia.partials.padron-integrantes', [
                'padron' => $padron,
                'contexto' => $contexto,
                'busqueda' => $busqueda,
                'request' => $request,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Pendientes de una persona dentro de una dependencia (§5): la tabla paginada
     * de sus destinos, reutilizando el mismo parcial, motor, filtros y orden que
     * el detalle. Devuelve un fragmento de tabla.
     *
     * Validaciones (§7.2): persona ajena al padrón y sin pendientes → 404;
     * persona fuera del padrón con pendientes → permitida como "otras
     * asignaciones". El selector grupo=sin_destinatario consulta el destinatario
     * nulo sin inventar un código.
     */
    public function pendientesPersona(Request $request, string $dependencia, string $empleado): View|Response
    {
        $contexto = $this->contextoIntegrantes($request, $dependencia);
        $publicacion = $this->publicacionDe($contexto);
        $motor = new MotorPendientesDependencia($publicacion, $contexto->filtros, $contexto);

        // El grupo "sin destinatario" se pide con grupo=sin_destinatario, no con
        // un código de empleado inventado.
        $sinDestinatario = $request->query('grupo') === 'sin_destinatario';
        $destinatario = $sinDestinatario ? null : $empleado;

        if ($sinDestinatario) {
            // Solo tiene sentido si existen pendientes sin destinatario.
            abort_if($motor->contarDetalle($dependencia, null) === 0, 404, 'No hay pendientes sin destinatario en esta oficina.');
        } else {
            // Persona ajena al padrón y sin pendientes en D → 404. En el padrón,
            // o fuera de él con pendientes → permitida.
            $enPadron = $motor->enPadron($dependencia, $empleado);
            if (! $enPadron && $motor->contarDetalle($dependencia, $empleado) === 0) {
                abort(404, 'La persona no pertenece al padrón ni tiene pendientes en esta oficina.');
            }
        }

        $detalle = $motor->detalle($dependencia, destinatario: $destinatario, pageName: 'persona_page');
        $detalle->destinos->appends($request->query());

        return response()
            ->view('reportes.pendientes-dependencia.partials.pendientes-persona', [
                'detalle' => $detalle,
                'contexto' => $contexto,
                'empleado' => $empleado,
                'sinDestinatario' => $sinDestinatario,
                'request' => $request,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Reporte general plano: una fila por dependencia (todas), sin jerarquía,
     * con las cifras propias de cada oficina. Comparte motor y filtros con el
     * resumen, de modo que las cifras coinciden; es la vista pensada para
     * exportar toda la data a Excel.
     */
    public function general(Request $request): View
    {
        $publicacion = $this->publicacionFijada($request);
        $areas = $this->areas();

        if ($publicacion === null) {
            return view('reportes.pendientes-dependencia.sin-datos', [
                'areas' => $areas,
                'area' => 'reportes',
            ]);
        }

        $contexto = ContextoReporte::resolver($request->user(), $publicacion, $this->filtrosDesde($request));
        $filtros = $contexto->filtros;
        $resumen = (new MotorPendientesDependencia($publicacion, $filtros, $contexto))->resumen();
        $busqueda = $this->busquedaDependencias($request);
        if ($busqueda !== null) {
            $resumen = new ResumenPendientes($publicacion, $filtros, $resumen->buscar($busqueda));
        }
        $exportacion = new ExportacionGeneral($resumen, $busqueda);

        return view('reportes.pendientes-dependencia.general', [
            'areas' => $areas,
            'area' => 'reportes',
            'resumen' => $resumen,
            'exportacion' => $exportacion,
            'busqueda' => $busqueda,
            'filtros' => $filtros,
            'request' => $request,
        ]);
    }

    /**
     * Exporta el detalle de una dependencia en CSV o .xlsx. Reutiliza el mismo
     * motor, filtros y orden que la vista de detalle: el archivo contiene
     * exactamente las mismas filas que la pantalla.
     *
     * Protegida por el permiso de exportación (además del de ver reportes de la
     * ruta padre), respetando el alcance autorizado.
     */
    public function exportarDetalle(Request $request, string $dependencia): StreamedResponse
    {
        Gate::authorize('reportes.exportar');

        $publicacion = $this->publicacionFijada($request);
        abort_if($publicacion === null, 404, 'No hay una publicación disponible para exportar.');

        $filtros = $this->filtrosDesde($request);
        $contexto = ContextoReporte::resolver($request->user(), $publicacion, $filtros);
        abort_unless($contexto->permiteDependencia($dependencia), 403, 'Dependencia fuera del ámbito autorizado.');

        $motor = new MotorPendientesDependencia($publicacion, $contexto->filtros, $contexto);
        $detalle = $motor->detalle($dependencia);
        $exportacion = new ExportacionDetalle($motor, $detalle, $contexto);

        if ($this->formato($request) === 'xlsx') {
            return (new EscritorXlsx)->descargar(
                nombreArchivo: $exportacion->nombreBase().'.xlsx',
                tituloHoja: 'Detalle '.$detalle->codigo,
                metadatos: $exportacion->metadatos(),
                columnas: $exportacion->columnas(),
                filas: $exportacion->filas(),
            );
        }

        return (new EscritorCsv)->descargar(
            nombreArchivo: $exportacion->nombreBase().'.csv',
            metadatos: $exportacion->metadatos(),
            columnas: $exportacion->columnas(),
            filas: $exportacion->filas(),
        );
    }

    /**
     * Exporta el reporte general plano (todas las dependencias) en CSV o .xlsx.
     * Deriva del mismo resumen que la pantalla; incluye una fila de totales
     * institucionales.
     */
    public function exportarGeneral(Request $request): StreamedResponse
    {
        Gate::authorize('reportes.exportar');

        $publicacion = $this->publicacionFijada($request);
        abort_if($publicacion === null, 404, 'No hay una publicación vigente para exportar.');

        $contexto = ContextoReporte::resolver($request->user(), $publicacion, $this->filtrosDesde($request));
        $filtros = $contexto->filtros;
        $resumen = (new MotorPendientesDependencia($publicacion, $filtros, $contexto))->resumen();
        $busqueda = $this->busquedaDependencias($request);
        if ($busqueda !== null) {
            $resumen = new ResumenPendientes($publicacion, $filtros, $resumen->buscar($busqueda));
        }
        $exportacion = new ExportacionGeneral($resumen, $busqueda);

        if ($this->formato($request) === 'xlsx') {
            return (new EscritorXlsx)->descargar(
                nombreArchivo: $exportacion->nombreBase().'.xlsx',
                tituloHoja: 'Pendientes general',
                metadatos: $exportacion->metadatos(),
                columnas: $exportacion->columnas(),
                filas: $exportacion->filas(),
                filaTotales: $exportacion->filaTotales(),
            );
        }

        return (new EscritorCsv)->descargar(
            nombreArchivo: $exportacion->nombreBase().'.csv',
            metadatos: $exportacion->metadatos(),
            columnas: $exportacion->columnas(),
            filas: $exportacion->filas(),
            filaTotales: $exportacion->filaTotales(),
        );
    }

    /**
     * Contexto compartido para los endpoints de integrantes (§7). Fija la
     * publicación, resuelve permisos/ámbito y comprueba que el usuario puede ver
     * la dependencia (403) y datos personales (403): el padrón y los nombres son
     * dato personal, de modo que sin datos.ver_personales se deniega el acceso.
     */
    private function contextoIntegrantes(Request $request, string $dependencia): ContextoReporte
    {
        $publicacion = $this->publicacionFijada($request);
        abort_if($publicacion === null, 404, 'No hay una publicación disponible.');

        $filtros = $this->filtrosDesde($request);
        $contexto = ContextoReporte::resolver($request->user(), $publicacion, $filtros);
        abort_unless($contexto->permiteDependencia($dependencia), 403, 'Dependencia fuera del ámbito autorizado.');
        abort_unless($contexto->puedeVerPersonales, 403, 'Se requiere permiso para ver datos personales de integrantes.');

        return $contexto;
    }

    private function publicacionDe(ContextoReporte $contexto): Publicacion
    {
        return $contexto->publicacion;
    }

    /** Texto de búsqueda del padrón, acotado para no servir como vector. */
    private function busquedaDesde(Request $request): ?string
    {
        $q = $request->query('q');
        if (! is_string($q)) {
            return null;
        }
        $q = trim($q);

        return $q === '' ? null : mb_substr($q, 0, 100);
    }

    /**
     * Publicación fijada para la vida de la petición. Por defecto la vigente;
     * si llega ?publicacion=ID, se usa esa (debe estar finalizada). Fijarla
     * evita que un cambio del scheduler mueva el corte durante la navegación.
     */
    private function publicacionFijada(Request $request): ?Publicacion
    {
        $id = $request->query('publicacion');
        if (is_string($id) && ctype_digit($id)) {
            $fijada = Publicacion::find((int) $id);
            // ContextoReporte::resolver validará estado; aquí solo se fija.
            if ($fijada !== null) {
                return $fijada;
            }
            // Un id inexistente no cae silenciosamente a vigente: estado claro.
            abort(404, 'La publicación solicitada no existe.');
        }

        return Publicacion::vigente();
    }

    /** Formato de exportación solicitado: 'xlsx' o 'csv' (por defecto). */
    private function formato(Request $request): string
    {
        return $request->query('formato') === 'xlsx' ? 'xlsx' : 'csv';
    }

    private function busquedaDependencias(Request $request): ?string
    {
        $validado = $request->validate(['buscar' => 'nullable|string|max:100']);
        $texto = trim($validado['buscar'] ?? '');

        return $texto === '' ? null : $texto;
    }

    private function periodoDesde(Request $request): int
    {
        $validado = $request->validate(['periodo' => 'nullable|integer|in:30,60,90']);

        return (int) ($validado['periodo'] ?? 30);
    }

    private function filtrosDesde(Request $request): FiltrosPendientes
    {
        return new FiltrosPendientes(
            prioridad: $this->normalizar($request->query('prioridad'), ['1', '2', '3']),
            estadoRecepcion: $this->normalizar($request->query('estado'), ['0', '1']),
            bandeja: $this->normalizar($request->query('bandeja'), ['entrada', 'delegados']),
        );
    }

    /** @param array<int, string> $permitidos */
    private function normalizar(mixed $valor, array $permitidos): ?string
    {
        return is_string($valor) && in_array($valor, $permitidos, true) ? $valor : null;
    }

    private function areas(): Collection
    {
        return collect(config('platform.areas'))->map(fn (array $item) => [
            ...$item, 'allowed' => Gate::allows($item['permission']),
        ]);
    }
}
