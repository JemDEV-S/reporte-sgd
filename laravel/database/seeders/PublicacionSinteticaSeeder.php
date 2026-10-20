<?php

namespace Database\Seeders;

use App\Enums\EstadoRecepcion;
use App\Enums\Prioridad;
use App\Models\Dependencia;
use App\Models\DependenciaCaptura;
use App\Models\DestinoCaptura;
use App\Models\DocumentoCaptura;
use App\Models\Empleado;
use App\Models\Publicacion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Publicación sintética para construir y validar el reporte de pendientes
 * por dependencia sin depender de la ingesta real del SGD.
 *
 * La oficina de Abastecimiento (código 00021) reproduce las cifras
 * históricas documentadas (perfil institucional, 13/10/2026):
 *   Entrada   — 43 no leídos, 16 recibidos sin atender  (59 asignaciones)
 *   Delegados — 304 no leídos, 299 recibidos sin atender (603 asignaciones)
 *
 * Las demás dependencias llevan cifras menores y variadas para que el
 * drill-down y los gráficos tengan contenido representativo.
 */
class PublicacionSinteticaSeeder extends Seeder
{
    /** Contador global para asegurar claves fuente (año, emisión) únicas. */
    private int $secuenciaEmision = 1;

    private string $anio = '2026';

    public function run(): void
    {
        DB::transaction(function () {
            $publicacion = Publicacion::create([
                'fecha_publicacion' => Carbon::parse('2026-10-13 08:00:00', 'America/Lima'),
                'perfil' => 'institucional',
                'version_reglas' => 'R01-R12',
                'estado' => 'vigente',
                'version_datos_detalle' => Publicacion::VERSION_DATOS_DETALLE,
                'nota' => 'Publicación sintética de demostración; no procede de una ingesta real del SGD.',
            ]);

            $arbol = $this->sembrarOrganigrama();

            foreach ($this->plan() as $fila) {
                [$codigo, $entradaNoLeidos, $entradaRecibidos, $delegadosNoLeidos, $delegadosRecibidos] = $fila;
                $this->sembrarDestinos(
                    $publicacion->id,
                    $arbol[$codigo],
                    $entradaNoLeidos,
                    $entradaRecibidos,
                    $delegadosNoLeidos,
                    $delegadosRecibidos,
                );
            }

            // Catálogos capturados: tipos, dependencias, padrón de empleados y
            // pertenencias (incluye integrantes activos de saldo cero).
            $this->sembrarCatalogos($publicacion->id, $arbol);

            // Oficina de incidencias para ejercitar la conciliación del modal (§5.3):
            // padrón activo (con saldo cero y pertenencia por sola vía "asignada"),
            // un destinatario fuera del padrón con pendientes, y pendientes con
            // destinatario nulo. No afecta las cifras de las oficinas anteriores.
            $this->sembrarOficinaConIncidencias($publicacion->id);
        });
    }

    /**
     * Organigrama de ejemplo con tres niveles. Devuelve un mapa
     * codigo => ['dependencia' => Dependencia, 'encargado' => Empleado, 'delegados' => Empleado[]].
     *
     * @return array<string, array{dependencia: Dependencia, encargado: Empleado, delegados: array<int, Empleado>}>
     */
    private function sembrarOrganigrama(): array
    {
        // [código, nombre, código padre|null]
        $definicion = [
            ['00001', 'Alcaldía', null],
            ['00010', 'Gerencia Municipal', '00001'],
            ['00020', 'Gerencia de Administración y Finanzas', '00010'],
            ['00021', 'Subgerencia de Abastecimiento', '00020'],
            ['00022', 'Subgerencia de Contabilidad', '00020'],
            ['00023', 'Subgerencia de Tesorería', '00020'],
            ['00030', 'Gerencia de Desarrollo Urbano', '00010'],
            ['00031', 'Subgerencia de Obras Públicas', '00030'],
            ['00032', 'Subgerencia de Catastro', '00030'],
            ['00040', 'Gerencia de Desarrollo Social', '00010'],
            ['00041', 'Subgerencia de Programas Sociales', '00040'],
            ['00050', 'Secretaría General', '00001'],
        ];

        $dependencias = [];
        $mapa = [];

        foreach ($definicion as [$codigo, $nombre, $codigoPadre]) {
            $padre = $codigoPadre !== null ? $dependencias[$codigoPadre] : null;
            $dependencia = Dependencia::create([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'dependencia_padre_id' => $padre?->id,
                'nivel' => $padre ? $padre->nivel + 1 : 0,
                'activa' => true,
            ]);
            $dependencias[$codigo] = $dependencia;

            $encargado = $this->crearEmpleado($dependencia, 'Encargado');
            $delegados = [];
            for ($i = 1; $i <= 3; $i++) {
                $delegados[] = $this->crearEmpleado($dependencia, "Delegado {$i}");
            }

            $mapa[$codigo] = [
                'dependencia' => $dependencia,
                'encargado' => $encargado,
                'delegados' => $delegados,
            ];
        }

        return $mapa;
    }

    private function crearEmpleado(Dependencia $dependencia, string $rol): Empleado
    {
        static $secuencia = 1;
        $codigo = str_pad((string) $secuencia++, 6, '0', STR_PAD_LEFT);

        return Empleado::create([
            'codigo' => $codigo,
            'nombres' => $rol,
            'apellidos' => "de {$dependencia->nombre}",
            'dependencia_id' => $dependencia->id,
            'activo' => true,
        ]);
    }

    /**
     * Cifras por dependencia.
     * [código, entrada_no_leidos, entrada_recibidos, delegados_no_leidos, delegados_recibidos]
     *
     * @return array<int, array{0: string, 1: int, 2: int, 3: int, 4: int}>
     */
    private function plan(): array
    {
        return [
            ['00021', 43, 16, 304, 299], // Abastecimiento: cifras históricas conocidas.
            ['00022', 12, 8, 21, 14],
            ['00023', 5, 3, 9, 6],
            ['00031', 28, 19, 47, 33],
            ['00032', 7, 4, 11, 8],
            ['00041', 15, 10, 18, 12],
            ['00050', 9, 6, 3, 2],
            ['00020', 4, 2, 6, 5],   // La gerencia también recibe directamente.
            ['00030', 6, 3, 2, 1],
            ['00040', 2, 1, 1, 0],
            ['00010', 3, 1, 0, 0],
            ['00001', 1, 0, 0, 0],
        ];
    }

    /**
     * Genera los destinos de una dependencia. Entrada = destinatario igual al
     * encargado; Delegados = destinatario distinto del encargado. El estado de
     * recepción distingue no leídos ('0') de recibidos sin atender ('1').
     *
     * @param  array{dependencia: Dependencia, encargado: Empleado, delegados: array<int, Empleado>}  $nodo
     */
    private function sembrarDestinos(
        int $publicacionId,
        array $nodo,
        int $entradaNoLeidos,
        int $entradaRecibidos,
        int $delegadosNoLeidos,
        int $delegadosRecibidos,
    ): void {
        $codigoDep = $nodo['dependencia']->codigo;
        $encargado = $nodo['encargado']->codigo;
        $delegados = array_map(fn (Empleado $e) => $e->codigo, $nodo['delegados']);

        // Cada lote: [destinatario(s), estado de recepción, cantidad]. Entrada va al
        // encargado; Delegados se reparten en round-robin entre los demás empleados.
        $lotes = [
            [$encargado, EstadoRecepcion::NoLeido, $entradaNoLeidos],
            [$encargado, EstadoRecepcion::RecibidoSinAtender, $entradaRecibidos],
            [$delegados, EstadoRecepcion::NoLeido, $delegadosNoLeidos],
            [$delegados, EstadoRecepcion::RecibidoSinAtender, $delegadosRecibidos],
        ];

        $filas = [];
        $ahora = now();

        foreach ($lotes as [$destinatarios, $estado, $cantidad]) {
            $destinatarios = is_array($destinatarios) ? $destinatarios : [$destinatarios];
            for ($i = 0; $i < $cantidad; $i++) {
                $destinatario = $destinatarios[$i % count($destinatarios)];
                $emision = str_pad((string) $this->secuenciaEmision++, 8, '0', STR_PAD_LEFT);
                $prioridad = $this->prioridadSintetica($i);
                $diasAtras = ($i * 3) % 45; // Distribuye antigüedad entre 0 y ~44 días.
                $fechaRecepcion = $estado === EstadoRecepcion::RecibidoSinAtender
                    ? Carbon::parse('2026-10-13', 'America/Lima')->subDays($diasAtras)
                    : null;

                $filas[] = [
                    'publicacion_id' => $publicacionId,
                    'anio' => $this->anio,
                    'emision' => $emision,
                    'destino' => '01',
                    'tipo_destino' => '01',
                    'dependencia_codigo' => $codigoDep,
                    'destinatario_empleado' => $destinatario,
                    'encargado_empleado' => $encargado,
                    'estado_recepcion' => $estado->value,
                    'activo' => true,
                    'prioridad' => $prioridad,
                    'fecha_recepcion' => $fechaRecepcion,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        // Un documento de cabecera por destino, con la misma clave fuente y los
        // metadatos legibles del detalle enriquecido (tipo, número con ceros,
        // asunto y origen). Sin datos personales reales.
        $siglaDep = $this->sigla($codigoDep);
        $documentos = array_map(function (array $f) use ($ahora, $siglaDep, $codigoDep) {
            $numeroEmisor = str_pad((string) ((int) $f['emision'] % 1000000), 6, '0', STR_PAD_LEFT);

            return [
                'publicacion_id' => $f['publicacion_id'],
                'anio' => $f['anio'],
                'emision' => $f['emision'],
                'tipo_documental' => 'OFI',
                'estado_emision' => '1',
                'eliminado' => false,
                'fecha_emision' => $f['fecha_recepcion'] ?? Carbon::parse('2026-10-13', 'America/Lima'),
                'tipo_emisor' => '01',
                'numero_documento' => "{$numeroEmisor}-{$f['anio']}-{$siglaDep}",
                'numero_emisor' => $numeroEmisor,
                'sigla_documento' => $siglaDep,
                'numero_incompleto' => false,
                'asunto' => "Asunto de prueba del documento {$numeroEmisor} de la dependencia {$codigoDep}.",
                'dependencia_emisora_codigo' => $codigoDep,
                'emisor_empleado_codigo' => $f['encargado_empleado'],
                'origen_descripcion' => "Dependencia {$codigoDep} (sintética)",
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }, $filas);

        foreach (array_chunk($documentos, 500) as $trozo) {
            DocumentoCaptura::insert($trozo);
        }
        foreach (array_chunk($filas, 500) as $trozo) {
            DestinoCaptura::insert($trozo);
        }
    }

    /**
     * Captura de catálogos de la publicación sintética: tipos documentales,
     * dependencias, padrón de empleados y pertenencias. El padrón incluye un
     * integrante activo adicional de saldo cero por dependencia, para probar que
     * el padrón no se limita a destinatarios con pendientes.
     *
     * @param  array<string, array{dependencia: Dependencia, encargado: Empleado, delegados: array<int, Empleado>}>  $arbol
     */
    private function sembrarCatalogos(int $publicacionId, array $arbol): void
    {
        $ahora = now();

        // Tipos documentales.
        DB::table('tipos_documentales_captura')->insert([
            ['publicacion_id' => $publicacionId, 'codigo' => 'OFI', 'descripcion' => 'Oficio', 'baja' => '0', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['publicacion_id' => $publicacionId, 'codigo' => '01', 'descripcion' => 'Documento interno', 'baja' => '0', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['publicacion_id' => $publicacionId, 'codigo' => '05', 'descripcion' => 'Memorando', 'baja' => '0', 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        $dependencias = [];
        $empleados = [];
        $pertenencias = [];

        foreach ($arbol as $codigo => $nodo) {
            /** @var Dependencia $dep */
            $dep = $nodo['dependencia'];
            $dependencias[] = [
                'publicacion_id' => $publicacionId,
                'codigo' => $codigo,
                'nombre' => $dep->nombre,
                'encargado_codigo' => $nodo['encargado']->codigo,
                'baja' => '0',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];

            // Integrantes: encargado + delegados (todos activos), más uno activo
            // adicional SIN pendientes (saldo cero), para el padrón.
            $integrantes = array_merge([$nodo['encargado']], $nodo['delegados']);
            $sinPendientes = $this->crearEmpleado($dep, 'Integrante sin pendientes');
            $integrantes[] = $sinPendientes;

            foreach ($integrantes as $emp) {
                /** @var Empleado $emp */
                $empleados[] = [
                    'publicacion_id' => $publicacionId,
                    'codigo' => $emp->codigo,
                    'apellido_paterno' => 'Apellido',
                    'apellido_materno' => (string) $emp->codigo,
                    'denominacion' => trim("{$emp->nombres} {$emp->apellidos}"),
                    'estado_empleado_codigo' => '1',
                    'baja_codigo' => '1',
                    'dependencia_principal_codigo' => $codigo,
                    'dependencia_configurada_codigo' => $codigo,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
                $pertenencias[] = [
                    'publicacion_id' => $publicacionId,
                    'empleado_codigo' => $emp->codigo,
                    'dependencia_codigo' => $codigo,
                    'es_principal' => true,
                    'es_configurada' => true,
                    'es_asignada' => false,
                    'es_activa' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        DB::table('dependencias_captura')->insert($dependencias);
        foreach (array_chunk($empleados, 500) as $trozo) {
            DB::table('empleados_captura')->insert($trozo);
        }
        foreach (array_chunk($pertenencias, 500) as $trozo) {
            DB::table('empleado_dependencia_captura')->insert($trozo);
        }
    }

    /**
     * Oficina con incidencias (código 00099) para el modal de integrantes (§5):
     *
     *  - Padrón activo: encargado (con pendientes) e integrante de saldo cero que
     *    pertenece SOLO por la vía "asignada" (TDTX_DEPENDENCIA_EMPLEADO).
     *  - Destinatario fuera del padrón con pendientes: empleado inactivo
     *    (estado '0'), que debe aparecer en "otras asignaciones".
     *  - Pendientes con destinatario nulo: grupo "sin destinatario identificado".
     *
     * Así la conciliación del padrón (integrantes + otras = total) tiene los tres
     * casos representados.
     */
    private function sembrarOficinaConIncidencias(int $publicacionId): void
    {
        $ahora = now();
        $codigo = '00099';

        $dependencia = Dependencia::create([
            'codigo' => $codigo,
            'nombre' => 'Oficina con incidencias',
            'dependencia_padre_id' => null,
            'nivel' => 0,
            'activa' => true,
        ]);

        $encargado = $this->crearEmpleado($dependencia, 'Encargado');
        $sinPendientes = $this->crearEmpleado($dependencia, 'Integrante asignado sin pendientes');
        $inactivo = $this->crearEmpleado($dependencia, 'Ex integrante inactivo');

        DependenciaCaptura::create([
            'publicacion_id' => $publicacionId,
            'codigo' => $codigo,
            'nombre' => $dependencia->nombre,
            'encargado_codigo' => $encargado->codigo,
            'baja' => '0',
        ]);

        // Padrón capturado: encargado e integrante de saldo cero, ambos activos;
        // el inactivo se captura como empleado (estado '0') pero su pertenencia
        // NO está activa, de modo que queda fuera del padrón.
        $empleados = [
            [$encargado, '1', ['es_principal' => true, 'es_configurada' => true, 'es_asignada' => false], true],
            [$sinPendientes, '1', ['es_principal' => false, 'es_configurada' => false, 'es_asignada' => true], true],
            [$inactivo, '0', ['es_principal' => true, 'es_configurada' => false, 'es_asignada' => false], false],
        ];

        foreach ($empleados as [$emp, $estado, $vias, $perteneciaActiva]) {
            /** @var Empleado $emp */
            DB::table('empleados_captura')->insert([
                'publicacion_id' => $publicacionId,
                'codigo' => $emp->codigo,
                'apellido_paterno' => 'Apellido',
                'apellido_materno' => (string) $emp->codigo,
                'denominacion' => trim("{$emp->nombres} {$emp->apellidos}"),
                'estado_empleado_codigo' => $estado,
                'baja_codigo' => $estado === '1' ? '1' : '0',
                'dependencia_principal_codigo' => $codigo,
                'dependencia_configurada_codigo' => $codigo,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
            DB::table('empleado_dependencia_captura')->insert([
                'publicacion_id' => $publicacionId,
                'empleado_codigo' => $emp->codigo,
                'dependencia_codigo' => $codigo,
                'es_principal' => $vias['es_principal'],
                'es_configurada' => $vias['es_configurada'],
                'es_asignada' => $vias['es_asignada'],
                'es_activa' => $perteneciaActiva,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        // Destinos: encargado (padrón, 4), inactivo (fuera del padrón, 3) y nulo (2).
        // El integrante "sin pendientes" no recibe destinos: saldo cero en el padrón.
        $lotes = [
            [$encargado->codigo, 4],
            [$inactivo->codigo, 3],
            [null, 2],
        ];

        $siglaDep = $this->sigla($codigo);
        $destinos = [];
        $documentos = [];
        foreach ($lotes as [$destinatario, $cantidad]) {
            for ($i = 0; $i < $cantidad; $i++) {
                $emision = str_pad((string) $this->secuenciaEmision++, 8, '0', STR_PAD_LEFT);
                $numeroEmisor = str_pad((string) ((int) $emision % 1000000), 6, '0', STR_PAD_LEFT);
                $estado = $i % 2 === 0 ? EstadoRecepcion::NoLeido : EstadoRecepcion::RecibidoSinAtender;
                $fechaRecepcion = $estado === EstadoRecepcion::RecibidoSinAtender
                    ? Carbon::parse('2026-10-13', 'America/Lima')->subDays($i * 2)
                    : null;

                $destinos[] = [
                    'publicacion_id' => $publicacionId,
                    'anio' => $this->anio,
                    'emision' => $emision,
                    'destino' => '01',
                    'tipo_destino' => '01',
                    'dependencia_codigo' => $codigo,
                    'destinatario_empleado' => $destinatario,
                    'encargado_empleado' => $encargado->codigo,
                    'estado_recepcion' => $estado->value,
                    'activo' => true,
                    'prioridad' => $this->prioridadSintetica($i),
                    'fecha_recepcion' => $fechaRecepcion,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
                $documentos[] = [
                    'publicacion_id' => $publicacionId,
                    'anio' => $this->anio,
                    'emision' => $emision,
                    'tipo_documental' => 'OFI',
                    'estado_emision' => '1',
                    'eliminado' => false,
                    'fecha_emision' => $fechaRecepcion ?? Carbon::parse('2026-10-13', 'America/Lima'),
                    'tipo_emisor' => '01',
                    'numero_documento' => "{$numeroEmisor}-{$this->anio}-{$siglaDep}",
                    'numero_emisor' => $numeroEmisor,
                    'sigla_documento' => $siglaDep,
                    'numero_incompleto' => false,
                    'asunto' => "Asunto de prueba del documento {$numeroEmisor} de la dependencia {$codigo}.",
                    'dependencia_emisora_codigo' => $codigo,
                    'emisor_empleado_codigo' => $encargado->codigo,
                    'origen_descripcion' => "Dependencia {$codigo} (sintética)",
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        DocumentoCaptura::insert($documentos);
        DestinoCaptura::insert($destinos);
    }

    /** Sigla sintética a partir del código de dependencia (p. ej. DEP21). */
    private function sigla(string $codigoDependencia): string
    {
        return 'DEP'.ltrim($codigoDependencia, '0');
    }

    /** Reparte prioridades 1/2/3 de forma determinista (mayoría normales). */
    private function prioridadSintetica(int $indice): string
    {
        return match ($indice % 5) {
            4 => Prioridad::MuyUrgente->value,
            2, 3 => Prioridad::Urgente->value,
            default => Prioridad::Normal->value,
        };
    }
}
