<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de tipos documentales capturado por publicación, para resolver la
 * descripción del tipo (SI_MAE_TIPO_DOC.CDOC_DESDOC) en el mismo corte sin
 * depender de un catálogo mutable. Un tipo faltante no elimina una asignación:
 * el detalle hace LEFT JOIN y muestra "Tipo sin descripción".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_documentales_captura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicacion_id')->constrained('publicaciones')->cascadeOnDelete();
            $table->string('codigo', 3);
            $table->string('descripcion', 120)->nullable();
            // Baja original del SGD (CDOC_INDBAJ), conservada como dato fuente.
            $table->string('baja', 1)->nullable();
            $table->timestampsTz();

            $table->unique(['publicacion_id', 'codigo'], 'tipos_documentales_captura_clave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_documentales_captura');
    }
};
