<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependencias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre');
            $table->foreignId('dependencia_padre_id')->nullable()->constrained('dependencias')->nullOnDelete();
            $table->unsignedSmallInteger('nivel')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestampsTz();

            $table->index('dependencia_padre_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependencias');
    }
};
