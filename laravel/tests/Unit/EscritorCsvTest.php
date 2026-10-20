<?php

namespace Tests\Unit;

use App\Reportes\PendientesDependencia\EscritorCsv;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class EscritorCsvTest extends TestCase
{
    /**
     * Las celdas de texto que empiezan por un disparador de fórmula se
     * neutralizan con un apóstrofo, sin alterar el resto del valor.
     */
    public function test_neutraliza_celdas_que_parecen_formula(): void
    {
        $neutralizar = new ReflectionMethod(EscritorCsv::class, 'neutralizar');

        foreach (['=SUMA(A1:A2)', '+1', '-1', '@importar', "\tcol", "\rret"] as $peligroso) {
            $this->assertSame("'".$peligroso, $neutralizar->invoke(null, $peligroso));
        }
    }

    public function test_respeta_valores_normales_y_enteros(): void
    {
        $neutralizar = new ReflectionMethod(EscritorCsv::class, 'neutralizar');

        $this->assertSame('000135-2026-OTI', $neutralizar->invoke(null, '000135-2026-OTI'));
        $this->assertSame('Oficio de prueba', $neutralizar->invoke(null, 'Oficio de prueba'));
        $this->assertSame(42, $neutralizar->invoke(null, 42));
        $this->assertSame('', $neutralizar->invoke(null, null));
    }
}
