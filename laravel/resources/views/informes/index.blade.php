@php
    use Illuminate\Support\Carbon;
@endphp

<x-layouts.platform :areas="$areas" :area="$area">
    <x-page-header eyebrow="INFORMES INTELIGENTES · BETA" title="Informes inteligentes"
        description="Informes situacionales de pendientes redactados a partir de agregados verificados del motor. Nacen como borrador y requieren aprobación humana." />

    @if (session('estado'))
        <x-alert class="mb-6">{{ session('estado') }}</x-alert>
    @endif

    {{-- Generar un nuevo informe sobre la publicación vigente --}}
    @if ($publicacion === null)
        <section class="mb-8 rounded-2xl border border-line bg-white px-7 py-10">
            <span class="status-label">Sin publicación</span>
            <h2 class="mt-5 text-lg font-medium">Todavía no hay datos publicados</h2>
            <p class="mt-3 max-w-lg text-sm leading-7 text-muted">El informe se redacta sobre la publicación vigente. Cuando exista, podrás generar un borrador aquí.</p>
        </section>
    @elseif ($puedeGenerar)
        <section class="mb-10 rounded-2xl border border-line bg-white p-6">
            <h2 class="text-sm font-semibold text-ink">Generar informe de la publicación vigente</h2>
            <p class="mt-1 text-xs text-muted">
                Publicación #{{ $publicacion->id }} ·
                {{ Carbon::parse($publicacion->fecha_publicacion)->translatedFormat('d \d\e F \d\e Y, H:i') }}.
                Las cifras se toman del motor; tú completas el encabezado del memorando.
            </p>
            <form method="POST" action="{{ route('informes.generar') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                @csrf
                <div class="sm:col-span-2">
                    <label for="numero" class="mb-1 block text-[11px] font-semibold text-muted">Número de informe</label>
                    <input id="numero" name="numero" maxlength="120" placeholder="N° 000135-2026-MDSJ/OTI" class="field">
                </div>
                <div>
                    <label for="destinatario" class="mb-1 block text-[11px] font-semibold text-muted">A (destinatario)</label>
                    <input id="destinatario" name="destinatario" maxlength="160" placeholder="Nombre del destinatario" class="field">
                </div>
                <div>
                    <label for="destinatario_cargo" class="mb-1 block text-[11px] font-semibold text-muted">Cargo del destinatario</label>
                    <input id="destinatario_cargo" name="destinatario_cargo" maxlength="160" placeholder="Gerente Municipal" class="field">
                </div>
                <div>
                    <label for="remitente" class="mb-1 block text-[11px] font-semibold text-muted">DE (remitente)</label>
                    <input id="remitente" name="remitente" maxlength="160" placeholder="Nombre del remitente" class="field">
                </div>
                <div>
                    <label for="remitente_cargo" class="mb-1 block text-[11px] font-semibold text-muted">Cargo del remitente</label>
                    <input id="remitente_cargo" name="remitente_cargo" maxlength="160" placeholder="Director OTI" class="field">
                </div>
                <div class="sm:col-span-2">
                    <label for="asunto" class="mb-1 block text-[11px] font-semibold text-muted">Asunto</label>
                    <input id="asunto" name="asunto" maxlength="300"
                        placeholder="Reporte situacional de documentos pendientes en el SGD" class="field">
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="btn btn-primary">Generar borrador</button>
                    <span class="ml-3 text-xs text-muted">La generación es idempotente: una misma publicación no crea borradores duplicados.</span>
                </div>
            </form>
        </section>
    @endif

    {{-- Lista de informes --}}
    <section aria-label="Informes" class="rounded-2xl border border-line bg-white">
        <div class="border-b border-line px-6 py-4">
            <h2 class="text-sm font-semibold text-ink">Informes generados</h2>
        </div>
        @if ($informes->isEmpty())
            <p class="px-6 py-10 text-center text-sm text-muted">Aún no se han generado informes.</p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($informes as $informe)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                        <div>
                            <a href="{{ route('informes.show', $informe) }}" class="text-sm font-semibold text-emerald hover:underline">
                                {{ $informe->numero ?: 'Informe de pendientes' }} · {{ $informe->alcance() }}
                            </a>
                            <p class="mt-0.5 text-xs text-muted">
                                Publicación #{{ $informe->publicacion_id }} ·
                                Generado {{ Carbon::parse($informe->created_at)->translatedFormat('d \d\e F \d\e Y, H:i') }}
                                @if ($informe->autor) por {{ $informe->autor->nombre_completo }} @endif
                            </p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-[11px] font-semibold {{ $informe->esAprobado() ? 'bg-emerald/10 text-emerald' : 'bg-amber-100 text-amber-700' }}">
                            {{ $informe->esAprobado() ? 'Aprobado' : 'Borrador' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.platform>
