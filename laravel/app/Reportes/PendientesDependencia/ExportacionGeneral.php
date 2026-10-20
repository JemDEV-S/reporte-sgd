<?php

namespace App\Reportes\PendientesDependencia;

use Illuminate\Support\Carbon;

/**
 * Define QUÉ contiene el reporte general plano: una fila por dependencia, sin
 * jerarquía, con las cifras PROPIAS de cada oficina (Entrada y Delegados por
 * estado, total y prioridades). Se usa tanto para la tabla en pantalla como
 * para las exportaciones CSV y .xlsx, de modo que las tres coinciden.
 *
 * Las filas derivan de ResumenPendientes (las mismas FilaPendientes del
 * reporte): el general y el resumen jerárquico cuentan el mismo universo.
 */
class ExportacionGeneral
{
    public function __construct(
        private readonly ResumenPendientes $resumen,
    ) {}

    /**
     * Columnas del reporte general, en orden.
     *
     * @return array<int, string>
     */
    public function columnas(): array
    {
        return [
            'Código',
            'Dependencia',
            'Nivel',
            'Entrada · No leídos',
            'Entrada · Recibidos sin atender',
            'Delegados · No leídos',
            'Delegados · Recibidos sin atender',
            'Total pendientes',
            'Prioridad normal',
            'Prioridad urgente',
            'Prioridad muy urgente',
        ];
    }

    /**
     * Una fila por dependencia (todas, incluso con saldo cero), ordenadas por
     * código. Cifras propias; sin roll-up jerárquico.
     *
     * @return array<int, array<int, string|int>>
     */
    public function filas(): array
    {
        $filas = [];
        foreach ($this->dependenciasOrdenadas() as $fila) {
            $filas[] = [
                $fila->codigo,
                $fila->nombre,
                $fila->nivel,
                $fila->entradaNoLeidos,
                $fila->entradaRecibidos,
                $fila->delegadosNoLeidos,
                $fila->delegadosRecibidos,
                $fila->total(),
                $fila->prioridadNormal,
                $fila->prioridadUrgente,
                $fila->prioridadMuyUrgente,
            ];
        }

        return $filas;
    }

    /**
     * Fila de totales institucionales: la suma de las cifras propias de todas
     * las dependencias (coincide con el total del reporte).
     *
     * @return array<int, string|int>
     */
    public function filaTotales(): array
    {
        $t = $this->resumen->totales();

        return [
            '',
            'TOTAL INSTITUCIONAL',
            '',
            $t->entradaNoLeidos,
            $t->entradaRecibidos,
            $t->delegadosNoLeidos,
            $t->delegadosRecibidos,
            $t->total(),
            $t->prioridadNormal,
            $t->prioridadUrgente,
            $t->prioridadMuyUrgente,
        ];
    }

    /**
     * Encabezado de contexto para que las cifras sean auditables.
     *
     * @return array<int, array<int, string>>
     */
    public function metadatos(): array
    {
        $publicacion = $this->resumen->publicacion;

        $lineas = [
            ['Reporte', 'Pendientes por dependencia — general (todas las dependencias)'],
            ['Alcance', 'Cifras propias de cada dependencia, sin jerarquía'],
            ['Unidad', 'Asignaciones (destinos internos, tipo 01)'],
            ['Dependencias', (string) count($this->dependenciasOrdenadas())],
            ['Total de destinos', (string) $this->resumen->totales()->total()],
            ['Perfil', ucfirst($publicacion->perfil)],
            ['Publicación', Carbon::parse($publicacion->fecha_publicacion)->translatedFormat('d \d\e F \d\e Y, H:i')],
            ['Generado', Carbon::now()->translatedFormat('d \d\e F \d\e Y, H:i')],
        ];

        $descripcion = $this->resumen->filtros->descripcion();
        $lineas[] = ['Filtros', $descripcion === [] ? 'Sin filtros' : implode(' · ', array_map(
            fn ($campo, $valor) => "{$campo}: {$valor}",
            array_keys($descripcion),
            array_values($descripcion),
        ))];

        return $lineas;
    }

    public function nombreBase(): string
    {
        return 'pendientes_general_'.Carbon::parse($this->resumen->publicacion->fecha_publicacion)->format('Ymd');
    }

    /**
     * Todas las dependencias ordenadas por código, como lista plana.
     *
     * @return array<int, FilaPendientes>
     */
    public function dependenciasOrdenadas(): array
    {
        $filas = array_values($this->resumen->filas);
        usort($filas, fn (FilaPendientes $a, FilaPendientes $b) => strcmp($a->codigo, $b->codigo));

        return $filas;
    }
}
