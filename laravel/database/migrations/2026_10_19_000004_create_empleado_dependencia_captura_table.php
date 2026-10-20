<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pertenencias empleado↔dependencia por publicación, deduplicadas por
 * (empleado, dependencia). Un empleado puede pertenecer a una dependencia por
 * varias vías (§5.1): principal (CEMP_CO_DEPEND), configurada (CO_DEPENDENCIA),
 * o asignada en TDTX_DEPENDENCIA_EMPLEADO (ES_EMP='0'). Se conservan las vías
 * para explicar la pertenencia; es_activa refleja CEMP_EST_EMP del empleado.
 *
 * Índice por (publicacion_id, dependencia_codigo) para armar el padrón de una
 * oficina sin recorrer todo el catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleado_dependencia_captura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicacion_id')->constrained('publicaciones')->cascadeOnDelete();
            $table->string('empleado_codigo', 10);
            $table->string('dependencia_codigo', 10);
            $table->boolean('es_principal')->default(false);
            $table->boolean('es_configurada')->default(false);
            $table->boolean('es_asignada')->default(false);
            $table->boolean('es_activa')->default(false);
            $table->timestampsTz();

            $table->unique(
                ['publicacion_id', 'empleado_codigo', 'dependencia_codigo'],
                'empleado_dependencia_captura_clave'
            );
            $table->index(['publicacion_id', 'dependencia_codigo'], 'empleado_dependencia_captura_dep_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleado_dependencia_captura');
    }
};
