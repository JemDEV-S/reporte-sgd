import { initDynamicTables } from './dynamic-table';
import './bootstrap';
import { iniciarModalIntegrantes } from './integrantes-modal';

// El "abrir" se delega en document, así que registrar el listener no depende de
// que el <dialog> ni el botón existan todavía (robusto en dev con Vite).
iniciarModalIntegrantes();

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Ocultar' : 'Mostrar';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
});

// The chart remains usable without JavaScript; enhance pointer and keyboard inspection.
document.querySelectorAll('[data-evolucion]').forEach((chart) => {
    const output = chart.querySelector('[data-chart-value]');
    if (!output) return;
    const initial = output.textContent;
    chart.querySelectorAll('[data-chart-point]').forEach((point) => {
        const show = () => { output.textContent = point.dataset.chartPoint; };
        const reset = () => { output.textContent = initial; };
        point.addEventListener('mouseenter', show);
        point.addEventListener('focus', show);
        point.addEventListener('mouseleave', reset);
        point.addEventListener('blur', reset);
    });
});

initDynamicTables();
