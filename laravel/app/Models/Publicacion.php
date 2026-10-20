<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['fecha_publicacion', 'perfil', 'version_reglas', 'estado', 'marca_agua', 'tipo_ingesta', 'version_datos_detalle', 'nota'])]
class Publicacion extends Model
{
    /** Versión actual del enriquecimiento de detalle (§4–§5 de la mejora). */
    public const VERSION_DATOS_DETALLE = 1;

    protected $table = 'publicaciones';

    protected function casts(): array
    {
        return [
            'fecha_publicacion' => 'datetime',
            'marca_agua' => 'datetime',
            'version_datos_detalle' => 'integer',
        ];
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoCaptura::class, 'publicacion_id');
    }

    public function destinos(): HasMany
    {
        return $this->hasMany(DestinoCaptura::class, 'publicacion_id');
    }

    public function tiposDocumentales(): HasMany
    {
        return $this->hasMany(TipoDocumentalCaptura::class, 'publicacion_id');
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(EmpleadoCaptura::class, 'publicacion_id');
    }

    public function pertenencias(): HasMany
    {
        return $this->hasMany(EmpleadoDependenciaCaptura::class, 'publicacion_id');
    }

    public function dependenciasCaptura(): HasMany
    {
        return $this->hasMany(DependenciaCaptura::class, 'publicacion_id');
    }

    /**
     * ¿La publicación tiene los datos de detalle enriquecidos (§4–§5)? Las
     * publicaciones anteriores a la mejora tienen version_datos_detalle nula.
     */
    public function tieneDatosDetalle(): bool
    {
        return $this->version_datos_detalle !== null;
    }

    /** Última publicación vigente; alimenta resumen, detalle y exportación. */
    public static function vigente(): ?self
    {
        return self::where('estado', 'vigente')
            ->orderByDesc('fecha_publicacion')
            ->first();
    }
}
