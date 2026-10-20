<?php

namespace Tests\Feature;

use App\Models\Publicacion;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

/**
 * Contrato de publicación que garantiza la ingesta, verificado sin depender
 * del SGD: una nueva publicación releva a la anterior y, si la transacción
 * falla, la última vigente permanece intacta.
 */
class PublicacionIngestaTest extends DatabaseTestCase
{
    public function test_vigente_devuelve_la_ultima_publicacion_vigente(): void
    {
        Publicacion::create([
            'fecha_publicacion' => now()->subDay(),
            'perfil' => 'institucional',
            'estado' => 'historica',
        ]);
        $actual = Publicacion::create([
            'fecha_publicacion' => now(),
            'perfil' => 'institucional',
            'estado' => 'vigente',
        ]);

        $this->assertSame($actual->id, Publicacion::vigente()?->id);
    }

    public function test_una_publicacion_en_construccion_no_es_vigente(): void
    {
        Publicacion::create([
            'fecha_publicacion' => now(),
            'perfil' => 'institucional',
            'estado' => 'construyendo',
        ]);

        $this->assertNull(Publicacion::vigente());
    }

    public function test_el_relevo_de_vigencia_deja_una_sola_publicacion_vigente(): void
    {
        $anterior = Publicacion::create([
            'fecha_publicacion' => now()->subDay(),
            'perfil' => 'institucional',
            'estado' => 'vigente',
        ]);

        // Simula el cierre de una ingesta: la nueva releva a la anterior.
        $nueva = Publicacion::create([
            'fecha_publicacion' => now(),
            'perfil' => 'institucional',
            'estado' => 'construyendo',
        ]);
        DB::transaction(function () use ($nueva) {
            Publicacion::where('estado', 'vigente')->update(['estado' => 'historica']);
            $nueva->update(['estado' => 'vigente']);
        });

        $this->assertSame('historica', $anterior->fresh()->estado);
        $this->assertSame($nueva->id, Publicacion::vigente()?->id);
        $this->assertSame(1, Publicacion::where('estado', 'vigente')->count());
    }

    public function test_si_la_transaccion_falla_la_publicacion_anterior_sigue_vigente(): void
    {
        $vigente = Publicacion::create([
            'fecha_publicacion' => now()->subDay(),
            'perfil' => 'institucional',
            'estado' => 'vigente',
        ]);

        try {
            DB::transaction(function () {
                Publicacion::create([
                    'fecha_publicacion' => now(),
                    'perfil' => 'institucional',
                    'estado' => 'construyendo',
                ]);
                throw new \RuntimeException('Fallo simulado durante la copia de destinos.');
            });
        } catch (\RuntimeException) {
            // Se ignora: la transacción debe haber revertido la publicación a medio construir.
        }

        $this->assertSame($vigente->id, Publicacion::vigente()?->id);
        $this->assertSame(1, Publicacion::count());
    }
}
