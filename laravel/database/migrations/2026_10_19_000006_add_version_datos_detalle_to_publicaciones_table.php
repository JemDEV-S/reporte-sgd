<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versión del enriquecimiento de detalle de una publicación. Nula en las
 * publicaciones anteriores a esta mejora: permite distinguir "dato ausente" de
 * "publicación aún no enriquecida", y mostrar "Publicación sin datos de
 * integrantes" en lugar de "Sin integrantes".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->unsignedInteger('version_datos_detalle')->nullable()->after('tipo_ingesta');
        });
    }

    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->dropColumn('version_datos_detalle');
        });
    }
};
