<?php

namespace App\Informes;

use App\Models\Publicacion;
use App\Reportes\PendientesDependencia\FilaPendientes;
use App\Reportes\PendientesDependencia\MotorPendientesDependencia;
use Illuminate\Support\Collection;

/**
 * Reúne los agregados VERIFICADOS del motor de pendientes que un informe cita:
 * resumen general, resumen por nivel (gerencias/oficinas vs subunidades),
 * ranking de dependencias y totales.
 *
 * Es la única fuente de cifras del informe: el redactor y el documento Word
 * solo las formatean, nunca las calculan ni las inventan. Cada cifra es
 * trazable a una publicación y a las reglas del reporte.
 */
class EvidenciaInforme
{
    private function __construct(
        /** @var array<string, mixed> */
        public readonly array $datos,
    ) {}

    /** Construye la evidencia institucional de la publicación. */
    public static function institucional(Publicacion $publicacion): self
    {
        $resumen = (new MotorPendientesDependencia($publicacion))->resumen();

        $filas = collect($resumen->filas);
        $total = $resumen->totales();

        return new self([
            'alcance' => 'institucional',
            'nombre_alcance' => 'la institución',
            // Totales institucionales (propios sumados).
            'resumen' => self::fila('TOTAL GENERAL', $total),
            // Resumen por nivel: Gerencias y Oficinas Generales vs Subunidades.
            'por_nivel' => self::porNivel($filas),
            // Ranking de dependencias con saldo, mayor a menor.
            'ranking' => self::ranking($filas),
            'dependencias_total' => $filas->count(),
            'dependencias_con_saldo' => $filas->filter(fn (FilaPendientes $f) => $f->total() > 0)->count(),
            ...self::contexto($publicacion),
        ]);
    }

    /**
     * Resumen en dos niveles, como el informe de referencia: las gerencias y
     * oficinas generales (nivel 1 y 2) frente a las subunidades (nivel ≥ 3).
     *
     * @param  Collection<string, FilaPendientes>  $filas
     * @return array<int, array<string, mixed>>
     */
    private static function porNivel($filas): array
    {
        $gerencias = $filas->filter(fn (FilaPendientes $f) => $f->nivel <= 2);
        $subunidades = $filas->filter(fn (FilaPendientes $f) => $f->nivel > 2);

        return [
            self::filaAgregada('Gerencias y Oficinas Generales', $gerencias),
            self::filaAgregada('Subunidades', $subunidades),
        ];
    }

    /**
     * Ranking de dependencias con pendientes, ordenado de mayor a menor total.
     *
     * @param  Collection<string, FilaPendientes>  $filas
     * @return array<int, array<string, mixed>>
     */
    private static function ranking($filas): array
    {
        return $filas
            ->filter(fn (FilaPendientes $f) => $f->total() > 0)
            ->sortByDesc(fn (FilaPendientes $f) => $f->total())
            ->values()
            ->map(fn (FilaPendientes $f) => self::fila($f->nombre, $f, $f->codigo))
            ->all();
    }

    /**
     * Una fila de cuadro: total, no leídos, sin atender, urgentes y % no leídos.
     *
     * @return array<string, mixed>
     */
    private static function fila(string $nombre, FilaPendientes $f, ?string $codigo = null): array
    {
        $total = $f->total();
        $noLeidos = $f->entradaNoLeidos + $f->delegadosNoLeidos;
        $sinAtender = $f->entradaRecibidos + $f->delegadosRecibidos;
        $urgentes = $f->prioridadUrgente + $f->prioridadMuyUrgente;

        return [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'total' => $total,
            // Totales por bandeja (entrada = destinatario es el encargado; delegados = otro).
            'entrada' => $f->entradaTotal(),
            'delegados' => $f->delegadosTotal(),
            // Desglose de cada bandeja por estado de recepción.
            'entrada_no_leidos' => $f->entradaNoLeidos,
            'entrada_sin_atender' => $f->entradaRecibidos,
            'delegados_no_leidos' => $f->delegadosNoLeidos,
            'delegados_sin_atender' => $f->delegadosRecibidos,
            // Totales globales (suma de ambas bandejas), para el resumen por nivel.
            'no_leidos' => $noLeidos,
            'sin_atender' => $sinAtender,
            'urgentes' => $urgentes,
            'pct_no_leidos' => $total > 0 ? round($noLeidos / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Fila agregada sobre un conjunto de dependencias (suma de sus cifras).
     *
     * @param  Collection<string, FilaPendientes>  $filas
     * @return array<string, mixed>
     */
    private static function filaAgregada(string $nombre, $filas): array
    {
        $total = $filas->sum(fn (FilaPendientes $f) => $f->total());
        $entrada = $filas->sum(fn (FilaPendientes $f) => $f->entradaTotal());
        $delegados = $filas->sum(fn (FilaPendientes $f) => $f->delegadosTotal());
        $noLeidos = $filas->sum(fn (FilaPendientes $f) => $f->entradaNoLeidos + $f->delegadosNoLeidos);
        $sinAtender = $filas->sum(fn (FilaPendientes $f) => $f->entradaRecibidos + $f->delegadosRecibidos);
        $urgentes = $filas->sum(fn (FilaPendientes $f) => $f->prioridadUrgente + $f->prioridadMuyUrgente);

        return [
            'codigo' => null,
            'nombre' => $nombre,
            'total' => $total,
            'entrada' => $entrada,
            'delegados' => $delegados,
            'no_leidos' => $noLeidos,
            'sin_atender' => $sinAtender,
            'urgentes' => $urgentes,
            'pct_no_leidos' => $total > 0 ? round($noLeidos / $total * 100, 1) : 0.0,
        ];
    }

    /** @return array<string, string|int> */
    private static function contexto(Publicacion $publicacion): array
    {
        return [
            'publicacion_id' => $publicacion->id,
            'perfil' => $publicacion->perfil,
            'fecha_publicacion' => (string) $publicacion->fecha_publicacion,
        ];
    }

    public function valor(string $clave): mixed
    {
        return $this->datos[$clave] ?? null;
    }
}
