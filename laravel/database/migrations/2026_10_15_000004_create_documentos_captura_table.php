<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_captura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicacion_id')->constrained('publicaciones')->cascadeOnDelete();
            // Clave fuente del SGD, conservada como texto con ceros iniciales.
            $table->string('anio', 4);
            $table->string('emision', 20);
            $table->string('tipo_documental', 20)->nullable();
            $table->string('estado_emision', 2)->nullable();
            $table->boolean('eliminado')->default(false);
            $table->timestampTz('fecha_emision')->nullable();
            $table->timestampsTz();

            $table->unique(['publicacion_id', 'anio', 'emision'], 'documentos_captura_clave_fuente');
            $table->index(['publicacion_id', 'fecha_emision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_captura');
    }
};
