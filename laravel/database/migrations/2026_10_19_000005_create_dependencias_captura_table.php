<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot del catálogo de dependencias por publicación (RHTM_DEPENDENCIA),
 * para resolver nombres de origen/destino en el mismo corte. Complementa la
 * tabla mutable `dependencias` (identidad local, jerarquía), que no debe usarse
 * para reescribir nombres o encargados de cortes anteriores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependencias_captura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicacion_id')->constrained('publicaciones')->cascadeOnDelete();
            $table->string('codigo', 10);
            $table->string('nombre', 200)->nullable();
            // Encargado capturado de la dependencia (RHTM_DEPENDENCIA.CO_EMPLEADO).
            $table->string('encargado_codigo', 10)->nullable();
            $table->string('baja', 1)->nullable();
            $table->timestampsTz();

            $table->unique(['publicacion_id', 'codigo'], 'dependencias_captura_clave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependencias_captura');
    }
};
