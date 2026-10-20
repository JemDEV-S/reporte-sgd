<?php

namespace App\Ingesta;

use App\Models\Publicacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Ingesta incremental sobre copia. Clona la última publicación vigente y aplica
 * solo los cambios ocurridos en el SGD desde su marca de agua (FE_USE_MOD),
 * con un solapamiento de seguridad para no perder escrituras en el límite.
 *
 * Mantiene el contrato de snapshot: la publicación resultante es completa y
 * conciliable igual que una ingesta total, pero mucho más barata de construir.
 *
 * Reglas de aplicación por clave de destino cambiada:
 *  - Sigue siendo pendiente (cumple el filtro institucional) → upsert.
 *  - Dejó de ser pendiente (atendido, eliminado, emisión excluida, referido) → borrado.
 *
 * Un destino puede cambiar de elegibilidad por su propio FE_USE_MOD o por el de
 * su remito (p. ej. una emisión que pasa a estado excluido); la consulta de
 * candidatos considera ambos orígenes.
 */
class IngestaIncrementalSgd
{
    private const LOTE = 1000;

    /** Minutos que se reprocesan antes de la marca para cubrir escrituras concurrentes. */
    private const SOLAPAMIENTO_MINUTOS = 15;

    public function __construct(
        private readonly string $esquema = 'IDOSGD',
        private readonly string $conexionSgd = 'sgd',
        /**
         * Si recapturar catálogos y cabeceras leyendo del SGD. Se desactiva en
         * las pruebas que ejercitan aplicarSobreCopia() con candidatos inyectados
         * sin una conexión SQL Server disponible.
         */
        private readonly bool $capturarCatalogos = true,
    ) {}

    public function ejecutar(?callable $progreso = null): ResultadoIncremental
    {
        $base = Publicacion::vigente();
        if ($base === null) {
            throw new RuntimeException('No hay una publicación vigente que clonar; ejecuta primero una ingesta completa.');
        }
        if ($base->marca_agua === null) {
            throw new RuntimeException('La publicación vigente no tiene marca de agua; vuelve a ejecutar una ingesta completa.');
        }

        $desde = Carbon::parse($base->marca_agua)->subMinutes(self::SOLAPAMIENTO_MINUTOS);
        $marcaNueva = $this->marcaAguaActual();

        // Catálogo de dependencias siempre al día (barato y necesario para clasificar).
        $dependencias = $this->leerDependencias();

        // Candidatos leídos fuera de la transacción para no alargarla.
        $candidatos = $this->leerCandidatos($desde);

        return $this->aplicarSobreCopia($base, $dependencias, $candidatos, $marcaNueva, $progreso);
    }

    /**
     * Construye la publicación incremental a partir de candidatos ya leídos.
     * Separado de la lectura del SGD para poder verificarse de forma aislada.
     *
     * @param  array<int, object>  $dependencias
     * @param  array<int, object>  $candidatos
     */
    public function aplicarSobreCopia(
        Publicacion $base,
        array $dependencias,
        array $candidatos,
        ?string $marcaNueva,
        ?callable $progreso = null,
    ): ResultadoIncremental {
        return DB::transaction(function () use ($base, $dependencias, $candidatos, $marcaNueva, $progreso) {
            $nueva = Publicacion::create([
                'fecha_publicacion' => Carbon::now('America/Lima'),
                'perfil' => 'institucional',
                'version_reglas' => 'R01-R12',
                'estado' => 'construyendo',
                'marca_agua' => $marcaNueva,
                'tipo_ingesta' => 'incremental',
                'nota' => "Ingesta incremental sobre la publicación #{$base->id}.",
            ]);

            $this->clonar($base->id, $nueva->id);
            if ($dependencias !== []) {
                $this->publicarDependencias($dependencias);
            }

            // Recaptura completa de catálogos por ciclo: el padrón es pequeño y
            // sus cambios no se reflejan en FE_USE_MOD de remitos/destinos.
            // Se omite solo en los tests que inyectan candidatos sin conexión SGD.
            if ($this->capturarCatalogos) {
                (new CapturaCatalogos($this->esquema, $this->conexionSgd))->capturar($nueva->id);
            }

            $metricas = $this->aplicarCambios($nueva->id, $candidatos, $progreso);

            Publicacion::where('estado', 'vigente')->update(['estado' => 'historica']);
            $totalDestinos = DB::table('destinos_captura')->where('publicacion_id', $nueva->id)->count();
            $nueva->update([
                'estado' => 'vigente',
                'version_datos_detalle' => Publicacion::VERSION_DATOS_DETALLE,
                'nota' => $nueva->nota." Altas/cambios: {$metricas['upserts']}, bajas: {$metricas['bajas']}. Destinos vigentes: {$totalDestinos}.",
            ]);

            return new ResultadoIncremental(
                publicacion: $nueva->fresh(),
                basePublicacion: $base->id,
                candidatos: count($candidatos),
                upserts: $metricas['upserts'],
                bajas: $metricas['bajas'],
                destinos: $totalDestinos,
            );
        });
    }

    /** Copia los destinos y documentos de la publicación base a la nueva. */
    private function clonar(int $origen, int $destino): void
    {
        DB::statement(
            'INSERT INTO destinos_captura
                (publicacion_id, anio, emision, destino, tipo_destino, dependencia_codigo,
                 destinatario_empleado, encargado_empleado, estado_recepcion, activo, prioridad,
                 fecha_recepcion, created_at, updated_at)
             SELECT ?, anio, emision, destino, tipo_destino, dependencia_codigo,
                 destinatario_empleado, encargado_empleado, estado_recepcion, activo, prioridad,
                 fecha_recepcion, created_at, updated_at
             FROM destinos_captura WHERE publicacion_id = ?',
            [$destino, $origen]
        );

        DB::statement(
            'INSERT INTO documentos_captura
                (publicacion_id, anio, emision, tipo_documental, estado_emision, eliminado, fecha_emision,
                 tipo_emisor, numero_documento, numero_emisor, sigla_documento, numero_incompleto, asunto,
                 dependencia_emisora_codigo, emisor_empleado_codigo, origen_descripcion, created_at, updated_at)
             SELECT ?, anio, emision, tipo_documental, estado_emision, eliminado, fecha_emision,
                 tipo_emisor, numero_documento, numero_emisor, sigla_documento, numero_incompleto, asunto,
                 dependencia_emisora_codigo, emisor_empleado_codigo, origen_descripcion, created_at, updated_at
             FROM documentos_captura WHERE publicacion_id = ?',
            [$destino, $origen]
        );
    }

    /**
     * Aplica los candidatos sobre la copia: upsert de los que siguen pendientes,
     * borrado de los que dejaron de serlo.
     *
     * @param  array<int, object>  $candidatos
     * @return array{upserts: int, bajas: int}
     */
    private function aplicarCambios(int $publicacionId, array $candidatos, ?callable $progreso): array
    {
        $upserts = 0;
        $bajas = 0;
        $filasUpsert = [];
        $clavesBaja = [];
        $cabeceras = [];
        $cabecerasVistas = [];
        $ahora = now();
        $procesados = 0;

        foreach ($candidatos as $c) {
            $anio = $this->texto($c->NU_ANN);
            $emision = $this->texto($c->NU_EMI);
            $destino = $this->enteroTexto($c->NU_DES);

            if ((int) $c->ES_PENDIENTE === 1) {
                // Cabecera del documento, una sola vez por (año, emisión). Insertar
                // la cabecera antes que el destino que la referencia.
                $claveDoc = $anio.'|'.$emision;
                if (! isset($cabecerasVistas[$claveDoc]) && $this->tieneCabecera($c)) {
                    $cabecerasVistas[$claveDoc] = true;
                    $cabeceras[] = $this->filaCabecera($publicacionId, $anio, $emision, $c, $ahora);
                }

                $filasUpsert[] = [
                    'publicacion_id' => $publicacionId,
                    'anio' => $anio,
                    'emision' => $emision,
                    'destino' => $destino,
                    'tipo_destino' => $this->texto($c->TI_DES) ?? '01',
                    'dependencia_codigo' => $this->texto($c->CO_DEP_DES) ?? '',
                    'destinatario_empleado' => $this->texto($c->CO_EMP_DES),
                    'encargado_empleado' => $this->texto($c->ENCARGADO),
                    'estado_recepcion' => $this->texto($c->ES_DOC_REC) ?? '0',
                    'activo' => true,
                    'prioridad' => $this->texto($c->CO_PRI),
                    'fecha_recepcion' => $c->FE_REC_DOC,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
                $upserts++;
            } else {
                $clavesBaja[] = [$anio, $emision, $destino];
                $bajas++;
            }

            if (count($cabeceras) >= self::LOTE) {
                $this->upsertCabeceras($cabeceras);
                $cabeceras = [];
            }
            if (count($filasUpsert) >= self::LOTE) {
                // Las cabeceras pendientes de este lote primero, por integridad.
                if ($cabeceras !== []) {
                    $this->upsertCabeceras($cabeceras);
                    $cabeceras = [];
                }
                $this->upsertDestinos($filasUpsert);
                $filasUpsert = [];
            }
            if (count($clavesBaja) >= self::LOTE) {
                $this->borrarDestinos($publicacionId, $clavesBaja);
                $clavesBaja = [];
            }

            if ($progreso !== null && (++$procesados % 2000 === 0)) {
                $progreso($procesados);
            }
        }

        if ($cabeceras !== []) {
            $this->upsertCabeceras($cabeceras);
        }
        if ($filasUpsert !== []) {
            $this->upsertDestinos($filasUpsert);
        }
        if ($clavesBaja !== []) {
            $this->borrarDestinos($publicacionId, $clavesBaja);
        }

        return ['upserts' => $upserts, 'bajas' => $bajas];
    }

    /**
     * Inserta o actualiza destinos por su clave natural dentro de la publicación.
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function upsertDestinos(array $filas): void
    {
        DB::table('destinos_captura')->upsert(
            $filas,
            ['publicacion_id', 'anio', 'emision', 'destino'],
            ['tipo_destino', 'dependencia_codigo', 'destinatario_empleado', 'encargado_empleado',
                'estado_recepcion', 'activo', 'prioridad', 'fecha_recepcion', 'updated_at'],
        );
    }

    /** ¿El candidato trae los campos de cabecera (los inyectados en pruebas no)? */
    private function tieneCabecera(object $c): bool
    {
        return property_exists($c, 'FE_EMI') || property_exists($c, 'TI_EMI');
    }

    /**
     * Compone la fila de cabecera para upsert, resolviendo número y origen con
     * el mismo criterio que la ingesta completa.
     *
     * @return array<string, mixed>
     */
    private function filaCabecera(int $publicacionId, ?string $anio, ?string $emision, object $c, mixed $ahora): array
    {
        $tipoEmisor = $this->texto($c->TI_EMI ?? null);
        $numeroEmisor = $this->texto($c->NU_DOC_EMI ?? null);
        $sigla = $this->texto($c->DE_DOC_SIG ?? null);

        return [
            'publicacion_id' => $publicacionId,
            'anio' => $anio,
            'emision' => $emision,
            'tipo_documental' => $this->texto($c->CO_TIP_DOC_ADM ?? null),
            'estado_emision' => $this->texto($c->ES_DOC_EMI ?? null),
            'eliminado' => false,
            'fecha_emision' => $c->FE_EMI ?? null,
            'tipo_emisor' => $tipoEmisor,
            'numero_documento' => MetadatosDocumento::numero(
                $tipoEmisor, $numeroEmisor, $anio, $sigla, $this->texto($c->NU_DOC_RESUMEN ?? null),
            ),
            'numero_emisor' => $numeroEmisor,
            'sigla_documento' => $sigla,
            'numero_incompleto' => MetadatosDocumento::incompleto($tipoEmisor, $numeroEmisor, $anio, $sigla),
            'asunto' => $this->texto($c->DE_ASU ?? null),
            'dependencia_emisora_codigo' => $this->texto($c->CO_DEP_EMI ?? null),
            'emisor_empleado_codigo' => $this->texto($c->CO_EMP_EMI ?? null),
            'origen_descripcion' => $this->texto($c->ORIGEN_DESC ?? null),
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
    }

    /**
     * Inserta o actualiza cabeceras por su clave natural dentro de la publicación,
     * de modo que un documento nuevo o modificado tenga sus metadatos completos.
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function upsertCabeceras(array $filas): void
    {
        DB::table('documentos_captura')->upsert(
            $filas,
            ['publicacion_id', 'anio', 'emision'],
            ['tipo_documental', 'estado_emision', 'eliminado', 'fecha_emision',
                'tipo_emisor', 'numero_documento', 'numero_emisor', 'sigla_documento',
                'numero_incompleto', 'asunto', 'dependencia_emisora_codigo',
                'emisor_empleado_codigo', 'origen_descripcion', 'updated_at'],
        );
    }

    /**
     * Borra de la copia los destinos que dejaron de ser pendientes.
     *
     * @param  array<int, array{0: ?string, 1: ?string, 2: string}>  $claves
     */
    private function borrarDestinos(int $publicacionId, array $claves): void
    {
        DB::table('destinos_captura')
            ->where('publicacion_id', $publicacionId)
            ->where(function ($query) use ($claves) {
                foreach ($claves as [$anio, $emision, $destino]) {
                    $query->orWhere(fn ($q) => $q
                        ->where('anio', $anio)
                        ->where('emision', $emision)
                        ->where('destino', $destino));
                }
            })
            ->delete();
    }

    /**
     * Destinos tocados desde $desde (por su propio FE_USE_MOD o el de su remito),
     * con una bandera de si cumplen el filtro institucional de pendiente.
     *
     * @return array<int, object>
     */
    private function leerCandidatos(Carbon $desde): array
    {
        $e = $this->esquema;
        $sql = <<<SQL
            SELECT d.NU_ANN, d.NU_EMI, d.NU_DES, d.TI_DES,
                   d.CO_DEP_DES, d.CO_EMP_DES, d.ES_DOC_REC, d.CO_PRI, d.FE_REC_DOC,
                   dep.CO_EMPLEADO AS ENCARGADO,
                   r.ES_DOC_EMI, r.CO_TIP_DOC_ADM, r.FE_EMI,
                   r.TI_EMI, r.NU_DOC_EMI, r.DE_DOC_SIG, r.DE_ASU,
                   r.CO_DEP_EMI, r.CO_EMP_EMI,
                   res.NU_DOC AS NU_DOC_RESUMEN,
                   {$e}.PK_SGD_DESCRIPCION_TI_EMI_EMP(r.NU_ANN, r.NU_EMI) AS ORIGEN_DESC,
                   CASE WHEN d.ES_DOC_REC IN ('0','1')
                             AND d.ES_ELI = '0'
                             AND d.TI_DES = '01'
                             AND d.CO_PRI IN ('1','2','3')
                             AND r.ES_DOC_EMI NOT IN ('5','7','9','8')
                             AND NOT EXISTS (
                                 SELECT 1 FROM {$e}.TDTR_REFERENCIA ref
                                 JOIN {$e}.TDTV_REMITOS resp
                                   ON resp.NU_ANN = ref.NU_ANN AND resp.NU_EMI = ref.NU_EMI
                                 WHERE ref.NU_ANN_REF = d.NU_ANN
                                   AND ref.NU_EMI_REF = d.NU_EMI
                                   AND ref.NU_DES_REF = d.NU_DES
                                   AND resp.ES_ELI = '0' AND resp.ES_DOC_EMI <> '9')
                        THEN 1 ELSE 0 END AS ES_PENDIENTE
            FROM {$e}.TDTV_DESTINOS d
            JOIN {$e}.TDTV_REMITOS r
              ON r.NU_ANN = d.NU_ANN AND r.NU_EMI = d.NU_EMI
            LEFT JOIN {$e}.RHTM_DEPENDENCIA dep
              ON dep.CO_DEPENDENCIA = d.CO_DEP_DES
            LEFT JOIN {$e}.TDTX_REMITOS_RESUMEN res
              ON res.NU_ANN = d.NU_ANN AND res.NU_EMI = d.NU_EMI
            WHERE d.FE_USE_MOD > ? OR r.FE_USE_MOD > ?
            SQL;

        $marca = $desde->format('Y-m-d H:i:s');

        return DB::connection($this->conexionSgd)->select($sql, [$marca, $marca]);
    }

    private function marcaAguaActual(): ?string
    {
        $fila = DB::connection($this->conexionSgd)->selectOne(
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

    /** @return array<int, object> */
    private function leerDependencias(): array
    {
        return DB::connection($this->conexionSgd)->select(
            "SELECT CO_DEPENDENCIA AS codigo, DE_DEPENDENCIA AS nombre,
                    CO_DEPEN_PADRE AS padre, CO_NIVEL AS nivel,
                    CO_EMPLEADO AS encargado, IN_BAJA AS baja
             FROM {$this->esquema}.RHTM_DEPENDENCIA"
        );
    }

    /** @param array<int, object> $dependencias */
    private function publicarDependencias(array $dependencias): void
    {
        usort($dependencias, fn ($a, $b) => (int) $a->nivel <=> (int) $b->nivel);

        $idPorCodigo = [];
        foreach ($dependencias as $dep) {
            $codigo = $this->texto($dep->codigo);
            $padreCodigo = $this->texto($dep->padre);
            $padreId = $padreCodigo !== null ? ($idPorCodigo[$padreCodigo] ?? null) : null;

            DB::table('dependencias')->updateOrInsert(
                ['codigo' => $codigo],
                [
                    'nombre' => (string) $dep->nombre,
                    'dependencia_padre_id' => $padreId,
                    'nivel' => is_numeric($this->texto($dep->nivel)) ? (int) $dep->nivel : 0,
                    'activa' => $this->texto($dep->baja) !== '1',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            $idPorCodigo[$codigo] = DB::table('dependencias')->where('codigo', $codigo)->value('id');
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

    private function enteroTexto(mixed $valor): string
    {
        $texto = $this->texto($valor) ?? '0';

        return is_numeric($texto) ? (string) (int) $texto : $texto;
    }
}
