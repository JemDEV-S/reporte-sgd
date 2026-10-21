export function iniciarSeguimientoSincronizacion(root, {
    request = globalThis.fetch,
    schedule = globalThis.setTimeout,
    cancel = globalThis.clearTimeout,
    documentRef = globalThis.document,
} = {}) {
    if (!root) return () => {};
    let stopped = false;
    let active = root.dataset.syncActive === '1';
    let timer;
    let controller;
    let inFlight = false;
    const feedback = root.querySelector('[data-sync-feedback]');
    const previous = new Map();

    function plan() {
        if (!stopped) timer = schedule(poll, active ? 5000 : 15000);
    }
    async function poll() {
        if (stopped || inFlight) return;
        if (documentRef.hidden) { plan(); return; }
        inFlight = true;
        controller = new AbortController();
        const timeout = schedule(() => controller.abort(), 10000);
        try {
            const response = await request(root.dataset.syncStatusUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
            });
            if ([401, 403, 419].includes(response.status)) {
                stopped = true;
                if (feedback) feedback.textContent = 'Tu sesión o tus permisos cambiaron. Actualiza la página para continuar.';
                return;
            }
            if (!response.ok) throw new Error('Estado no disponible');
            const data = await response.json();
            if (stopped) return;
            if (typeof data.activa !== 'boolean' || !data.fragmentos) throw new Error('Respuesta no válida');
            for (const name of ['resumen', 'accion', 'historial']) {
                const html = data.fragmentos[name];
                const target = root.querySelector(`[data-sync-fragment="${name}"]`);
                if (!target || typeof html !== 'string' || previous.get(name) === html) continue;
                const focusedId = target.contains(documentRef.activeElement) ? documentRef.activeElement?.id : null;
                target.innerHTML = html;
                previous.set(name, html);
                if (focusedId) {
                    const replacement = documentRef.getElementById(focusedId);
                    if (replacement && !replacement.disabled) replacement.focus();
                }
            }
            active = data.activa;
            root.dataset.syncActive = active ? '1' : '0';
            if (feedback) feedback.textContent = active
                ? 'Seguimiento activo: el estado se actualiza cada 5 segundos.'
                : 'No hay solicitudes pendientes. El estado se actualizará automáticamente.';
        } catch {
            if (!stopped && feedback) feedback.textContent = 'No se pudo actualizar el estado. Se volverá a intentar; también puedes usar Actualizar estado.';
        } finally {
            cancel(timeout);
            inFlight = false;
            plan();
        }
    }
    function resume() {
        if (stopped || documentRef.hidden || inFlight) return;
        cancel(timer);
        poll();
    }
    documentRef.addEventListener('visibilitychange', resume);
    plan();
    return () => {
        stopped = true;
        cancel(timer);
        controller?.abort();
        documentRef.removeEventListener('visibilitychange', resume);
    };
}
