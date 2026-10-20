<?php

namespace App\Http\Controllers;

use App\Explorador\CatalogoExplorador;
use App\Explorador\ConsultaExplorador;
use App\Explorador\MotorExplorador;
use App\Explorador\RepositorioConsultas;
use App\Models\ConsultaGuardada;
use App\Models\Publicacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Explorador de datos (beta) sobre los destinos pendientes de la publicación
 * vigente. Permite elegir campos para agrupar, filtros de lista cerrada y un
 * orden, todo desde un catálogo permitido (nunca SQL libre), y guardar la
 * consulta con versiones.
 */
class ExploradorController extends Controller
{
    public function __construct(
        private readonly RepositorioConsultas $repositorio,
    ) {}

    /**
     * Muestra el explorador: el formulario construido desde el catálogo, el
     * resultado de la consulta actual y las consultas guardadas visibles.
     */
    public function index(Request $request): View
    {
        $publicacion = Publicacion::vigente();
        $areas = $this->areas();

        if ($publicacion === null) {
            return view('explorador.sin-datos', ['areas' => $areas, 'area' => 'explorador']);
        }

        $consulta = ConsultaExplorador::desde($this->entradaDesde($request));
        $resultado = (new MotorExplorador($publicacion))->ejecutar($consulta);

        return view('explorador.index', [
            'areas' => $areas,
            'area' => 'explorador',
            'campos' => CatalogoExplorador::campos(),
            'consulta' => $consulta,
            'resultado' => $resultado,
            'guardadas' => $this->repositorio->visiblesPara($request->user()),
            'request' => $request,
        ]);
    }

    /**
     * Guarda la consulta actual (la de los parámetros del request) como una
     * consulta nueva con su primera versión.
     */
    public function guardar(Request $request): RedirectResponse
    {
        Gate::authorize('explorador.usar');

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'visibilidad' => ['nullable', 'in:privada,institucional'],
        ]);

        $consulta = ConsultaExplorador::desde($this->entradaDesde($request));
        $this->repositorio->crear(
            autor: $request->user(),
            nombre: $datos['nombre'],
            consulta: $consulta,
            visibilidad: $datos['visibilidad'] ?? 'privada',
        );

        return redirect()
            ->route('explorador.index', $consulta->aArray())
            ->with('estado', 'Consulta guardada.');
    }

    /**
     * Carga una consulta guardada: redirige al explorador con su última versión
     * aplicada en los parámetros, revalidada contra el catálogo.
     */
    public function cargar(Request $request, ConsultaGuardada $consulta): RedirectResponse
    {
        abort_unless($this->repositorio->puedeVer($request->user(), $consulta), 403);

        $version = $consulta->versionVigente();
        $spec = $version?->aConsultaExplorador() ?? new ConsultaExplorador;

        return redirect()->route('explorador.index', $spec->aArray());
    }

    /**
     * Normaliza la entrada del request a la forma que entiende
     * ConsultaExplorador::desde(). Las agrupaciones llegan como lista.
     *
     * @return array<string, mixed>
     */
    private function entradaDesde(Request $request): array
    {
        return [
            'agrupaciones' => (array) $request->query('agrupaciones', []),
            'filtros' => (array) $request->query('filtros', []),
            'dependencia' => $request->query('dependencia'),
            'orden' => $request->query('orden'),
        ];
    }

    private function areas(): Collection
    {
        return collect(config('platform.areas'))->map(fn (array $item) => [
            ...$item, 'allowed' => Gate::allows($item['permission']),
        ]);
    }
}
