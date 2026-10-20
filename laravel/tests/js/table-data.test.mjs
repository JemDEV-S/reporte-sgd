import test from 'node:test';
import assert from 'node:assert/strict';
import { selectRows } from '../../resources/js/table-data.js';

const rows = [
    { index: 0, text: 'Alcaldía 1,200', values: ['Alcaldía', '1,200'] },
    { index: 1, text: 'Administración 90', values: ['Administración', '90'] },
    { index: 2, text: 'Oficina 90', values: ['Oficina', '90'] },
];
test('encuentra nombres sin tildes y sin distinguir mayúsculas', () => {
    assert.deepEqual(selectRows(rows, { query: '  ALCALDIA ' }).map(r => r.index), [0]);
});
test('ordena cifras con separador de miles y conserva el orden de los empates', () => {
    assert.deepEqual(selectRows(rows, { column: 1, numeric: true }).map(r => r.index), [1, 2, 0]);
    assert.deepEqual(selectRows(rows, { column: 1, numeric: true, direction: -1 }).map(r => r.index), [0, 1, 2]);
});
test('combina búsqueda y ordenación sin modificar los registros originales', () => {
    assert.deepEqual(selectRows(rows, { query: '90', column: 0, direction: -1 }).map(r => r.index), [2, 1]);
    assert.deepEqual(rows.map(r => r.index), [0, 1, 2]);
    assert.deepEqual(selectRows(rows, { query: 'inexistente' }), []);
});
