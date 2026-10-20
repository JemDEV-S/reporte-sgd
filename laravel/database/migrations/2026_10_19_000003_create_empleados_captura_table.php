<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Padrón de empleados capturado por publicación (RHTM_PER_EMPLEADOS). Guarda el
 * nombre (apellidos y denominación por separado), el estado de empleado y las
 * dos dependencias originales. El padrón NO se limita a destinatarios con
 * pendientes: incluye activos sin pendientes e inactivos referenciados, para
 * explicar el padrón activo y las "otras asignaciones".
 *
 * Regla de actividad verificada en la fuente (§10): CEMP_EST_EMP='1' = Activo,
 * '0' = Baja. CEMP_INDBAJ se conserva como dato, no como filtro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados_captura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicacion_id')->constrained('publicaciones')->cascadeOnDelete();
            $table->string('codigo', 10);
            $table->string('apellido_paterno', 60)->nullable();
            $table->string('apellido_materno', 60)->nullable();
            $table->string('denominacion', 120)->nullable();
            // CEMP_EST_EMP: '1' Activo, '0' Baja.
            $table->string('estado_empleado_codigo', 1)->nullable();
            // CEMP_INDBAJ, conservado como dato fuente.
            $table->string('baja_codigo', 1)->nullable();
            // Dependencia principal (CEMP_CO_DEPEND) y configurada (CO_DEPENDENCIA).
            $table->string('dependencia_principal_codigo', 10)->nullable();
            $table->string('dependencia_configurada_codigo', 10)->nullable();
            $table->timestampsTz();

            $table->unique(['publicacion_id', 'codigo'], 'empleados_captura_clave');
            $table->index(['publicacion_id', 'estado_empleado_codigo'], 'empleados_captura_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados_captura');
    }
};
