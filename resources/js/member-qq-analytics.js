/* QQ-ANALYTICS-01 C-03: accessible, CSP-safe table search/sort/pagination.
   Progressive enhancement: without JS every server-rendered row remains readable.
   No AJAX, DOM insertion from user-provided HTML, CDN or external dependency. */
const collator = new Intl.Collator('id-ID', { numeric: true, sensitivity: 'base' });

for (const grid of document.querySelectorAll('[data-qq-analytics-grid]')) {
    const table = grid.querySelector('[data-qq-analytics-table]');
    const tbody = table?.tBodies[0];
    const controls = grid.querySelector('.qq-analytics-controls');
    const footer = grid.querySelector('.qq-analytics-data-footer');
    const input = grid.querySelector('[data-qq-search]');
    const pageSize = grid.querySelector('[data-qq-page-size]');
    const status = grid.querySelector('[data-qq-page-status]');
    const previous = grid.querySelector('[data-qq-prev]');
    const next = grid.querySelector('[data-qq-next]');
    if (!table || !tbody || !controls || !footer || !input || !pageSize || !status || !previous || !next) continue;

    const rows = Array.from(tbody.rows);
    const headers = Array.from(table.tHead?.rows[0]?.cells || []);
    if (!headers.length) continue;
    let page = 0;
    let sortColumn = 0;
    let ascending = true;

    const noRows = document.createElement('tr');
    noRows.className = 'qq-analytics-no-results';
    const noRowsCell = document.createElement('td');
    noRowsCell.colSpan = headers.length;
    noRowsCell.textContent = 'Tidak ada baris yang cocok. Coba kata kunci lain.';
    noRows.append(noRowsCell);

    function render() {
        const query = input.value.trim().toLocaleLowerCase('id-ID');
        const limit = Number(pageSize.value);
        const matching = rows.filter(row => !query || row.textContent.toLocaleLowerCase('id-ID').includes(query));
        matching.sort((a, b) => {
            const numeric = headers[sortColumn].hasAttribute('data-qq-numeric');
            const first = a.cells[sortColumn];
            const second = b.cells[sortColumn];
            const orderA = first?.dataset.order ?? first?.textContent.trim() ?? '';
            const orderB = second?.dataset.order ?? second?.textContent.trim() ?? '';
            const compared = numeric
                ? Number(orderA) - Number(orderB)
                : collator.compare(orderA, orderB);
            return ascending ? compared : -compared;
        });
        const pages = Math.max(1, Math.ceil(matching.length / limit));
        page = Math.min(page, pages - 1);
        const start = page * limit;
        const selected = matching.slice(start, start + limit);
        tbody.replaceChildren(...(selected.length ? selected : [noRows]));
        status.textContent = matching.length
            ? `Menampilkan ${start + 1}–${start + selected.length} dari ${matching.length} baris` +
              (matching.length < rows.length ? ` (total ${rows.length})` : '')
            : `Tidak ditemukan dari ${rows.length} baris`;
        previous.disabled = page === 0;
        next.disabled = page >= pages - 1;
        headers.forEach((header, index) => {
            header.setAttribute('aria-sort', index !== sortColumn ? 'none' : (ascending ? 'ascending' : 'descending'));
            const button = header.querySelector('[data-qq-sort-button]');
            if (button) button.setAttribute('aria-label', `Urutkan ${button.dataset.label} ${index === sortColumn && ascending ? 'menurun' : 'menaik'}`);
        });
    }

    headers.forEach((header, index) => {
        const label = header.textContent.trim();
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'qq-analytics-sort-button';
        button.dataset.qqSortButton = '';
        button.dataset.label = label;
        button.textContent = `${label} ↕`;
        button.addEventListener('click', () => {
            if (sortColumn === index) ascending = !ascending;
            else { sortColumn = index; ascending = index === 0; }
            page = 0;
            render();
        });
        header.replaceChildren(button);
    });
    input.addEventListener('input', () => { page = 0; render(); });
    pageSize.addEventListener('change', () => { page = 0; render(); });
    previous.addEventListener('click', () => { if (page > 0) { page--; render(); } });
    next.addEventListener('click', () => { page++; render(); });
    controls.hidden = false;
    footer.hidden = false;
    render();
}
