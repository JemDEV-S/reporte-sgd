<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía documentos_captura con los metadatos documentales legibles que exige
 * el detalle mejorado (§4 del diseño): tipo del emisor, número documental, sus
 * componentes, asunto y origen. Todo anulable: una captura anterior a esta
 * mejora (publicacion.version_datos_detalle nula) no tiene estos valores, y un
 * documento puede carecer de algún componente en el SGD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_captura', function (Blueprint $table) {
            // Tipo de emisor del SGD (TI_EMI): decide el formato del número.
            $table->string('tipo_emisor', 2)->nullable()->after('tipo_documental');
            // Número documental visible, autoritativo desde TDTX_REMITOS_RESUMEN.NU_DOC,
            // con la reconstrucción NU_DOC_EMI-NU_ANN-DE_DOC_SIG como respaldo.
            $table->string('numero_documento', 80)->nullable()->after('tipo_emisor');
            // Componentes originales conservados para trazabilidad (§4.2).
            $table->string('numero_emisor', 10)->nullable()->after('numero_documento');
            $table->string('sigla_documento', 60)->nullable()->after('numero_emisor');
            // Faltan componentes del número en un tipo que debería tenerlos (01/05).
            $table->boolean('numero_incompleto')->default(false)->after('sigla_documento');
            // Asunto: texto libre, dato sensible (permiso datos.ver_asuntos).
            $table->text('asunto')->nullable()->after('numero_incompleto');
            // Origen / remitente de la cabecera (§4.3). Códigos por separado y la
            // descripción resuelta; la descripción externa puede ser personal.
            $table->string('dependencia_emisora_codigo', 10)->nullable()->after('asunto');
            $table->string('emisor_empleado_codigo', 10)->nullable()->after('dependencia_emisora_codigo');
            $table->text('origen_descripcion')->nullable()->after('emisor_empleado_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_captura', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_emisor', 'numero_documento', 'numero_emisor', 'sigla_documento',
                'numero_incompleto', 'asunto', 'dependencia_emisora_codigo',
                'emisor_empleado_codigo', 'origen_descripcion',
            ]);
        });
    }
};
