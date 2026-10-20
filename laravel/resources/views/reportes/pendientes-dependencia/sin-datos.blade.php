<x-layouts.platform :areas="$areas" :area="$area">
    <x-page-header icon="reportes" eyebrow="CATÁLOGO DE REPORTES" title="Pendientes por dependencia"
        description="Documentos no leídos y recibidos sin atender, en entrada y delegados, por dependencia." />
    <section class="rounded-2xl border border-line bg-white px-7 py-12 sm:p-12">
        <x-illustration class="mb-2 w-48" />
        <span class="status-label">Sin publicación</span>
        <h2 class="mt-5 text-xl font-medium">Todavía no hay datos publicados</h2>
        <p class="mt-3 max-w-lg text-sm leading-7 text-muted">
            El reporte se calcula sobre una publicación vigente de los datos documentales. Cuando se
            registre la primera publicación, aquí aparecerá el resumen por dependencia.
        </p>
        <a href="{{ route('home') }}" class="mt-8 inline-block text-sm font-semibold text-emerald">← Volver al inicio</a>
    </section>
</x-layouts.platform>
