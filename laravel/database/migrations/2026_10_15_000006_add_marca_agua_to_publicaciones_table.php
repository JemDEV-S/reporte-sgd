<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            // Mayor FE_USE_MOD del SGD cubierto por esta publicación; punto de
            // partida de la siguiente ingesta incremental.
            $table->timestampTz('marca_agua')->nullable()->after('estado');
            // 'completa' (relee todo el universo) o 'incremental' (clona y aplica cambios).
            $table->string('tipo_ingesta', 20)->default('completa')->after('marca_agua');
        });
    }

    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->dropColumn(['marca_agua', 'tipo_ingesta']);
        });
    }
};
