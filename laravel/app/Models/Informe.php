<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Informe inteligente (beta). Guarda la narrativa redactada y los agregados
 * verificados del motor que la sustentan. Nace como borrador y se cierra con
 * la aprobación humana, que registra autor y fecha.
 */
#[Fillable([
    'publicacion_id', 'generado_por', 'reporte', 'dependencia', 'estado',
    'contenido', 'evidencia', 'redactor', 'idempotencia_clave',
    'numero', 'destinatario', 'destinatario_cargo', 'remitente', 'remitente_cargo', 'asunto',
    'aprobado_por', 'aprobado_en',
])]
class Informe extends Model
{
    protected $table = 'informes';

    protected function casts(): array
    {
        return [
            'evidencia' => 'array',
            'aprobado_en' => 'datetime',
        ];
    }

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class, 'publicacion_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function esAprobado(): bool
    {
        return $this->estado === 'aprobado';
    }

    public function esBorrador(): bool
    {
        return $this->estado === 'borrador';
    }

    /**
     * Marca el informe como aprobado por un usuario. Idempotente: un informe
     * ya aprobado conserva su aprobador y fecha originales.
     */
    public function aprobar(User $aprobador): void
    {
        if ($this->esAprobado()) {
            return;
        }

        $this->forceFill([
            'estado' => 'aprobado',
            'aprobado_por' => $aprobador->id,
            'aprobado_en' => Carbon::now(),
        ])->save();
    }

    public function alcance(): string
    {
        return $this->dependencia === null ? 'Institucional' : "Dependencia {$this->dependencia}";
    }
}
