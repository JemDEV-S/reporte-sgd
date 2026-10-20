<?php

namespace App\Ingesta;

use App\Models\Publicacion;

/** Resumen de una ingesta incremental. */
class ResultadoIncremental
{
    public function __construct(
        public readonly Publicacion $publicacion,
        public readonly int $basePublicacion,
        public readonly int $candidatos,
        public readonly int $upserts,
        public readonly int $bajas,
        public readonly int $destinos,
    ) {}
}
