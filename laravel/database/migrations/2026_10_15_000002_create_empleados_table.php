<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombres');
            $table->string('apellidos');
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->index('dependencia_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
