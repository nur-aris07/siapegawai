/* Participant directory: same URL serves Blade and Yajra JSON, no dynamic HTML insertion. */
const panel = document.querySelector('[data-participants-table]');
if (panel) {
    const rows = document.getElementById('participants-rows');
    const search = document.getElementById('participants-search');
    const status = document.getElementById('participants-status');
    const info = document.getElementById('participants-info');
    const prev = document.getElementById('participants-prev');
    const next = document.getElementById('participants-next');
    let offset = 0;
    let total = 0;
    let sequence = 0;
    let controller;
    let searchDelay;
    const size = 10;
    const addTextCell = (row, value) => {
        const cell = document.createElement('td');
        cell.textContent = value == null ? '—' : String(value);
        row.append(cell);
        return cell;
    };
    const empty = (message) => {
        rows.replaceChildren();
        const row = rows.insertRow();
        addTextCell(row, message).colSpan = 5;
    };
    const safeHref = (raw) => {
        if (typeof raw !== 'string') return null;
        try {
            const url = new URL(raw, location.origin);
            if (url.origin === location.origin && /^\/admin\/peserta\/\d+$/.test(url.pathname)) return url.href;
        } catch (_) { /* malformed URL */ }
        return null;
    };
    const statusLabels = { active: 'Aktif', pending: 'Menunggu', suspended: 'Ditangguhkan', rejected: 'Ditolak' };
    const dateLabel = (raw) => {
        const date = new Date(raw);
        return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'Asia/Jakarta' }).format(date);
    };
    async function load() {
        const requestNumber = ++sequence;
        controller?.abort();
        controller = new AbortController();
        empty('Memuat peserta…');
        info.textContent = 'Memuat…';
        const url = new URL(panel.dataset.url, location.origin);
        url.searchParams.set('draw', String(requestNumber));
        url.searchParams.set('start', String(offset));
        url.searchParams.set('length', String(size));
        url.searchParams.set('status', status.value);
        url.searchParams.set('search[value]', search.value.trim());
        url.searchParams.set('search[regex]', 'false');
        [['name', 'users.name'], ['email', 'users.email'], ['status', 'users.status'], ['created_at', 'users.created_at'], ['detail_url', 'detail_url']]
            .forEach(([field, name], i) => {
                url.searchParams.set(`columns[${i}][data]`, field);
                url.searchParams.set(`columns[${i}][name]`, name);
                url.searchParams.set(`columns[${i}][searchable]`, String(i < 3));
                url.searchParams.set(`columns[${i}][orderable]`, String(i < 4));
                url.searchParams.set(`columns[${i}][search][value]`, '');
            });
        url.searchParams.set('order[0][column]', '3');
        url.searchParams.set('order[0][dir]', 'desc');
        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                credentials: 'same-origin', signal: controller.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const result = await response.json();
            if (sequence !== requestNumber) return;
            if (!Array.isArray(result.data)) throw new Error('JSON tak valid');
            total = Number(result.recordsFiltered || 0);
            rows.replaceChildren();
            result.data.forEach((participant) => {
                const tr = document.createElement('tr');
                addTextCell(tr, participant.name);
                addTextCell(tr, participant.email);
                addTextCell(tr, statusLabels[participant.status] || '—');
                addTextCell(tr, dateLabel(participant.created_at));
                const action = document.createElement('td');
                const href = safeHref(participant.detail_url);
                if (href) {
                    const a = document.createElement('a');
                    a.href = href;
                    a.textContent = 'Lihat detail';
                    action.append(a);
                } else action.textContent = '—';
                tr.append(action);
                rows.append(tr);
            });
            if (result.data.length === 0) empty('Tidak ada peserta yang sesuai pencarian.');
            info.textContent = `${total ? offset + 1 : 0}–${offset + result.data.length} dari ${total} akun`;
            prev.disabled = offset <= 0;
            next.disabled = offset + result.data.length >= total;
        } catch (err) {
            if (controller.signal.aborted || sequence !== requestNumber) return;
            empty('Gagal memuat data. Coba Muat ulang.');
            info.textContent = 'Gagal memuat peserta';
            window.Siapegawai?.notify?.('error', 'Daftar peserta gagal dimuat.');
        }
    }
    const reset = () => { offset = 0; load(); };
    search.addEventListener('input', () => { clearTimeout(searchDelay); searchDelay = setTimeout(reset, 300); });
    status.addEventListener('change', reset);
    document.getElementById('participants-refresh')?.addEventListener('click', load);
    prev.addEventListener('click', () => { offset = Math.max(0, offset - size); load(); });
    next.addEventListener('click', () => { if (offset + size < total) { offset += size; load(); } });
    load();
}
