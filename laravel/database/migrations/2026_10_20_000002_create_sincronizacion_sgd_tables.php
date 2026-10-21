<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sincronizacion_sgd', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->boolean('automatica')->default(true);
            $table->unsignedSmallInteger('intervalo_minutos')->default(15);
            $table->timestampTz('proxima_ejecucion_en')->nullable();
            $table->timestampTz('scheduler_visto_en')->nullable();
            $table->timestampTz('worker_visto_en')->nullable();
            $table->timestampsTz();
        });
        DB::table('sincronizacion_sgd')->insert([
            'id' => 1, 'automatica' => true, 'intervalo_minutos' => 15,
            'proxima_ejecucion_en' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::create('sincronizaciones_sgd', function (Blueprint $table) {
            $table->id();
            $table->string('origen', 20);
            $table->string('tipo', 20)->default('incremental');
            $table->string('dependencia', 5)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('estado', 20)->default('pendiente')->index();
            // Una única reserva, pendiente o en curso, incluso con peticiones concurrentes.
            $table->unsignedSmallInteger('activa')->nullable()->unique();
            $table->timestampTz('inicio_en')->nullable();
            $table->timestampTz('fin_en')->nullable();
            $table->foreignId('publicacion_id')->nullable()->constrained('publicaciones')->restrictOnDelete();
            $table->json('metricas')->nullable();
            $table->string('error_resumen', 500)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sincronizaciones_sgd');
        Schema::dropIfExists('sincronizacion_sgd');
    }
};
