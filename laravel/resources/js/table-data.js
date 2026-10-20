const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

export const normalizeText = (value) => String(value).normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('es');

export function selectRows(rows, { query = '', column = null, direction = 1, numeric = false } = {}) {
    const needle = normalizeText(query.trim());
    const selected = rows.filter((row) => normalizeText(row.text).includes(needle));
    if (column === null) return selected;
    return selected.sort((a, b) => {
        const left = a.values[column] ?? '';
        const right = b.values[column] ?? '';
        const difference = numeric
            ? Number(String(left).replaceAll(',', '')) - Number(String(right).replaceAll(',', ''))
            : collator.compare(left, right);
        return (difference || 0) * direction || a.index - b.index;
    });
}
