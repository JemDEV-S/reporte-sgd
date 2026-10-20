<?php

namespace Tests\Feature;

use App\Ingesta\IngestaIncrementalSgd;
use App\Models\Publicacion;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

/**
 * Verifica la mecánica de la ingesta incremental sobre la copia, inyectando
 * candidatos sintéticos (sin contactar el SGD): clonado de la base, upsert de
 * los que siguen pendientes, baja de los que dejaron de serlo e idempotencia.
 */
class IngestaIncrementalTest extends DatabaseTestCase
{
    public function test_clona_la_base_y_aplica_altas_cambios_y_bajas(): void
    {
        $base = $this->publicacionBaseCon([
            // (anio, emision, destino, estado_recepcion, prioridad)
            ['2026', '0000000001', '1', '0', '1'],
            ['2026', '0000000002', '1', '0', '2'],
            ['2026', '0000000003', '1', '1', '1'], // este dejará de ser pendiente
        ]);

        $candidatos = [
            // Cambio: 0001 pasa de no leído (0) a recibido (1), sigue pendiente.
            $this->candidato('2026', '0000000001', '1', esPendiente: 1, estado: '1', prioridad: '1'),
            // Alta: 0004 aparece como pendiente nuevo.
            $this->candidato('2026', '0000000004', '1', esPendiente: 1, estado: '0', prioridad: '3'),
            // Baja: 0003 dejó de ser pendiente (atendido).
            $this->candidato('2026', '0000000003', '1', esPendiente: 0),
        ];

        $resultado = $this->motorSinSgd()->aplicarSobreCopia($base, [], $candidatos, '2026-10-15 16:00:00.000');

        $nueva = $resultado->publicacion;
        $this->assertSame('vigente', $nueva->estado);
        $this->assertSame('incremental', $nueva->tipo_ingesta);
        $this->assertSame(2, $resultado->upserts);
        $this->assertSame(1, $resultado->bajas);

        $destinos = DB::table('destinos_captura')->where('publicacion_id', $nueva->id);
        // Base tenía 3; baja 1 (0003), alta 1 (0004) => 3 vigentes.
        $this->assertSame(3, (clone $destinos)->count());
        $this->assertFalse((clone $destinos)->where('emision', '0000000003')->exists());
        $this->assertTrue((clone $destinos)->where('emision', '0000000004')->exists());
        // El cambio de estado se aplicó.
        $this->assertSame('1', (clone $destinos)->where('emision', '0000000001')->value('estado_recepcion'));

        // La base permanece intacta (snapshot inmutable).
        $this->assertSame(3, DB::table('destinos_captura')->where('publicacion_id', $base->id)->count());
        $this->assertSame('historica', $base->fresh()->estado);
    }

    public function test_reaplicar_los_mismos_candidatos_es_idempotente(): void
    {
        $base = $this->publicacionBaseCon([
            ['2026', '0000000001', '1', '0', '1'],
        ]);

        $candidatos = [
            $this->candidato('2026', '0000000001', '1', esPendiente: 1, estado: '1', prioridad: '2'),
            $this->candidato('2026', '0000000002', '1', esPendiente: 1, estado: '0', prioridad: '1'),
        ];

        $primera = $this->motorSinSgd()->aplicarSobreCopia($base, [], $candidatos, '2026-10-15 16:00:00.000');
        $segunda = $this->motorSinSgd()->aplicarSobreCopia($primera->publicacion, [], $candidatos, '2026-10-15 16:05:00.000');

        $this->assertSame(
            DB::table('destinos_captura')->where('publicacion_id', $primera->publicacion->id)->count(),
            DB::table('destinos_captura')->where('publicacion_id', $segunda->publicacion->id)->count(),
        );
        $this->assertSame(2, $segunda->destinos);
    }

    public function test_clona_los_metadatos_de_cabecera_a_la_nueva_publicacion(): void
    {
        $base = $this->publicacionBaseCon([
            ['2026', '0000000001', '1', '0', '1'],
        ]);
        // La base tiene una cabecera enriquecida; el clon debe conservarla.
        DB::table('documentos_captura')->insert([
            'publicacion_id' => $base->id,
            'anio' => '2026',
            'emision' => '0000000001',
            'tipo_documental' => 'OFI',
            'tipo_emisor' => '01',
            'numero_documento' => '000006-2026-OGA',
            'numero_emisor' => '000006',
            'sigla_documento' => 'OGA',
            'numero_incompleto' => false,
            'asunto' => 'Asunto de prueba',
            'dependencia_emisora_codigo' => '00001',
            'origen_descripcion' => 'ALCALDÍA (prueba)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Candidato que mantiene pendiente ese destino y trae una cabecera nueva.
        $candidato = $this->candidato('2026', '0000000002', '1', esPendiente: 1);
        $candidato->TI_EMI = '05';
        $candidato->NU_DOC_EMI = '000081';
        $candidato->DE_DOC_SIG = 'OA-RFAG';
        $candidato->DE_ASU = 'Nuevo documento';
        $candidato->CO_DEP_EMI = '00021';
        $candidato->CO_EMP_EMI = '00053';
        $candidato->NU_DOC_RESUMEN = null;
        $candidato->ORIGEN_DESC = 'OFICINA DE ABASTECIMIENTO (prueba)';
        $candidato->FE_EMI = null;
        $candidato->CO_TIP_DOC_ADM = 'MEM';
        $candidato->ES_DOC_EMI = '1';

        $resultado = $this->motorSinSgd()->aplicarSobreCopia(
            $base,
            [],
            [$this->candidato('2026', '0000000001', '1', esPendiente: 1), $candidato],
            '2026-10-15 16:00:00.000',
        );
        $nueva = $resultado->publicacion;

        // La cabecera clonada conserva el número y el asunto.
        $clon = DB::table('documentos_captura')
            ->where('publicacion_id', $nueva->id)->where('emision', '0000000001')->first();
        $this->assertSame('000006-2026-OGA', $clon->numero_documento);
        $this->assertSame('Asunto de prueba', $clon->asunto);

        // La cabecera nueva se insertó con su número reconstruido (tipo 05).
        $nuevo = DB::table('documentos_captura')
            ->where('publicacion_id', $nueva->id)->where('emision', '0000000002')->first();
        $this->assertNotNull($nuevo);
        $this->assertSame('000081-2026-OA-RFAG', $nuevo->numero_documento);
        $this->assertSame('Nuevo documento', $nuevo->asunto);
    }

    private function motorSinSgd(): IngestaIncrementalSgd
    {
        // No recapturar catálogos/cabeceras desde el SGD: las pruebas aíslan la
        // mecánica sobre Postgres con candidatos inyectados.
        return new IngestaIncrementalSgd('IDOSGD', 'sgd', capturarCatalogos: false);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}>  $filas
     */
    private function publicacionBaseCon(array $filas): Publicacion
    {
        $pub = Publicacion::create([
            'fecha_publicacion' => now()->subHour(),
            'perfil' => 'institucional',
            'estado' => 'vigente',
            'marca_agua' => '2026-10-15 15:00:00.000',
            'tipo_ingesta' => 'completa',
        ]);

        foreach ($filas as [$anio, $emision, $destino, $estado, $prioridad]) {
            DB::table('destinos_captura')->insert([
                'publicacion_id' => $pub->id,
                'anio' => $anio,
                'emision' => $emision,
                'destino' => $destino,
                'tipo_destino' => '01',
                'dependencia_codigo' => '00021',
                'destinatario_empleado' => '00053',
                'encargado_empleado' => '00053',
                'estado_recepcion' => $estado,
                'activo' => true,
                'prioridad' => $prioridad,
                'fecha_recepcion' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $pub;
    }

    private function candidato(
        string $anio,
        string $emision,
        string $destino,
        int $esPendiente,
        string $estado = '0',
        string $prioridad = '1',
    ): object {
        return (object) [
            'NU_ANN' => $anio,
            'NU_EMI' => $emision,
            'NU_DES' => $destino,
            'TI_DES' => '01',
            'CO_DEP_DES' => '00021',
            'CO_EMP_DES' => '00053',
            'ES_DOC_REC' => $estado,
            'CO_PRI' => $prioridad,
            'FE_REC_DOC' => null,
            'ENCARGADO' => '00053',
            'ES_PENDIENTE' => $esPendiente,
        ];
    }
}
