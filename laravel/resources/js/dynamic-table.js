import { selectRows } from './table-data';

export function initDynamicTables(root = document) {
    root.querySelectorAll('[data-dynamic-table]:not([data-table-ready])').forEach((container) => {
        const table = container.querySelector('table');
        const body = table?.tBodies[0];
        if (!body) return;
        container.dataset.tableReady = 'true';
        const headers = [...table.tHead.rows[0].cells];
        const rows = [...body.rows].filter(row => row.cells.length === headers.length).map((element, index) => ({
            element, index, text: element.textContent,
            values: [...element.cells].map(cell => cell.dataset.sortValue ?? cell.textContent.trim()),
        }));
        if (!rows.length) return;

        const tools = document.createElement('div');
        tools.className = 'table-tools';
        const search = document.createElement('input');
        search.type = 'search';
        search.placeholder = 'Filtrar esta tabla…';
        search.setAttribute('aria-label', 'Filtrar filas de esta tabla, sin modificar los indicadores ni la exportación');
        const size = document.createElement('select');
        size.setAttribute('aria-label', 'Filas por página de tabla');
        for (const value of [10, 25, 50, 0]) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value ? `${value} filas` : 'Todas';
            size.append(option);
        }
        tools.append(search, size);
        container.insertBefore(tools, table.parentElement);

        const footer = document.createElement('div');
        footer.className = 'table-pager';
        const status = document.createElement('span');
        status.setAttribute('role', 'status');
        const navigation = document.createElement('div');
        navigation.className = 'flex items-center gap-2';
        const previous = document.createElement('button');
        previous.type = 'button';
        previous.textContent = 'Anterior';
        previous.setAttribute('aria-label', 'Página anterior de la tabla');
        const next = document.createElement('button');
        next.type = 'button';
        next.textContent = 'Siguiente';
        next.setAttribute('aria-label', 'Página siguiente de la tabla');
        navigation.append(previous, next);
        footer.append(status, navigation);
        container.append(footer);

        const empty = body.insertRow();
        empty.hidden = true;
        const emptyCell = empty.insertCell();
        emptyCell.colSpan = headers.length;
        emptyCell.className = 'px-4 py-6 text-center text-sm text-muted';
        emptyCell.textContent = 'No hay filas que coincidan con la búsqueda.';
        let page = 0;
        let order = { column: null, direction: 1, numeric: false };
        const render = () => {
            const selected = selectRows(rows, { ...order, query: search.value });
            const limit = Number(size.value) || Math.max(1, selected.length);
            const pages = Math.max(1, Math.ceil(selected.length / limit));
            page = Math.max(0, Math.min(page, pages - 1));
            rows.forEach(row => { row.element.hidden = true; });
            selected.forEach((row, index) => {
                row.element.hidden = index < page * limit || index >= (page + 1) * limit;
                body.append(row.element);
            });
            empty.hidden = selected.length !== 0;
            body.append(empty);
            status.textContent = `${selected.length ? page * limit + 1 : 0}–${Math.min((page + 1) * limit, selected.length)} de ${selected.length} filas${selected.length !== rows.length ? ` (${rows.length} en total)` : ''}`;
            previous.disabled = page === 0;
            next.disabled = page >= pages - 1;
            navigation.hidden = pages === 1;
        };
        headers.forEach((header, column) => {
            if (header.dataset.sortType === 'none') return;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sort-button';
            button.setAttribute('aria-label', `Ordenar por ${header.innerText.trim().replace(/\s+/g, ' ')}`);
            while (header.firstChild) button.append(header.firstChild);
            const indicator = document.createElement('span');
            indicator.textContent = '↕';
            indicator.setAttribute('aria-hidden', 'true');
            indicator.dataset.sortIndicator = 'true';
            button.append(indicator);
            header.append(button);
            header.setAttribute('aria-sort', 'none');
            button.addEventListener('click', () => {
                order = { column, direction: order.column === column ? -order.direction : 1, numeric: header.dataset.sortType === 'number' };
                headers.forEach(th => { if (th.hasAttribute('aria-sort')) th.setAttribute('aria-sort', 'none'); });
                headers.forEach(th => {
                    const arrow = th.querySelector('[data-sort-indicator]');
                    if (arrow) arrow.textContent = '↕';
                });
                header.setAttribute('aria-sort', order.direction === 1 ? 'ascending' : 'descending');
                indicator.textContent = order.direction === 1 ? '↑' : '↓';
                page = 0;
                render();
            });
        });
        search.addEventListener('input', () => { page = 0; render(); });
        size.addEventListener('change', () => { page = 0; render(); });
        previous.addEventListener('click', () => { page--; render(); });
        next.addEventListener('click', () => { page++; render(); });
        render();
    });
}
