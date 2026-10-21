import test from 'node:test';
import assert from 'node:assert/strict';
import { iniciarSeguimientoSincronizacion } from '../../resources/js/sincronizacion-monitor.js';

function fixture(request) {
    const timers = new Map();
    let nextId = 0;
    const feedback = { textContent: '' };
    const parts = Object.fromEntries(['resumen', 'accion', 'historial'].map(name => [name, { innerHTML: '', contains: () => false }]));
    const form = { value: 'cambio sin guardar' };
    const documentRef = { hidden: false, activeElement: null, addEventListener() {}, removeEventListener() {} };
    const root = {
        dataset: { syncActive: '1', syncStatusUrl: '/administracion/sincronizacion/estado' },
        querySelector: selector => selector === '[data-sync-feedback]' ? feedback : parts[selector.match(/="(.*?)"/)?.[1]],
    };
    const stop = iniciarSeguimientoSincronizacion(root, {
        request, documentRef,
        schedule: (callback, delay) => { const id = ++nextId; timers.set(id, { callback, delay }); return id; },
        cancel: id => timers.delete(id),
    });
    return {
        parts, feedback, form, stop, documentRef, timers,
        async tick() { const [id, timer] = timers.entries().next().value; timers.delete(id); await timer.callback(); },
    };
}

test('actualiza estados y libera el botón conservando el formulario de configuración', async () => {
    const ui = fixture(async () => ({ ok: true, status: 200, json: async () => ({ activa: false, fragmentos: { resumen: 'Exitosa', accion: 'Sincronizar ahora', historial: '#6 completada' } }) }));
    await ui.tick();
    assert.equal(ui.parts.accion.innerHTML, 'Sincronizar ahora');
    assert.equal(ui.parts.historial.innerHTML, '#6 completada');
    assert.equal(ui.form.value, 'cambio sin guardar');
    assert.equal([...ui.timers.values()][0].delay, 15000);
    ui.stop();
    assert.equal(ui.timers.size, 0);
});

test('detiene consultas cuando se revocan los permisos', async () => {
    const ui = fixture(async () => ({ status: 403 }));
    await ui.tick();
    assert.equal(ui.timers.size, 0);
    assert.match(ui.feedback.textContent, /permisos cambiaron/);
    ui.stop();
});

test('un fallo de red conserva el estado visible y programa otro intento', async () => {
    const ui = fixture(async () => { throw new Error('Sin conexión'); });
    ui.parts.accion.innerHTML = 'Pendiente de procesamiento';
    await ui.tick();
    assert.equal(ui.parts.accion.innerHTML, 'Pendiente de procesamiento');
    assert.equal([...ui.timers.values()][0].delay, 5000);
    assert.match(ui.feedback.textContent, /volverá a intentar/);
    ui.stop();
});

test('no consulta cuando la pestaña está oculta', async () => {
    let calls = 0;
    const ui = fixture(async () => { calls++; });
    ui.documentRef.hidden = true;
    await ui.tick();
    assert.equal(calls, 0);
    ui.stop();
});
