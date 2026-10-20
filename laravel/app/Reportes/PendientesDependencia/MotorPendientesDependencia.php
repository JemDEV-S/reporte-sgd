<?php

namespace App\Reportes\PendientesDependencia;

use App\Enums\EstadoRecepcion;
use App\Models\Dependencia;
use App\Models\DependenciaCaptura;
use App\Models\DestinoCaptura;
use App\Models\EmpleadoCaptura;
use App\Models\EmpleadoDependenciaCaptura;
use App\Models\Publicacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Motor del reporte "pendientes por dependencia".
 *
 * Una sola consulta agregada produce las cifras por dependencia; el resumen
 * jerárquico, el drill-down y el detalle derivan todos de la misma
 * publicación y los mismos filtros, de modo que resumen, gráfico, detalle y
 * exportación siempre coinciden para una ejecución dada.
 *
 * Reglas aplicadas (perfil institucional, R01–R12):
 *  - Universo interno: tipo_destino = '01'.
 *  - Saldo de pendientes: estado_recepcion en ('0','1') y destino activo.
 *  - Unidad: destinos (asignaciones). No se deduplican documentos entre
 *    oficinas ni se suman documentos únicos para el total institucional.
 *  - Entrada vs Delegados: destinatario igual o distinto del encargado.
 */
class MotorPendientesDependencia
{
    public function __construct(
        private readonly Publicacion $publicacion,
        private readonly FiltrosPendientes $filtros = new FiltrosPendientes,
        /**
         * Contexto de permisos de campo. Si es null, se asume ámbito completo
         * (p. ej. resumen agregado sin datos sensibles, o llamadas internas);
         * el detalle enriquecido debe recibirlo para proyectar solo lo permitido.
         */
        private readonly ?ContextoReporte $contexto = null,
    ) {}

    /** Contexto efectivo: el recibido, o uno institucional implícito. */
    private function contexto(): ContextoReporte
    {
        return $this->contexto ?? ContextoReporte::institucional($this->publicacion, $this->filtros);
    }

    /**
     * Consulta base con las reglas del perfil y los filtros aplicados.
     * Es la única fuente de verdad: resumen y detalle parten de aquí.
     */
    public function consultaBase(): Builder
    {
        return $this->consultaPublicaciones([$this->publicacion->id]);
    }

    /** Las mismas reglas para el corte visible y la serie histórica. */
    private function consultaPublicaciones(array $ids): Builder
    {
        $query = DestinoCaptura::query()
            ->whereIn('destinos_captura.publicacion_id', $ids)
            ->where('destinos_captura.tipo_destino', '01')
            ->where('destinos_captura.activo', true)
            ->whereIn('destinos_captura.estado_recepcion', EstadoRecepcion::pendientes());

        return $this->filtros->aplicar($query);
    }

    /** Una agregación SQL para todos los cortes, sin cargar destinos en memoria. */
    public function conteosHistoricos(array $publicaciones, array $dependencias): Collection
    {
        return $this->consultaPublicaciones($publicaciones)
            ->whereIn('dependencia_codigo', $dependencias)
            ->selectRaw('publicacion_id, count(*) as total')
            ->groupBy('publicacion_id')
            ->pluck('total', 'publicacion_id');
    }

    /**
     * Resumen jerárquico: una fila por dependencia con cifras propias y
     * acumuladas (roll-up a los ancestros). Incluye dependencias con saldo
     * cero para no ocultar oficinas sin pendientes.
     */
    public function resumen(): ResumenPendientes
    {
        $agregados = $this->agregarPorDependencia();
        $filas = $this->construirArbol($agregados);
        $this->acumularRollUp($filas);

        return new ResumenPendientes(
            publicacion: $this->publicacion,
            filtros: $this->filtros,
            filas: $filas,
        );
    }

    /**
     * Detalle de una dependencia: los destinos individuales (solo los propios de
     * esa oficina) con su antigüedad, paginados, más la distribución por tramo
     * de antigüedad para el gráfico. Deriva de la misma consulta base que el
     * resumen, de modo que las cifras coinciden.
     */
    public function detalle(string $codigo, int $porPagina = 50, ?string $destinatario = null, string $pageName = 'page'): DetallePendientes
    {
        $dependencia = Dependencia::where('codigo', $codigo)->first();
        $corte = Carbon::parse($this->publicacion->fecha_publicacion);
        $base = $this->consultaDetalle($codigo, $destinatario);

        // Distribución de antigüedad sobre el total de la dependencia (no solo la página).
        $distribucion = array_fill_keys(
            array_map(fn (TramoAntiguedad $t) => $t->value, TramoAntiguedad::ordenados()),
            0,
        );
        foreach ($base()->get(['destinos_captura.fecha_recepcion', 'documentos_captura.fecha_emision as fecha_emision_doc']) as $fila) {
            $tramo = $this->tramoDe($this->fechaReferencia($fila), $corte);
            $distribucion[$tramo->value]++;
        }

        $contexto = $this->contexto();
        $destinos = $base()
            ->paginate($porPagina, pageName: $pageName)
            ->through(fn (DestinoCaptura $d) => FilaDetalle::desde($d, $corte, $contexto));

        return new DetallePendientes(
            publicacion: $this->publicacion,
            filtros: $this->filtros,
            dependencia: $dependencia,
            codigo: $codigo,
            destinos: $destinos,
            distribucionAntiguedad: $distribucion,
            fechaCorte: $corte,
        );
    }

    /**
     * Recorre todos los destinos del detalle de una dependencia, en el mismo
     * orden y con las mismas reglas que la tabla paginada, pero sin paginar y
     * de forma perezosa (cursor) para exportaciones de cualquier tamaño.
     *
     * Deriva de la MISMA consultaDetalle() que detalle(): la exportación y la
     * pantalla cuentan exactamente el mismo universo.
     *
     * @return \Generator<int, FilaDetalle>
     */
    public function filasDetalle(string $codigo, ?string $destinatario = null): \Generator
    {
        $corte = Carbon::parse($this->publicacion->fecha_publicacion);
        $contexto = $this->contexto();

        foreach ($this->consultaDetalle($codigo, $destinatario)()->cursor() as $destino) {
            yield FilaDetalle::desde($destino, $corte, $contexto);
        }
    }

    /** Conteo de destinos del detalle (para validar conciliación/totales). */
    public function contarDetalle(string $codigo, ?string $destinatario = null): int
    {
        return $this->consultaDetalle($codigo, $destinatario)()->count();
    }

    /**
     * Padrón de integrantes activos de una dependencia y la conciliación de sus
     * pendientes (§5). Devuelve los integrantes del padrón (paginados, con ceros)
     * y el grupo separado de "otras asignaciones" (destinatarios fuera del padrón
     * activo, incluido el nulo). El nombre solo se expone con permiso personal.
     *
     * Estrategia (§5.3): se agregan primero los destinos por destinatario (una
     * sola consulta GROUP BY) y luego se unen al padrón; el grupo fuera del padrón
     * se arma por anti-existencia. Así se garantiza la identidad
     *   pendientes de integrantes + pendientes fuera del padrón = total del detalle.
     */
    public function padron(string $codigo, int $porPagina = 25, ?string $busqueda = null): PadronIntegrantes
    {
        $dependencia = Dependencia::where('codigo', $codigo)->first();
        $contexto = $this->contexto();

        // Conteos de pendientes por destinatario (clave: código o '' para nulo).
        $conteos = $this->conteosPorDestinatario($codigo);
        $total = array_sum(array_map(fn (array $c) => $c['total'], $conteos));

        // Padrón activo: empleados activos que pertenecen a D por cualquiera de
        // las 3 vías, deduplicados por código, con su nombre (si hay permiso) y
        // sus vías de pertenencia. Incluye activos de saldo cero.
        $padronFilas = $this->padronActivo($codigo);
        $codigosPadron = array_map(fn (array $p) => $p['codigo'], $padronFilas);
        $encargado = $dependencia !== null
            ? $this->encargadoDe($codigo)
            : null;

        $integrantes = [];
        $totalIntegrantes = 0;
        foreach ($padronFilas as $p) {
            if ($busqueda !== null && ! $this->coincideBusqueda($p, $busqueda, $contexto)) {
                continue;
            }
            $c = $conteos[$p['codigo']] ?? ['total' => 0, 'no_leidos' => 0, 'recibidos' => 0, 'urgentes' => 0];
            $totalIntegrantes += $c['total'];
            $integrantes[] = IntegrantePadron::integrante(
                codigo: $p['codigo'],
                nombre: $contexto->puedeVerPersonales ? $p['nombre'] : null,
                esEncargado: $encargado !== null && $p['codigo'] === $encargado,
                vias: $p['vias'],
                total: $c['total'],
                noLeidos: $c['no_leidos'],
                recibidos: $c['recibidos'],
                urgentes: $c['urgentes'],
            );
        }

        // Para el total de integrantes (todas las páginas) se ignora la búsqueda:
        // la identidad de conciliación se mide sobre el padrón completo.
        $totalIntegrantesPadron = array_sum(array_map(
            fn (array $p) => ($conteos[$p['codigo']] ?? ['total' => 0])['total'],
            $padronFilas,
        ));

        // Otras asignaciones: destinatarios con pendientes que NO están en el
        // padrón activo (anti-existencia), más el grupo de destinatario nulo.
        $otras = [];
        $totalOtras = 0;
        foreach ($conteos as $clave => $c) {
            if ($clave === '') {
                $otras[] = IntegrantePadron::sinDestinatario(
                    total: $c['total'],
                    noLeidos: $c['no_leidos'],
                    recibidos: $c['recibidos'],
                    urgentes: $c['urgentes'],
                );
                $totalOtras += $c['total'];

                continue;
            }
            if (in_array($clave, $codigosPadron, true)) {
                continue;
            }
            $otras[] = IntegrantePadron::otraAsignacion(
                codigo: $clave,
                nombre: $contexto->puedeVerPersonales ? $this->nombreEmpleado($clave) : null,
                total: $c['total'],
                noLeidos: $c['no_leidos'],
                recibidos: $c['recibidos'],
                urgentes: $c['urgentes'],
            );
            $totalOtras += $c['total'];
        }

        $paginador = $this->paginarArray($integrantes, $porPagina);

        return new PadronIntegrantes(
            publicacion: $this->publicacion,
            filtros: $this->filtros,
            dependencia: $dependencia,
            codigo: $codigo,
            integrantes: $paginador,
            otrasAsignaciones: $otras,
            totalIntegrantes: $totalIntegrantesPadron,
            totalOtras: $totalOtras,
            totalDetalle: $total,
        );
    }

    /**
     * ¿Pertenece el empleado al padrón activo de la dependencia? Lo usa el
     * controlador para decidir entre 404 (ajeno y sin pendientes) y permitir
     * la consulta (en el padrón, o fuera de él pero con pendientes).
     */
    public function enPadron(string $codigo, string $empleado): bool
    {
        return in_array(
            $empleado,
            array_map(fn (array $p) => $p['codigo'], $this->padronActivo($codigo)),
            true,
        );
    }

    /**
     * Conteos de pendientes agrupados por destinatario para una dependencia,
     * aplicando los mismos filtros que el detalle. La clave del arreglo es el
     * código de empleado, o '' (cadena vacía) para el destinatario nulo, que no
     * se equipara a ninguna persona (§5.3).
     *
     * @return array<string, array{total: int, no_leidos: int, recibidos: int, urgentes: int}>
     */
    private function conteosPorDestinatario(string $codigo): array
    {
        $filas = $this->consultaBase()
            ->where('destinos_captura.dependencia_codigo', $codigo)
            ->selectRaw("coalesce(destinos_captura.destinatario_empleado, '') as destinatario")
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where destinos_captura.estado_recepcion = '0') as no_leidos")
            ->selectRaw("count(*) filter (where destinos_captura.estado_recepcion = '1') as recibidos")
            ->selectRaw("count(*) filter (where destinos_captura.prioridad in ('2','3')) as urgentes")
            ->groupBy('destinatario')
            ->get();

        $conteos = [];
        foreach ($filas as $fila) {
            $conteos[(string) $fila->destinatario] = [
                'total' => (int) $fila->total,
                'no_leidos' => (int) $fila->no_leidos,
                'recibidos' => (int) $fila->recibidos,
                'urgentes' => (int) $fila->urgentes,
            ];
        }

        return $conteos;
    }

    /**
     * Padrón activo de una dependencia: empleados activos que pertenecen a D por
     * al menos una de las 3 vías (§5.1), deduplicados por código. Cada entrada
     * trae su nombre (para mostrarlo solo con permiso) y las vías de pertenencia.
     *
     * Parte de empleado_dependencia_captura (indexada por publicación+dependencia)
     * con es_activa, uniéndose a empleados_captura por código. No incluye
     * dependencias hijas ni la opción sintética "[TODOS]" (§5.1).
     *
     * @return array<int, array{codigo: string, nombre: ?string, vias: array<int, string>}>
     */
    private function padronActivo(string $codigo): array
    {
        $filas = EmpleadoDependenciaCaptura::query()
            ->from('empleado_dependencia_captura as p')
            ->where('p.publicacion_id', $this->publicacion->id)
            ->where('p.dependencia_codigo', $codigo)
            ->where('p.es_activa', true)
            ->leftJoin('empleados_captura as e', function ($join) {
                $join->on('e.publicacion_id', '=', 'p.publicacion_id')
                    ->on('e.codigo', '=', 'p.empleado_codigo');
            })
            ->orderBy('e.apellido_paterno')
            ->orderBy('e.apellido_materno')
            ->orderBy('e.denominacion')
            ->orderBy('p.empleado_codigo')
            ->get([
                'p.empleado_codigo as codigo',
                'p.es_principal',
                'p.es_configurada',
                'p.es_asignada',
                'e.apellido_paterno',
                'e.apellido_materno',
                'e.denominacion',
            ]);

        $padron = [];
        foreach ($filas as $fila) {
            $vias = array_values(array_filter([
                $this->esVerdadero($fila->es_principal) ? 'principal' : null,
                $this->esVerdadero($fila->es_configurada) ? 'configurada' : null,
                $this->esVerdadero($fila->es_asignada) ? 'asignada' : null,
            ]));

            $padron[] = [
                'codigo' => (string) $fila->codigo,
                'nombre' => $this->componerNombre($fila->apellido_paterno, $fila->apellido_materno, $fila->denominacion),
                'vias' => $vias,
            ];
        }

        return $padron;
    }

    /** Código del encargado capturado de la dependencia en esta publicación. */
    private function encargadoDe(string $codigo): ?string
    {
        $valor = DependenciaCaptura::query()
            ->where('publicacion_id', $this->publicacion->id)
            ->where('codigo', $codigo)
            ->value('encargado_codigo');

        return $valor !== null ? (string) $valor : null;
    }

    /** Nombre de un empleado capturado por código (para "otras asignaciones"). */
    private function nombreEmpleado(string $empleado): ?string
    {
        $e = EmpleadoCaptura::query()
            ->where('publicacion_id', $this->publicacion->id)
            ->where('codigo', $empleado)
            ->first(['apellido_paterno', 'apellido_materno', 'denominacion']);

        return $e === null
            ? null
            : $this->componerNombre($e->apellido_paterno, $e->apellido_materno, $e->denominacion);
    }

    private function componerNombre(?string $apePat, ?string $apeMat, ?string $denom): ?string
    {
        $apellidos = trim(implode(' ', array_filter([
            $apePat !== null ? trim((string) $apePat) : null,
            $apeMat !== null ? trim((string) $apeMat) : null,
        ])));
        $partes = array_filter([
            $apellidos !== '' ? $apellidos : null,
            $denom !== null && trim((string) $denom) !== '' ? trim((string) $denom) : null,
        ]);

        return $partes === [] ? null : implode(', ', $partes);
    }

    /**
     * ¿La entrada del padrón coincide con el texto de búsqueda? Busca en el
     * nombre (si hay permiso) y en el código. Sin permiso personal el nombre no
     * participa en la búsqueda.
     *
     * @param  array{codigo: string, nombre: ?string, vias: array<int, string>}  $p
     */
    private function coincideBusqueda(array $p, string $busqueda, ContextoReporte $contexto): bool
    {
        $aguja = mb_strtolower(trim($busqueda));
        if ($aguja === '') {
            return true;
        }
        if (str_contains(mb_strtolower($p['codigo']), $aguja)) {
            return true;
        }

        return $contexto->puedeVerPersonales
            && $p['nombre'] !== null
            && str_contains(mb_strtolower($p['nombre']), $aguja);
    }

    /**
     * Paginador en memoria para el padrón ya resuelto. El padrón de una oficina
     * es de volumen bajo (decenas), de modo que paginar el arreglo resuelto evita
     * una segunda ronda de consultas por página sin coste relevante.
     *
     * @param  array<int, IntegrantePadron>  $items
     * @return LengthAwarePaginator<int, IntegrantePadron>
     */
    private function paginarArray(array $items, int $porPagina): LengthAwarePaginator
    {
        $pagina = Paginator::resolveCurrentPage('integrantes_page');
        $porPagina = max(1, $porPagina);
        $trozo = array_slice($items, ($pagina - 1) * $porPagina, $porPagina);

        return new LengthAwarePaginator(
            $trozo,
            count($items),
            $porPagina,
            $pagina,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'integrantes_page',
            ],
        );
    }

    /**
     * Consulta del detalle de una dependencia: la base común con el join a
     * documentos (para la fecha de emisión sustituta) y el orden estable que
     * comparten la tabla paginada y la exportación. Devuelve una fábrica para
     * poder derivar varias consultas independientes de la misma definición.
     *
     * Acepta una restricción opcional de destinatario (para el detalle de una
     * persona), que filtra destinos sin alterar el orden estable.
     *
     * @return \Closure(): Builder<DestinoCaptura>
     */
    private function consultaDetalle(string $codigo, ?string $destinatario = null): \Closure
    {
        $publicacionId = $this->publicacion->id;
        $verAsuntos = $this->contexto()->puedeVerAsuntos;

        // La antigüedad usa la recepción; si falta (p. ej. un no leído), se
        // sustituye por la emisión del documento. Los metadatos documentales,
        // la descripción del tipo y el nombre del destinatario se traen con
        // LEFT JOIN por publicación+clave: ningún faltante elimina un destino.
        return function () use ($codigo, $destinatario, $publicacionId, $verAsuntos) {
            $query = $this->consultaBase()
                ->where('destinos_captura.dependencia_codigo', $codigo)
                ->leftJoin('documentos_captura', function ($join) use ($publicacionId) {
                    $join->on('documentos_captura.publicacion_id', '=', 'destinos_captura.publicacion_id')
                        ->on('documentos_captura.anio', '=', 'destinos_captura.anio')
                        ->on('documentos_captura.emision', '=', 'destinos_captura.emision')
                        ->where('documentos_captura.publicacion_id', '=', $publicacionId);
                })
                ->leftJoin('tipos_documentales_captura', function ($join) use ($publicacionId) {
                    // El tipo documental es CO_TIP_DOC_ADM (tipo_documental), cuyo
                    // código casa con SI_MAE_TIPO_DOC.CDOC_TIPDOC; NO es TI_EMI.
                    $join->on('tipos_documentales_captura.publicacion_id', '=', 'destinos_captura.publicacion_id')
                        ->on('tipos_documentales_captura.codigo', '=', 'documentos_captura.tipo_documental')
                        ->where('tipos_documentales_captura.publicacion_id', '=', $publicacionId);
                })
                ->leftJoin('empleados_captura', function ($join) use ($publicacionId) {
                    $join->on('empleados_captura.publicacion_id', '=', 'destinos_captura.publicacion_id')
                        ->on('empleados_captura.codigo', '=', 'destinos_captura.destinatario_empleado')
                        ->where('empleados_captura.publicacion_id', '=', $publicacionId);
                });

            if ($destinatario !== null) {
                $query->where('destinos_captura.destinatario_empleado', $destinatario);
            }

            $columnas = [
                'destinos_captura.*',
                'documentos_captura.fecha_emision as fecha_emision_doc',
                'documentos_captura.tipo_emisor',
                'documentos_captura.tipo_documental',
                'documentos_captura.numero_documento',
                'documentos_captura.numero_incompleto',
                'documentos_captura.origen_descripcion',
                'documentos_captura.dependencia_emisora_codigo',
                'tipos_documentales_captura.descripcion as tipo_descripcion',
                'empleados_captura.apellido_paterno as destinatario_apepat',
                'empleados_captura.apellido_materno as destinatario_apemat',
                'empleados_captura.denominacion as destinatario_denom',
            ];
            // El asunto es dato sensible: no se selecciona si falta el permiso
            // (no basta ocultarlo en la vista, §7.3).
            if ($verAsuntos) {
                $columnas[] = 'documentos_captura.asunto';
            }

            return $query
                ->select($columnas)
                ->orderByRaw("CASE destinos_captura.estado_recepcion WHEN '0' THEN 0 ELSE 1 END")
                ->orderByDesc('destinos_captura.prioridad')
                ->orderBy('destinos_captura.anio')
                ->orderBy('destinos_captura.emision')
                ->orderBy('destinos_captura.destino');
        };
    }

    /**
     * Fecha de referencia para la antigüedad: la recepción si existe; si no, la
     * emisión del documento como sustituta. Usada para la distribución por tramo
     * (el detalle por fila delega el cálculo en FilaDetalle).
     */
    private function fechaReferencia(mixed $fila): mixed
    {
        $recepcion = $fila->fecha_recepcion ?? null;
        if ($recepcion !== null) {
            return $recepcion;
        }

        return $fila->fecha_emision_doc ?? null;
    }

    private function tramoDe(mixed $fechaReferencia, Carbon $corte): TramoAntiguedad
    {
        return TramoAntiguedad::desde($this->diasDesde($fechaReferencia, $corte));
    }

    /** Días entre la recepción y el corte; null si no hay fecha de referencia. */
    private function diasDesde(mixed $fechaRecepcion, Carbon $corte): ?int
    {
        if ($fechaRecepcion === null) {
            return null;
        }
        $fecha = $fechaRecepcion instanceof Carbon ? $fechaRecepcion : Carbon::parse($fechaRecepcion);

        // Días completos; recepción futura respecto al corte da negativo (incidencia).
        return (int) $fecha->startOfDay()->diffInDays($corte->copy()->startOfDay(), false);
    }

    /**
     * Una fila por (dependencia, es_encargado, estado_recepcion, prioridad)
     * con su conteo. Agrupar en SQL mantiene el cálculo en la base de datos.
     *
     * @return Collection<int, object>
     */
    private function agregarPorDependencia(): Collection
    {
        return $this->consultaBase()
            ->selectRaw('dependencia_codigo')
            ->selectRaw('(destinatario_empleado is not distinct from encargado_empleado) as es_entrada')
            ->selectRaw('estado_recepcion')
            ->selectRaw("coalesce(prioridad, '1') as prioridad")
            ->selectRaw('count(*) as cantidad')
            ->groupBy('dependencia_codigo', 'es_entrada', 'estado_recepcion', 'prioridad')
            ->get();
    }

    /**
     * Construye el mapa codigo => FilaPendientes con las cifras propias ya
     * colocadas, para todas las dependencias (incluidas las de saldo cero).
     *
     * @param  Collection<int, object>  $agregados
     * @return array<string, FilaPendientes>
     */
    private function construirArbol(Collection $agregados): array
    {
        $dependencias = Dependencia::query()
            ->orderBy('nivel')
            ->orderBy('codigo')
            ->get()
            ->keyBy('codigo');

        $filas = [];
        foreach ($dependencias as $codigo => $dep) {
            $padre = $dep->dependencia_padre_id
                ? $dependencias->firstWhere('id', $dep->dependencia_padre_id)
                : null;

            $filas[$codigo] = new FilaPendientes(
                codigo: $codigo,
                nombre: $dep->nombre,
                nivel: $dep->nivel,
                activa: $dep->activa,
                codigoPadre: $padre?->codigo,
            );
        }

        // Registrar hijas directas en cada padre.
        foreach ($filas as $codigo => $fila) {
            if ($fila->codigoPadre !== null && isset($filas[$fila->codigoPadre])) {
                $filas[$fila->codigoPadre]->hijas[] = $codigo;
            }
        }

        foreach ($agregados as $fila) {
            $destino = $filas[$fila->dependencia_codigo] ?? null;
            if ($destino === null) {
                continue; // Un catálogo faltante no debe eliminar el destino; se contabiliza aparte si hiciera falta.
            }

            $cantidad = (int) $fila->cantidad;
            $esEntrada = $this->esVerdadero($fila->es_entrada);
            $noLeido = $fila->estado_recepcion === EstadoRecepcion::NoLeido->value;

            if ($esEntrada) {
                $noLeido ? $destino->entradaNoLeidos += $cantidad : $destino->entradaRecibidos += $cantidad;
            } else {
                $noLeido ? $destino->delegadosNoLeidos += $cantidad : $destino->delegadosRecibidos += $cantidad;
            }

            match ($fila->prioridad) {
                '3' => $destino->prioridadMuyUrgente += $cantidad,
                '2' => $destino->prioridadUrgente += $cantidad,
                default => $destino->prioridadNormal += $cantidad,
            };
        }

        return $filas;
    }

    /**
     * Suma las cifras propias de cada dependencia a sus ancestros. Procesar
     * de mayor a menor nivel garantiza que las hojas estén listas antes que
     * sus padres.
     *
     * @param  array<string, FilaPendientes>  $filas
     */
    private function acumularRollUp(array $filas): void
    {
        $orden = array_values($filas);
        usort($orden, fn (FilaPendientes $a, FilaPendientes $b) => $b->nivel <=> $a->nivel);

        foreach ($filas as $fila) {
            // Inicializa el acumulado con las cifras propias.
            $fila->accEntradaNoLeidos = $fila->entradaNoLeidos;
            $fila->accEntradaRecibidos = $fila->entradaRecibidos;
            $fila->accDelegadosNoLeidos = $fila->delegadosNoLeidos;
            $fila->accDelegadosRecibidos = $fila->delegadosRecibidos;
            $fila->accPrioridadNormal = $fila->prioridadNormal;
            $fila->accPrioridadUrgente = $fila->prioridadUrgente;
            $fila->accPrioridadMuyUrgente = $fila->prioridadMuyUrgente;
        }

        foreach ($orden as $fila) {
            if ($fila->codigoPadre === null || ! isset($filas[$fila->codigoPadre])) {
                continue;
            }
            $padre = $filas[$fila->codigoPadre];
            $padre->accEntradaNoLeidos += $fila->accEntradaNoLeidos;
            $padre->accEntradaRecibidos += $fila->accEntradaRecibidos;
            $padre->accDelegadosNoLeidos += $fila->accDelegadosNoLeidos;
            $padre->accDelegadosRecibidos += $fila->accDelegadosRecibidos;
            $padre->accPrioridadNormal += $fila->accPrioridadNormal;
            $padre->accPrioridadUrgente += $fila->accPrioridadUrgente;
            $padre->accPrioridadMuyUrgente += $fila->accPrioridadMuyUrgente;
        }
    }

    private function esVerdadero(mixed $valor): bool
    {
        return $valor === true || $valor === 't' || $valor === 1 || $valor === '1';
    }
}
