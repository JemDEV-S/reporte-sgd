/**
 * Modal de integrantes (§5 de la mejora del detalle de pendientes).
 *
 * Un único <dialog> reutilizable abre el padrón de integrantes de la dependencia
 * y, dentro del mismo diálogo, el detalle paginado de una persona, volviendo al
 * padrón sin anidar modales. Los fragmentos Blade se cargan bajo demanda desde
 * los endpoints /integrantes y /integrantes/{empleado}/pendientes.
 *
 * El "abrir" se delega en document: así no depende de cuándo se ejecute el
 * módulo ni de que el botón exista al inicializar (en dev con Vite el módulo
 * puede cargarse tarde). Si el JS no corre o <dialog> no está soportado, el
 * enlace "Ver integrantes" navega al fragmento como fallback GET.
 *
 * Accesibilidad: showModal() atrapa el foco y habilita Escape; al cerrar se
 * restaura el foco al botón que abrió el modal; una región aria-live anuncia los
 * estados de carga.
 */
export function iniciarModalIntegrantes() {
    // Un solo listener global: intercepta el clic en "Ver integrantes" venga
    // cuando venga, sin exigir que el botón exista en el momento del init.
    document.addEventListener('click', (evento) => {
        const abridor = evento.target.closest('[data-ver-integrantes]');
        if (!abridor) {
            return;
        }
        const dialog = document.querySelector('[data-integrantes-dialog]');
        if (!dialog || typeof dialog.showModal !== 'function') {
            return; // Sin soporte de <dialog>: queda el fallback GET del enlace.
        }
        evento.preventDefault();
        abrir(dialog, abridor, abridor.dataset.integrantesUrl || abridor.href);
    });
}

/** Controlador del diálogo (se monta una sola vez por <dialog>). */
function controlador(dialog) {
    if (dialog._integrantes) {
        return dialog._integrantes;
    }

    const cuerpo = dialog.querySelector('[data-modal-cuerpo]');
    const estado = dialog.querySelector('[data-modal-estado]');
    let peticionActual = null; // AbortController de la carga en curso.
    let abridorActivo = null; // Botón que abrió el modal, para restaurar el foco.

    const anunciar = (texto) => {
        if (estado) {
            estado.textContent = texto;
        }
    };

    const mostrarCargando = () => {
        anunciar('Cargando…');
        cuerpo.innerHTML =
            '<p class="py-10 text-center text-sm text-muted">Cargando…</p>';
    };

    const mostrarError = (url) => {
        anunciar('No se pudo cargar la información.');
        cuerpo.innerHTML = '';
        const aviso = document.createElement('div');
        aviso.className = 'py-10 text-center text-sm text-muted';
        const texto = document.createElement('p');
        texto.textContent = 'No se pudo cargar la información.';
        const reintentar = document.createElement('button');
        reintentar.type = 'button';
        reintentar.className =
            'mt-3 rounded-lg border border-emerald/40 bg-emerald/5 px-3 py-1.5 text-sm font-semibold text-emerald hover:bg-emerald/10';
        reintentar.textContent = 'Reintentar';
        reintentar.addEventListener('click', () => cargar(url));
        aviso.append(texto, reintentar);
        cuerpo.append(aviso);
    };

    /**
     * Carga un fragmento en el cuerpo del modal. Cancela cualquier carga previa
     * (p. ej. al cambiar de persona) para no inyectar filas de otra selección.
     */
    const cargar = async (url) => {
        if (peticionActual) {
            peticionActual.abort();
        }
        const ctrl = new AbortController();
        peticionActual = ctrl;
        mostrarCargando();

        try {
            const respuesta = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: ctrl.signal,
                credentials: 'same-origin',
            });
            if (!respuesta.ok) {
                throw new Error(`HTTP ${respuesta.status}`);
            }
            const html = await respuesta.text();
            if (ctrl.signal.aborted) {
                return;
            }
            cuerpo.innerHTML = html;
            anunciar('Contenido actualizado.');
            cuerpo.scrollTop = 0;
        } catch (error) {
            if (error.name === 'AbortError') {
                return; // Reemplazada por una carga más reciente.
            }
            mostrarError(url);
        } finally {
            if (peticionActual === ctrl) {
                peticionActual = null;
            }
        }
    };

    const cerrar = () => {
        if (peticionActual) {
            peticionActual.abort();
            peticionActual = null;
        }
        if (dialog.open) {
            dialog.close();
        }
    };

    // Navegación interna del modal: enlaces de persona, vuelta al padrón y
    // paginación; búsqueda del padrón. Se delega en el cuerpo del modal.
    cuerpo.addEventListener('click', (evento) => {
        const enlace = evento.target.closest('a[href]');
        if (!enlace || !cuerpo.contains(enlace)) {
            return;
        }
        const gestionado =
            enlace.matches('[data-persona-link]') ||
            enlace.matches('[data-volver-padron]') ||
            enlace.closest('nav[role="navigation"], .pagination, [aria-label="Pagination Navigation"]');
        if (!gestionado) {
            return;
        }
        evento.preventDefault();
        cargar(enlace.href);
    });

    cuerpo.addEventListener('submit', (evento) => {
        const form = evento.target.closest('[data-padron-busqueda]');
        if (!form) {
            return;
        }
        evento.preventDefault();
        const params = new URLSearchParams(new FormData(form));
        cargar(`${form.action}?${params.toString()}`);
    });

    // Cierre: botón, click en el backdrop y evento close (Escape).
    dialog.querySelector('[data-modal-cerrar]')?.addEventListener('click', cerrar);
    dialog.addEventListener('click', (evento) => {
        if (evento.target === dialog) {
            cerrar(); // Click fuera del contenido (sobre el backdrop).
        }
    });
    dialog.addEventListener('close', () => {
        cuerpo.innerHTML = '';
        anunciar('');
        abridorActivo?.focus(); // Restaura el foco al botón que abrió el modal.
    });

    dialog._integrantes = {
        cargar,
        setAbridor: (a) => {
            abridorActivo = a;
        },
    };

    return dialog._integrantes;
}

function abrir(dialog, abridor, url) {
    const ctrl = controlador(dialog);
    ctrl.setAbridor(abridor);
    if (!dialog.open) {
        dialog.showModal();
    }
    ctrl.cargar(url);
}
