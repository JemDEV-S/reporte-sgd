<?php

namespace App\Http\Controllers;

use App\Informes\DocumentoWord;
use App\Informes\GeneradorInforme;
use App\Models\Informe;
use App\Models\Publicacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Informes inteligentes (beta). Los informes se redactan a partir de agregados
 * verificados del motor de pendientes (nunca cifras inventadas), nacen como
 * borrador y requieren aprobación humana. La generación es idempotente: una
 * misma publicación y alcance no producen informes duplicados.
 */
class InformeController extends Controller
{
    /** Lista los informes del reporte, más la publicación vigente disponible. */
    public function index(): View
    {
        $publicacion = Publicacion::vigente();
        $informes = Informe::with(['autor', 'aprobador'])
            ->where('reporte', GeneradorInforme::REPORTE)
            ->orderByDesc('created_at')
            ->get();

        return view('informes.index', [
            'areas' => $this->areas(),
            'area' => 'informes',
            'publicacion' => $publicacion,
            'informes' => $informes,
            'puedeGenerar' => Gate::allows('informes.generar'),
        ]);
    }

    public function show(Informe $informe): View
    {
        Gate::authorize('informes.ver');

        return view('informes.show', [
            'areas' => $this->areas(),
            'area' => 'informes',
            'informe' => $informe->load(['autor', 'aprobador', 'publicacion']),
            'puedeAprobar' => Gate::allows('informes.aprobar'),
        ]);
    }

    /**
     * Genera (o recupera, por idempotencia) el borrador institucional de la
     * publicación vigente, con los datos de encabezado del memorando.
     */
    public function generar(Request $request): RedirectResponse
    {
        Gate::authorize('informes.generar');

        $publicacion = Publicacion::vigente();
        abort_if($publicacion === null, 404, 'No hay una publicación vigente para informar.');

        $encabezado = $request->validate([
            'numero' => ['nullable', 'string', 'max:120'],
            'destinatario' => ['nullable', 'string', 'max:160'],
            'destinatario_cargo' => ['nullable', 'string', 'max:160'],
            'remitente' => ['nullable', 'string', 'max:160'],
            'remitente_cargo' => ['nullable', 'string', 'max:160'],
            'asunto' => ['nullable', 'string', 'max:300'],
        ]);

        $informe = (new GeneradorInforme)->generar($publicacion, $request->user(), $encabezado);

        return redirect()
            ->route('informes.show', $informe)
            ->with('estado', $informe->wasRecentlyCreated
                ? 'Borrador generado. Requiere revisión y aprobación.'
                : 'Ya existía un informe para esta publicación; se muestra el borrador.');
    }

    /** Descarga el informe como documento Word (.docx). */
    public function descargar(Informe $informe): StreamedResponse
    {
        Gate::authorize('informes.ver');

        return (new DocumentoWord)->descargar($informe);
    }

    /** Aprueba un borrador tras la revisión humana. */
    public function aprobar(Informe $informe): RedirectResponse
    {
        Gate::authorize('informes.aprobar');

        $informe->aprobar(request()->user());

        return redirect()
            ->route('informes.show', $informe)
            ->with('estado', 'Informe aprobado.');
    }

    private function areas(): Collection
    {
        return collect(config('platform.areas'))->map(fn (array $item) => [
            ...$item, 'allowed' => Gate::allows($item['permission']),
        ]);
    }
}
