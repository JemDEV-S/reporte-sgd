<?php

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function __invoke(): View
    {
        return view('administracion.auditoria', [
            'eventos' => DB::table('eventos_auditoria')->leftJoin('users', 'users.id', '=', 'eventos_auditoria.user_id')
                ->select('eventos_auditoria.*', 'users.nombre', 'users.apellidos')
                ->orderByDesc('eventos_auditoria.id')->paginate(30),
        ]);
    }
}
