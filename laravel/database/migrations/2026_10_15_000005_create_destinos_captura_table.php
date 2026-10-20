<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinos_captura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicacion_id')->constrained('publicaciones')->cascadeOnDelete();
            // Clave fuente del destino: (año, emisión, destino).
            $table->string('anio', 4);
            $table->string('emision', 20);
            $table->string('destino', 20);
            // '01' = destino interno; los externos no son pendientes de oficina.
            $table->string('tipo_destino', 2)->default('01');
            // Oficina de destino y destinatario, como códigos fuente (texto con ceros).
            $table->string('dependencia_codigo', 10);
            $table->string('destinatario_empleado', 20)->nullable();
            // Encargado capturado de la dependencia en el momento de la publicación.
            $table->string('encargado_empleado', 20)->nullable();
            // Estado de recepción: '0' = no leído, '1' = recibido sin atender.
            $table->string('estado_recepcion', 2)->default('0');
            $table->boolean('activo')->default(true);
            // Prioridad: '1' normal (azul), '2' urgente (naranja), '3' muy urgente (rojo).
            $table->string('prioridad', 1)->nullable();
            $table->timestampTz('fecha_recepcion')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['publicacion_id', 'anio', 'emision', 'destino'],
                'destinos_captura_clave_fuente'
            );
            $table->index(['publicacion_id', 'dependencia_codigo', 'estado_recepcion'], 'destinos_captura_dep_estado_idx');
            $table->index(['publicacion_id', 'dependencia_codigo', 'prioridad'], 'destinos_captura_dep_prioridad_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destinos_captura');
    }
};
