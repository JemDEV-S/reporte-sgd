<?php

namespace App\Ingesta;

use App\Models\Publicacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Extrae del SGD (SQL Server) los destinos pendientes del perfil institucional
 * y los publica en PostgreSQL como una nueva publicación.
 *
 * El universo reproduce la regla del contador de acceso total (SQL 05/06 de
 * fase 1): destinos con recepción 0/1, no eliminados, de remitos cuya emisión
 * no esté en (5,7,9,8), prioridad 1/2/3 y sin una respuesta referenciada viva.
 * La clasificación entrada/delegados se resuelve contra el encargado de la
 * dependencia (RHTM_DEPENDENCIA.CO_EMPLEADO), que se conserva por destino.
 *
 * La publicación es atómica: todo ocurre en una transacción de PostgreSQL y,
 * si falla, la última publicación vigente permanece disponible.
 */
class IngestaSgd
{
    /** Tamaño de inserción por lote en PostgreSQL. */
    private const LOTE = 1000;

    public function __construct(
        private readonly string $esquema = 'IDOSGD',
        private readonly string $conexionSgd = 'sgd',
    ) {}

    /**
     * Ejecuta una ingesta completa. Devuelve el resumen de lo publicado.
     *
     * @param  string|null  $soloDependencia  limita el ámbito a una dependencia (código); null = todas
     */
    public function ejecutar(?string $soloDependencia = null, ?callable $progreso = null): ResultadoIngesta
    {
        $inicio = Carbon::now('America/Lima');

        // Marca de agua ANTES de leer: el mayor FE_USE_MOD del origen en este
        // instante. Lo que se escriba después quedará para la próxima incremental.
        $marcaAgua = $this->marcaAguaActual();

        // Las dependencias se publican primero para que el catálogo esté completo,
        // incluso las de saldo cero y las dadas de baja.
        $dependencias = $this->leerDependencias($soloDependencia);

        return DB::transaction(function () use ($dependencias, $soloDependencia, $inicio, $marcaAgua, $progreso) {
            $publicacion = Publicacion::create([
                'fecha_publicacion' => $inicio,
                'perfil' => 'institucional',
                'version_reglas' => 'R01-R12',
                'estado' => 'construyendo',
                'marca_agua' => $marcaAgua,
                'tipo_ingesta' => 'completa',
                'nota' => $soloDependencia !== null
                    ? "Ingesta del SGD limitada a la dependencia {$soloDependencia}."
                    : 'Ingesta del SGD, perfil institucional de acceso total.',
            ]);

            $this->publicarDependencias($dependencias);

            // Catálogos capturados por publicación (tipos, padrón de empleados,
            // pertenencias y dependencias), necesarios para el detalle enriquecido.
            (new CapturaCatalogos($this->esquema, $this->conexionSgd))->capturar($publicacion->id);

            $totales = $this->copiarDestinos($publicacion, $soloDependencia, $progreso);

            // Marca la publicación como vigente solo si todo terminó bien; relega
            // la anterior a "histórica" para conservar la trazabilidad.
            Publicacion::where('estado', 'vigente')->update(['estado' => 'historica']);
            $publicacion->update([
                'estado' => 'vigente',
                'version_datos_detalle' => Publicacion::VERSION_DATOS_DETALLE,
                'nota' => $publicacion->nota." Destinos publicados: {$totales['destinos']}.",
            ]);

            return new ResultadoIngesta(
                publicacion: $publicacion->fresh(),
                dependencias: count($dependencias),
                documentos: $totales['documentos'],
                destinos: $totales['destinos'],
            );
        });
    }

    /**
     * Catálogo de dependencias con su jerarquía y encargado actual.
     *
     * @return array<int, object>
     */
    private function leerDependencias(?string $soloDependencia): array
    {
        $sgd = DB::connection($this->conexionSgd);
        $sql = "SELECT CO_DEPENDENCIA AS codigo, DE_DEPENDENCIA AS nombre,
                       CO_DEPEN_PADRE AS padre, CO_NIVEL AS nivel,
                       CO_EMPLEADO AS encargado, IN_BAJA AS baja
                FROM {$this->esquema}.RHTM_DEPENDENCIA";
        $bindings = [];
        if ($soloDependencia !== null) {
            $sql .= ' WHERE CO_DEPENDENCIA = ?';
            $bindings[] = $soloDependencia;
        }

        return $sgd->select($sql, $bindings);
    }

    /**
     * Inserta/actualiza el catálogo de dependencias en PostgreSQL, resolviendo
     * la jerarquía por código de padre. Se procesa por nivel para que el padre
     * exista antes que la hija.
     *
     * @param  array<int, object>  $dependencias
     */
    private function publicarDependencias(array $dependencias): void
    {
        // Mapa código => id ya persistido, para enlazar padres.
        usort($dependencias, fn ($a, $b) => (int) $a->nivel <=> (int) $b->nivel);

        $idPorCodigo = [];
        foreach ($dependencias as $dep) {
            $codigo = $this->texto($dep->codigo);
            $padreCodigo = $this->texto($dep->padre);
            $padreId = $padreCodigo !== null ? ($idPorCodigo[$padreCodigo] ?? null) : null;

            $id = DB::table('dependencias')->updateOrInsert(
                ['codigo' => $codigo],
                [
                    'nombre' => (string) $dep->nombre,
                    'dependencia_padre_id' => $padreId,
                    'nivel' => $this->nivelEntero($dep->nivel),
                    'activa' => $this->texto($dep->baja) !== '1',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            $idPorCodigo[$codigo] = DB::table('dependencias')->where('codigo', $codigo)->value('id');
        }
    }

    /**
     * Recorre los destinos elegibles del SGD en streaming y los inserta por
     * lotes en destinos_captura y documentos_captura.
     *
     * @return array{documentos: int, destinos: int}
     */
    private function copiarDestinos(Publicacion $publicacion, ?string $soloDependencia, ?callable $progreso): array
    {
        $sgd = DB::connection($this->conexionSgd);
        [$sql, $bindings] = $this->consultaDestinos($soloDependencia);

        $loteDestinos = [];
        $loteDocumentos = [];
        $documentosVistos = [];
        $totalDestinos = 0;
        $totalDocumentos = 0;
        $ahora = now();

        foreach ($sgd->cursor($sql, $bindings) as $fila) {
            $anio = $this->texto($fila->NU_ANN);
            $emision = $this->texto($fila->NU_EMI);
            // NU_DES es numérico en el SGD; se conserva como entero en texto.
            $destino = $this->enteroTexto($fila->NU_DES);
            $claveDoc = $anio.'|'.$emision;

            $loteDestinos[] = [
                'publicacion_id' => $publicacion->id,
                'anio' => $anio,
                'emision' => $emision,
                'destino' => $destino,
                'tipo_destino' => $this->texto($fila->TI_DES) ?? '01',
                'dependencia_codigo' => $this->texto($fila->CO_DEP_DES) ?? '',
                'destinatario_empleado' => $this->texto($fila->CO_EMP_DES),
                'encargado_empleado' => $this->texto($fila->ENCARGADO),
                'estado_recepcion' => $this->texto($fila->ES_DOC_REC) ?? '0',
                'activo' => true,
                'prioridad' => $this->texto($fila->CO_PRI),
                'fecha_recepcion' => $fila->FE_REC_DOC,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
            $totalDestinos++;

            // Un documento de cabecera por clave fuente, una sola vez. Resuelve
            // los metadatos legibles (número, origen) por cabecera, no por destino.
            if (! isset($documentosVistos[$claveDoc])) {
                $documentosVistos[$claveDoc] = true;
                $tipoEmisor = $this->texto($fila->TI_EMI);
                $numeroEmisor = $this->texto($fila->NU_DOC_EMI);
                $sigla = $this->texto($fila->DE_DOC_SIG);
                $loteDocumentos[] = [
                    'publicacion_id' => $publicacion->id,
                    'anio' => $anio,
                    'emision' => $emision,
                    'tipo_documental' => $this->texto($fila->CO_TIP_DOC_ADM),
                    'estado_emision' => $this->texto($fila->ES_DOC_EMI),
                    'eliminado' => false,
                    'fecha_emision' => $fila->FE_EMI,
                    'tipo_emisor' => $tipoEmisor,
                    'numero_documento' => MetadatosDocumento::numero(
                        $tipoEmisor, $numeroEmisor, $anio, $sigla, $this->texto($fila->NU_DOC_RESUMEN),
                    ),
                    'numero_emisor' => $numeroEmisor,
                    'sigla_documento' => $sigla,
                    'numero_incompleto' => MetadatosDocumento::incompleto($tipoEmisor, $numeroEmisor, $anio, $sigla),
                    'asunto' => $this->texto($fila->DE_ASU),
                    'dependencia_emisora_codigo' => $this->texto($fila->CO_DEP_EMI),
                    'emisor_empleado_codigo' => $this->texto($fila->CO_EMP_EMI),
                    'origen_descripcion' => $this->texto($fila->ORIGEN_DESC),
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
                $totalDocumentos++;
            }

            if (count($loteDestinos) >= self::LOTE) {
                DB::table('destinos_captura')->insert($loteDestinos);
                $loteDestinos = [];
            }
            if (count($loteDocumentos) >= self::LOTE) {
                DB::table('documentos_captura')->insert($loteDocumentos);
                $loteDocumentos = [];
            }
            if ($progreso !== null && $totalDestinos % 5000 === 0) {
                $progreso($totalDestinos);
            }
        }

        if ($loteDestinos !== []) {
            DB::table('destinos_captura')->insert($loteDestinos);
        }
        if ($loteDocumentos !== []) {
            DB::table('documentos_captura')->insert($loteDocumentos);
        }

        return ['documentos' => $totalDocumentos, 'destinos' => $totalDestinos];
    }

    /**
     * Consulta de destinos elegibles. Trae el encargado de la dependencia para
     * clasificar entrada/delegados en la plataforma. Reproduce las reglas del
     * contador de acceso total (SQL 05 de fase 1).
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function consultaDestinos(?string $soloDependencia): array
    {
        $e = $this->esquema;
        $bindings = [];
        $filtroDep = '';
        if ($soloDependencia !== null) {
            $filtroDep = ' AND d.CO_DEP_DES = ?';
            $bindings[] = $soloDependencia;
        }

        $sql = <<<SQL
            SELECT d.NU_ANN, d.NU_EMI, d.NU_DES, d.TI_DES,
                   d.CO_DEP_DES, d.CO_EMP_DES, d.ES_DOC_REC, d.CO_PRI, d.FE_REC_DOC,
                   dep.CO_EMPLEADO AS ENCARGADO,
                   r.ES_DOC_EMI, r.CO_TIP_DOC_ADM, r.FE_EMI,
                   r.TI_EMI, r.NU_DOC_EMI, r.DE_DOC_SIG, r.DE_ASU,
                   r.CO_DEP_EMI, r.CO_EMP_EMI,
                   res.NU_DOC AS NU_DOC_RESUMEN,
                   {$e}.PK_SGD_DESCRIPCION_TI_EMI_EMP(r.NU_ANN, r.NU_EMI) AS ORIGEN_DESC
            FROM {$e}.TDTV_DESTINOS d
            JOIN {$e}.TDTV_REMITOS r
              ON r.NU_ANN = d.NU_ANN AND r.NU_EMI = d.NU_EMI
            LEFT JOIN {$e}.RHTM_DEPENDENCIA dep
              ON dep.CO_DEPENDENCIA = d.CO_DEP_DES
            LEFT JOIN {$e}.TDTX_REMITOS_RESUMEN res
              ON res.NU_ANN = d.NU_ANN AND res.NU_EMI = d.NU_EMI
            WHERE d.ES_DOC_REC IN ('0','1')
              AND d.ES_ELI = '0'
              AND r.ES_DOC_EMI NOT IN ('5','7','9','8')
              AND d.CO_PRI IN ('1','2','3')
              AND d.TI_DES = '01'
              {$filtroDep}
              AND NOT EXISTS (
                  SELECT 1
                  FROM {$e}.TDTR_REFERENCIA ref
                  JOIN {$e}.TDTV_REMITOS respuesta
                    ON respuesta.NU_ANN = ref.NU_ANN AND respuesta.NU_EMI = ref.NU_EMI
                  WHERE ref.NU_ANN_REF = d.NU_ANN
                    AND ref.NU_EMI_REF = d.NU_EMI
                    AND ref.NU_DES_REF = d.NU_DES
                    AND respuesta.ES_ELI = '0'
                    AND respuesta.ES_DOC_EMI <> '9'
              )
            SQL;

        return [$sql, $bindings];
    }

    /**
     * Mayor FE_USE_MOD entre destinos y remitos: define hasta dónde cubre esta
     * publicación y desde dónde parte la siguiente ingesta incremental.
     */
    private function marcaAguaActual(): ?string
    {
        $sgd = DB::connection($this->conexionSgd);
        $fila = $sgd->selectOne(
            "SELECT CONVERT(varchar(27), m, 121) AS marca FROM (
                SELECT MAX(x) AS m FROM (
                    SELECT MAX(FE_USE_MOD) AS x FROM {$this->esquema}.TDTV_DESTINOS
                    UNION ALL
                    SELECT MAX(FE_USE_MOD) AS x FROM {$this->esquema}.TDTV_REMITOS
                ) u
            ) t"
        );

        return $fila?->marca;
    }

    /** Normaliza un valor de SQL Server a texto recortado, o null si viene vacío. */
    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private function nivelEntero(mixed $nivel): int
    {
        $texto = $this->texto($nivel);

        return is_numeric($texto) ? (int) $texto : 0;
    }

    /** Convierte un numérico del SGD a su representación entera en texto. */
    private function enteroTexto(mixed $valor): string
    {
        $texto = $this->texto($valor) ?? '0';

        return is_numeric($texto) ? (string) (int) $texto : $texto;
    }
}
