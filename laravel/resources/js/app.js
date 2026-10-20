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
