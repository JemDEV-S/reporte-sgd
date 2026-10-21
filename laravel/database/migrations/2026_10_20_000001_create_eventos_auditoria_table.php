<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('accion', 80)->index();
            $table->string('entidad_tipo', 40);
            $table->unsignedBigInteger('entidad_clave');
            $table->json('contexto');
            $table->timestampTz('ocurrido_en')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_auditoria');
    }
};
