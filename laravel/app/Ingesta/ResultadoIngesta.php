<?php

namespace App\Ingesta;

use App\Models\Publicacion;

/** Resumen de una ejecución de ingesta. */
class ResultadoIngesta
{
    public function __construct(
        public readonly Publicacion $publicacion,
        public readonly int $dependencias,
        public readonly int $documentos,
        public readonly int $destinos,
    ) {}
}
