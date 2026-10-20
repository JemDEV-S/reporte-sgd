<?php

namespace Tests\Unit;

use App\Ingesta\MetadatosDocumento;
use PHPUnit\Framework\TestCase;

class MetadatosDocumentoTest extends TestCase
{
    public function test_los_tipos_01_y_05_componen_numero_emisor_anio_sigla(): void
    {
        $this->assertSame(
            '000006-2026-OGA',
            MetadatosDocumento::numero('01', '000006', '2026', 'OGA'),
        );
        $this->assertSame(
            '000005-2026-OA-RFAG',
            MetadatosDocumento::numero('05', '000005', '2026', 'OA-RFAG'),
        );
    }

    public function test_otros_tipos_usan_la_sigla_como_numero(): void
    {
        $this->assertSame('6617', MetadatosDocumento::numero('03', null, '2026', '6617'));
        $this->assertNull(MetadatosDocumento::numero('03', null, '2026', null));
    }

    public function test_el_resumen_es_autoritativo_cuando_esta_disponible(): void
    {
        // El NU_DOC del resumen prevalece sobre la reconstrucción.
        $this->assertSame(
            '000001-2025-GM',
            MetadatosDocumento::numero('01', '000001', '2025', 'GM', '000001-2025-GM'),
        );
    }

    public function test_un_componente_faltante_no_produce_guiones_vacios(): void
    {
        // Falta el número emisor: se muestran los disponibles, sin guion inicial.
        $this->assertSame('2026-ALC', MetadatosDocumento::numero('01', null, '2026', 'ALC'));
    }

    public function test_marca_incompleto_solo_en_tipos_compuestos_con_componentes_faltantes(): void
    {
        $this->assertTrue(MetadatosDocumento::incompleto('01', null, '2026', 'ALC'));
        $this->assertFalse(MetadatosDocumento::incompleto('01', '000001', '2026', 'GM'));
        // Un tipo no compuesto sin sigla no es "incompleto": es "sin número".
        $this->assertFalse(MetadatosDocumento::incompleto('03', null, '2026', null));
    }
}
