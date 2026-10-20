<?php

namespace App\Explorador;

use App\Enums\Prioridad;

/**
 * Lista cerrada de campos que el explorador permite seleccionar, filtrar y
 * agrupar sobre los destinos pendientes de la publicación vigente. Es el
 * "contrato de datos" del modelo §8: el usuario elige de esta lista, nunca
 * escribe SQL, y validar una consulta guardada es comprobar que sus claves
 * pertenecen al catálogo.
 *
 * El universo es siempre el de pendientes (perfil institucional): tipo_destino
 * '01', activo, recepción en {0,1}. La medida es el conteo de destinos
 * (asignaciones), coherente con la unidad del reporte; nunca se suman
 * documentos únicos entre oficinas.
 *
 * Beta acotada al primer reporte: solo dimensiones que viven en
 * destinos_captura, sin joins. La antigüedad por tramos vive en el detalle del
 * reporte y se incorporará al explorador en una versión posterior.
 */
class CatalogoExplorador
{
    /** @var array<string, CampoExplorador>|null */
    private static ?array $campos = null;

    /** @return array<string, CampoExplorador> */
    public static function campos(): array
    {
        if (self::$campos !== null) {
            return self::$campos;
        }

        // El operador + conserva las claves de código ('1','2','3'); array_merge
        // las reindexaría al ser numéricas y desalinearía las etiquetas.
        $prioridades = ['' => 'Sin prioridad'] + collect(Prioridad::cases())->mapWithKeys(
            fn (Prioridad $p) => [$p->value => $p->nombre()],
        )->all();

        return self::$campos = collect([
            new CampoExplorador(
                clave: 'dependencia',
                etiqueta: 'Dependencia',
                expresion: 'destinos_captura.dependencia_codigo',
                // No es lista cerrada (hay 155); se filtra por código exacto aparte.
                filtrable: false,
            ),
            new CampoExplorador(
                clave: 'bandeja',
                etiqueta: 'Bandeja',
                // Entrada = destinatario coincide con el encargado; Delegados si difiere.
                expresion: "CASE WHEN destinos_captura.destinatario_empleado is not distinct from destinos_captura.encargado_empleado THEN 'entrada' ELSE 'delegados' END",
                opciones: ['entrada' => 'Entrada', 'delegados' => 'Delegados'],
            ),
            new CampoExplorador(
                clave: 'estado',
                etiqueta: 'Estado de recepción',
                expresion: 'destinos_captura.estado_recepcion',
                opciones: ['0' => 'No leído', '1' => 'Recibido sin atender'],
            ),
            new CampoExplorador(
                clave: 'prioridad',
                etiqueta: 'Prioridad',
                expresion: "coalesce(destinos_captura.prioridad, '')",
                opciones: $prioridades,
            ),
            new CampoExplorador(
                clave: 'anio',
                etiqueta: 'Año de emisión',
                expresion: 'destinos_captura.anio',
                // Muchos valores posibles; agrupable pero no filtrable por lista.
                filtrable: false,
            ),
        ])->keyBy('clave')->all();
    }

    public static function campo(string $clave): ?CampoExplorador
    {
        return self::campos()[$clave] ?? null;
    }

    /**
     * Claves válidas para agrupar.
     *
     * @return array<int, string>
     */
    public static function clavesAgrupables(): array
    {
        return array_keys(array_filter(self::campos(), fn (CampoExplorador $c) => $c->agrupable));
    }

    /**
     * Claves válidas para filtrar por lista cerrada de opciones.
     *
     * @return array<int, string>
     */
    public static function clavesFiltrables(): array
    {
        return array_keys(array_filter(self::campos(), fn (CampoExplorador $c) => $c->filtrable));
    }
}
