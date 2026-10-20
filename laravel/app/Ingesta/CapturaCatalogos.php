<?php

namespace App\Ingesta;

use Illuminate\Support\Facades\DB;

/**
 * Lectura del SGD y escritura en PostgreSQL de los catálogos capturados por
 * publicación que exige el detalle enriquecido (§4–§6 del diseño): tipos
 * documentales, padrón de empleados, pertenencias empleado↔dependencia y
 * snapshot de dependencias.
 *
 * Es código compartido por la ingesta completa y la incremental, para que
 * ambas produzcan exactamente las mismas capturas: el padrón NO se limita a
 * destinatarios con pendientes, incluye activos sin pendientes e inactivos
 * referenciados, y se resuelve en el mismo corte de la publicación.
 *
 * Reglas de fuente verificadas (§10): empleado activo = CEMP_EST_EMP='1';
 * pertenencia por tres vías (CEMP_CO_DEPEND, CO_DEPENDENCIA y
 * TDTX_DEPENDENCIA_EMPLEADO con ES_EMP='0').
 */
class CapturaCatalogos
{
    private const LOTE = 1000;

    public function __construct(
        private readonly string $esquema = 'IDOSGD',
        private readonly string $conexionSgd = 'sgd',
    ) {}

    /**
     * Captura todos los catálogos de una publicación recién creada. Debe
     * ejecutarse dentro de la transacción de publicación. Idempotente por
     * borrado-recreación: si la publicación ya tenía capturas (p. ej. tras un
     * clon incremental), se reemplazan por completo.
     */
    public function capturar(int $publicacionId): void
    {
        $this->limpiar($publicacionId);
        $this->capturarTiposDocumentales($publicacionId);
        $this->capturarDependencias($publicacionId);
        $this->capturarEmpleados($publicacionId);
        $this->capturarPertenencias($publicacionId);
    }

    /** Borra las capturas previas de la publicación (para recrearlas íntegras). */
    public function limpiar(int $publicacionId): void
    {
        foreach ([
            'tipos_documentales_captura',
            'dependencias_captura',
            'empleado_dependencia_captura',
            'empleados_captura',
        ] as $tabla) {
            DB::table($tabla)->where('publicacion_id', $publicacionId)->delete();
        }
    }

    private function capturarTiposDocumentales(int $publicacionId): void
    {
        $sgd = DB::connection($this->conexionSgd);
        $filas = [];
        $ahora = now();
        foreach ($sgd->cursor("SELECT CDOC_TIPDOC, CDOC_DESDOC, CDOC_INDBAJ FROM {$this->esquema}.SI_MAE_TIPO_DOC") as $r) {
            $codigo = $this->texto($r->CDOC_TIPDOC);
            if ($codigo === null) {
                continue;
            }
            $filas[] = [
                'publicacion_id' => $publicacionId,
                'codigo' => $codigo,
                'descripcion' => $this->texto($r->CDOC_DESDOC),
                'baja' => $this->texto($r->CDOC_INDBAJ),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
            $filas = $this->vaciarSi($filas, 'tipos_documentales_captura');
        }
        $this->vaciar($filas, 'tipos_documentales_captura');
    }

    private function capturarDependencias(int $publicacionId): void
    {
        $sgd = DB::connection($this->conexionSgd);
        $filas = [];
        $ahora = now();
        $sql = "SELECT CO_DEPENDENCIA, DE_DEPENDENCIA, CO_EMPLEADO, IN_BAJA
                FROM {$this->esquema}.RHTM_DEPENDENCIA";
        foreach ($sgd->cursor($sql) as $r) {
            $codigo = $this->texto($r->CO_DEPENDENCIA);
            if ($codigo === null) {
                continue;
            }
            $filas[] = [
                'publicacion_id' => $publicacionId,
                'codigo' => $codigo,
                'nombre' => $this->texto($r->DE_DEPENDENCIA),
                'encargado_codigo' => $this->texto($r->CO_EMPLEADO),
                'baja' => $this->texto($r->IN_BAJA),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
            $filas = $this->vaciarSi($filas, 'dependencias_captura');
        }
        $this->vaciar($filas, 'dependencias_captura');
    }

    /**
     * Padrón completo de empleados (no solo destinatarios con pendientes).
     * Conserva nombre, estado y las dos dependencias originales.
     */
    private function capturarEmpleados(int $publicacionId): void
    {
        $sgd = DB::connection($this->conexionSgd);
        $filas = [];
        $ahora = now();
        $sql = "SELECT CEMP_CODEMP, CEMP_APEPAT, CEMP_APEMAT, CEMP_DENOM,
                       CEMP_EST_EMP, CEMP_INDBAJ, CEMP_CO_DEPEND, CO_DEPENDENCIA
                FROM {$this->esquema}.RHTM_PER_EMPLEADOS";
        foreach ($sgd->cursor($sql) as $r) {
            $codigo = $this->texto($r->CEMP_CODEMP);
            if ($codigo === null) {
                continue;
            }
            $filas[] = [
                'publicacion_id' => $publicacionId,
                'codigo' => $codigo,
                'apellido_paterno' => $this->texto($r->CEMP_APEPAT),
                'apellido_materno' => $this->texto($r->CEMP_APEMAT),
                'denominacion' => $this->texto($r->CEMP_DENOM),
                'estado_empleado_codigo' => $this->texto($r->CEMP_EST_EMP),
                'baja_codigo' => $this->texto($r->CEMP_INDBAJ),
                'dependencia_principal_codigo' => $this->texto($r->CEMP_CO_DEPEND),
                'dependencia_configurada_codigo' => $this->texto($r->CO_DEPENDENCIA),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
            $filas = $this->vaciarSi($filas, 'empleados_captura');
        }
        $this->vaciar($filas, 'empleados_captura');
    }

    /**
     * Pertenencias empleado↔dependencia por las tres vías, deduplicadas por
     * (empleado, dependencia). Se arma en memoria porque el padrón es pequeño
     * (cientos de empleados), y así una sola fila integra las vías de un mismo
     * par. es_activa refleja CEMP_EST_EMP del empleado.
     */
    private function capturarPertenencias(int $publicacionId): void
    {
        $sgd = DB::connection($this->conexionSgd);
        $ahora = now();

        // Mapa de actividad por empleado, para marcar es_activa.
        $activo = [];
        foreach ($sgd->cursor("SELECT CEMP_CODEMP, CEMP_EST_EMP FROM {$this->esquema}.RHTM_PER_EMPLEADOS") as $r) {
            $codigo = $this->texto($r->CEMP_CODEMP);
            if ($codigo !== null) {
                $activo[$codigo] = $this->texto($r->CEMP_EST_EMP) === '1';
            }
        }

        /** @var array<string, array{emp:string,dep:string,principal:bool,configurada:bool,asignada:bool}> $pares */
        $pares = [];
        $registrar = function (?string $emp, ?string $dep, string $via) use (&$pares) {
            if ($emp === null || $dep === null) {
                return;
            }
            $clave = $emp.'|'.$dep;
            $pares[$clave] ??= ['emp' => $emp, 'dep' => $dep, 'principal' => false, 'configurada' => false, 'asignada' => false];
            $pares[$clave][$via] = true;
        };

        // Vías 1 y 2: columnas del propio empleado.
        foreach ($sgd->cursor("SELECT CEMP_CODEMP, CEMP_CO_DEPEND, CO_DEPENDENCIA FROM {$this->esquema}.RHTM_PER_EMPLEADOS") as $r) {
            $emp = $this->texto($r->CEMP_CODEMP);
            $registrar($emp, $this->texto($r->CEMP_CO_DEPEND), 'principal');
            $registrar($emp, $this->texto($r->CO_DEPENDENCIA), 'configurada');
        }

        // Vía 3: asignaciones de TDTX_DEPENDENCIA_EMPLEADO (ES_EMP='0').
        foreach ($sgd->cursor("SELECT CO_EMP, CO_DEP, ES_EMP FROM {$this->esquema}.TDTX_DEPENDENCIA_EMPLEADO WHERE ES_EMP = '0'") as $r) {
            $registrar($this->texto($r->CO_EMP), $this->texto($r->CO_DEP), 'asignada');
        }

        $filas = [];
        foreach ($pares as $p) {
            $filas[] = [
                'publicacion_id' => $publicacionId,
                'empleado_codigo' => $p['emp'],
                'dependencia_codigo' => $p['dep'],
                'es_principal' => $p['principal'],
                'es_configurada' => $p['configurada'],
                'es_asignada' => $p['asignada'],
                'es_activa' => $activo[$p['emp']] ?? false,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
            $filas = $this->vaciarSi($filas, 'empleado_dependencia_captura');
        }
        $this->vaciar($filas, 'empleado_dependencia_captura');
    }

    /**
     * Inserta el lote si alcanza el tamaño; devuelve el buffer (vacío si se
     * insertó, el mismo si no).
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<int, array<string, mixed>>
     */
    private function vaciarSi(array $filas, string $tabla): array
    {
        if (count($filas) >= self::LOTE) {
            DB::table($tabla)->insert($filas);

            return [];
        }

        return $filas;
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function vaciar(array $filas, string $tabla): void
    {
        if ($filas !== []) {
            DB::table($tabla)->insert($filas);
        }
    }

    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
