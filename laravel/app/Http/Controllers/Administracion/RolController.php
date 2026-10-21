<?php

namespace App\Http\Controllers\Administracion;

use App\Administracion\GestionarRol;
use App\Enums\PermisoCodigo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\GuardarRolRequest;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RolController extends Controller
{
    public function index(): View
    {
        return view('administracion.roles.index', [
            'roles' => Rol::withCount(['usuarios', 'permisos'])->orderBy('nombre')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return $this->formulario(new Rol);
    }

    public function edit(Rol $rol): View
    {
        return $this->formulario($rol->load('permisos'));
    }

    public function store(GuardarRolRequest $request, GestionarRol $accion): RedirectResponse
    {
        $rol = $accion->guardar($request->user(), null, $request->validated());

        return to_route('admin.roles.edit', $rol)->with('estado', 'Rol creado.');
    }

    public function update(GuardarRolRequest $request, Rol $rol, GestionarRol $accion): RedirectResponse
    {
        $accion->guardar($request->user(), $rol, $request->validated());

        return to_route('admin.roles.edit', $rol)->with('estado', 'Rol actualizado.');
    }

    private function formulario(Rol $rol): View
    {
        return view('administracion.roles.form', [
            'rol' => $rol,
            'grupos' => Permiso::whereIn('codigo', array_column(PermisoCodigo::cases(), 'value'))
                ->orderBy('codigo')->get()->groupBy(fn ($permiso) => explode('.', $permiso->codigo)[0]),
        ]);
    }
}
