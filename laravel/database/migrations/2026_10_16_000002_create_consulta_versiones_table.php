<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Versiones de una consulta guardada (modelo §8). Cada cambio crea una
        // versión nueva en lugar de alterar la anterior: las consultas quedan
        // reproducibles. La configuración es declarativa (selección, filtros,
        // agrupaciones, orden); nunca SQL libre.
        Schema::create('consulta_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_id')->constrained('consultas_guardadas')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            // Especificación declarativa validada contra el catálogo del explorador.
            $table->jsonb('agrupaciones');
            $table->jsonb('filtros');
            $table->string('dependencia')->nullable();
            $table->string('orden')->default('cantidad_desc');
            $table->timestampsTz();

            $table->unique(['consulta_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consulta_versiones');
    }
};
