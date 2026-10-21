<?php

namespace App\Http\Controllers\Administracion;

use App\Administracion\GestionarUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\GuardarUsuarioRequest;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $datos = $request->validate(['buscar' => ['nullable', 'string', 'max:100'], 'estado' => ['nullable', 'in:activos,inactivos']]);
        $buscar = trim($datos['buscar'] ?? '');
        $usuarios = User::query()->with('roles')->orderBy('apellidos')->orderBy('id')
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->where(function ($query) use ($buscar) {
                    foreach (['dni', 'nombre', 'apellidos', 'email'] as $campo) {
                        $query->orWhereRaw('lower('.$campo.') like ?', ['%'.mb_strtolower($buscar).'%']);
                    }
                });
            })
            ->when($datos['estado'] ?? null, fn ($query, $estado) => $query->where('activo', $estado === 'activos'))
            ->paginate(20)->withQueryString();

        return view('administracion.usuarios.index', compact('usuarios', 'buscar'));
    }

    public function create(): View
    {
        return $this->formulario(new User(['activo' => true]));
    }

    public function edit(User $usuario): View
    {
        return $this->formulario($usuario->load('roles'));
    }

    public function store(GuardarUsuarioRequest $request, GestionarUsuario $accion): RedirectResponse
    {
        $usuario = $accion->guardar($request->user(), null, $request->validated());

        return to_route('admin.usuarios.edit', $usuario)->with('estado', 'Cuenta creada.');
    }

    public function update(GuardarUsuarioRequest $request, User $usuario, GestionarUsuario $accion): RedirectResponse
    {
        $accion->guardar($request->user(), $usuario, $request->validated());

        return to_route('admin.usuarios.edit', $usuario)->with('estado', 'Cuenta actualizada.');
    }

    private function formulario(User $usuario): View
    {
        return view('administracion.usuarios.form', [
            'usuario' => $usuario,
            'roles' => Rol::orderBy('nombre')->get(),
        ]);
    }
}
