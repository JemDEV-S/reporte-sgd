<x-layouts.platform :areas="$areas" :area="$area">
    <x-page-header eyebrow="EXPLORADOR DE DATOS" title="Explorador de datos"
        description="Consultas dinámicas sobre los destinos pendientes de la publicación vigente." />
    <section class="rounded-2xl border border-line bg-white px-7 py-12 sm:p-12">
        <span class="status-label">Sin publicación</span>
        <h2 class="mt-5 text-xl font-medium">Todavía no hay datos publicados</h2>
        <p class="mt-3 max-w-lg text-sm leading-7 text-muted">
            El explorador consulta la publicación vigente de los datos documentales. Cuando se
            registre la primera publicación, aquí podrás agrupar y filtrar los pendientes.
        </p>
        <a href="{{ route('home') }}" class="mt-8 inline-block text-sm font-semibold text-emerald">← Volver al inicio</a>
    </section>
</x-layouts.platform>
