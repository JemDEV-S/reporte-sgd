<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Informes inteligentes (beta del modelo §9). Un informe se redacta a
        // partir de agregados verificados del motor; nunca inventa cifras. La
        // clave de idempotencia impide duplicar el informe de una misma
        // publicación y alcance; la aprobación humana lo cierra.
        Schema::create('informes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicacion_id')->constrained('publicaciones')->cascadeOnDelete();
            $table->foreignId('generado_por')->constrained('users')->cascadeOnDelete();
            // Reporte/base del informe. La beta se acota a pendientes.
            $table->string('reporte')->default('pendientes_dependencia');
            // Alcance: código de dependencia, o null para el informe institucional.
            $table->string('dependencia')->nullable();
            // Encabezado del memorando (editable al generar): número, destinatario,
            // remitente, cargos y asunto. Salen en el documento Word.
            $table->string('numero')->nullable();
            $table->string('destinatario')->nullable();
            $table->string('destinatario_cargo')->nullable();
            $table->string('remitente')->nullable();
            $table->string('remitente_cargo')->nullable();
            $table->string('asunto')->nullable();
            // 'borrador' | 'aprobado'. Un informe aprobado queda cerrado.
            $table->string('estado')->default('borrador');
            // Narrativa redactada y los agregados verificados que la sustentan.
            $table->text('contenido');
            $table->jsonb('evidencia');
            // Cómo se redactó (plantilla determinista, o un proveedor de IA futuro).
            $table->string('redactor')->default('plantilla');
            // Idempotencia: misma publicación + alcance + redactor ⇒ mismo informe.
            $table->string('idempotencia_clave')->unique();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('aprobado_en')->nullable();
            $table->timestampsTz();

            $table->index(['reporte', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informes');
    }
};
