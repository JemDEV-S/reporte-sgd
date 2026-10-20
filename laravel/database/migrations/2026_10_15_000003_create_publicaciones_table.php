<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicaciones', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('fecha_publicacion');
            $table->string('perfil', 20)->default('institucional');
            $table->string('version_reglas', 20)->default('R01-R12');
            $table->string('estado', 20)->default('vigente');
            $table->text('nota')->nullable();
            $table->timestampsTz();

            $table->index(['estado', 'fecha_publicacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publicaciones');
    }
};
