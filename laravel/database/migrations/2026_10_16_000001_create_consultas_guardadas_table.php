<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consultas guardadas del explorador (modelo §8). Guardar o compartir una
        // consulta no comparte sus datos: el alcance se calcula al ejecutar.
        Schema::create('consultas_guardadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creado_por')->constrained('users')->cascadeOnDelete();
            $table->string('nombre');
            // Reporte/base de datos al que aplica. La beta se acota a pendientes.
            $table->string('reporte')->default('pendientes_dependencia');
            // 'privada' = solo el autor; 'institucional' = visible para todos.
            $table->string('visibilidad')->default('privada');
            $table->timestampsTz();

            $table->index(['creado_por', 'reporte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas_guardadas');
    }
};
