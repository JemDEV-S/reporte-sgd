<?php

namespace App\Administracion;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegistrarAuditoria
{
    /** El contexto debe contener únicamente metadatos, nunca credenciales. */
    public function registrar(User $actor, string $accion, string $tipo, int $id, array $contexto): void
    {
        DB::table('eventos_auditoria')->insert([
            'user_id' => $actor->id,
            'accion' => $accion,
            'entidad_tipo' => $tipo,
            'entidad_clave' => $id,
            'contexto' => json_encode($contexto, JSON_THROW_ON_ERROR),
            'ocurrido_en' => now(),
        ]);
    }
}
