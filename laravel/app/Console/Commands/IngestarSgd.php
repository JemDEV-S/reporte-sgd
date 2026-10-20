<?php

namespace App\Console\Commands;

use App\Ingesta\IngestaIncrementalSgd;
use App\Ingesta\IngestaSgd;
use Illuminate\Console\Command;
use Throwable;

class IngestarSgd extends Command
{
    protected $signature = 'sgd:ingestar
        {--dependencia= : Limitar la ingesta a un código de dependencia (piloto)}
        {--incremental : Aplicar solo los cambios desde la última marca de agua}
        {--sin-confirmar : Omitir la confirmación antes de una ingesta completa}';

    protected $description = 'Extrae los destinos pendientes del SGD y los publica en PostgreSQL como una nueva publicación.';

    public function handle(): int
    {
        if ($this->option('incremental')) {
            return $this->ingestaIncremental();
        }

        $dependencia = $this->option('dependencia');
        $dependencia = is_string($dependencia) && trim($dependencia) !== '' ? trim($dependencia) : null;

        if ($dependencia !== null && ! preg_match('/^[0-9A-Za-z]{1,5}$/', $dependencia)) {
            $this->error('El código de dependencia no es válido.');

            return self::FAILURE;
        }

        if ($dependencia === null && ! $this->option('sin-confirmar')
            && ! $this->confirm('Vas a ejecutar una ingesta COMPLETA del SGD (puede ser un volumen grande). ¿Continuar?')) {
            $this->line('Ingesta cancelada.');

            return self::SUCCESS;
        }

        $this->info($dependencia !== null
            ? "Ingiriendo la dependencia {$dependencia} del SGD…"
            : 'Ingiriendo el SGD completo (perfil institucional)…');

        try {
            $resultado = (new IngestaSgd)->ejecutar(
                $dependencia,
                fn (int $n) => $this->line("  … {$n} destinos leídos"),
            );
        } catch (Throwable $error) {
            $this->error('La ingesta falló; la última publicación vigente permanece intacta.');
            $this->line($error->getMessage());

            return self::FAILURE;
        }

        $pub = $resultado->publicacion;
        $this->newLine();
        $this->info('Publicación creada y marcada como vigente.');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Publicación', (string) $pub->id],
                ['Fecha', (string) $pub->fecha_publicacion],
                ['Dependencias', (string) $resultado->dependencias],
                ['Documentos', (string) $resultado->documentos],
                ['Destinos (asignaciones)', (string) $resultado->destinos],
            ],
        );

        return self::SUCCESS;
    }

    private function ingestaIncremental(): int
    {
        $this->info('Ingesta incremental: aplicando cambios desde la última marca de agua…');

        try {
            $resultado = (new IngestaIncrementalSgd)->ejecutar(
                fn (int $n) => $this->line("  … {$n} candidatos procesados"),
            );
        } catch (Throwable $error) {
            $this->error('La ingesta incremental falló; la última publicación vigente permanece intacta.');
            $this->line($error->getMessage());

            return self::FAILURE;
        }

        $pub = $resultado->publicacion;
        $this->newLine();
        $this->info("Publicación incremental creada sobre #{$resultado->basePublicacion} y marcada como vigente.");
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Publicación', (string) $pub->id],
                ['Fecha', (string) $pub->fecha_publicacion],
                ['Marca de agua', (string) $pub->marca_agua],
                ['Candidatos evaluados', (string) $resultado->candidatos],
                ['Altas/cambios', (string) $resultado->upserts],
                ['Bajas', (string) $resultado->bajas],
                ['Destinos vigentes', (string) $resultado->destinos],
            ],
        );

        return self::SUCCESS;
    }
}
