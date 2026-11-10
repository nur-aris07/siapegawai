/* CLS-MON: progressive enhancement of the existing server-rendered admin table.
 * Yajra JSON uses the SAME URL as the Blade page; user data is always text-only. */
const form = document.querySelector('[data-monitoring-table]');
if (form) {
    const rows = document.getElementById('monitoring-rows');
    const search = document.getElementById('monitoring-search');
    const status = document.getElementById('monitoring-status');
    const reset = document.getElementById('monitoring-reset');
    const prev = document.getElementById('monitoring-prev');
    const next = document.getElementById('monitoring-next');
    const info = document.getElementById('monitoring-info');
    const ajaxFooter = document.getElementById('monitoring-ajax-foot');
    const plainPagination = document.getElementById('monitoring-plain-pagination');
    const publishedCount = Number(document.querySelector('[data-monitoring-published]')?.dataset.monitoringPublished || 0);
    let offset = 0;
    let total = 0;
    let sequence = 0;
    let controller;
    let debounce;
    const pageSize = 15;
    const base = new URL(form.dataset.url, window.location.origin);
    const cell = (row, value) => {
        const td = document.createElement('td');
        td.textContent = value === null || value === undefined ? '—' : String(value);
        row.append(td);
        return td;
    };
    const empty = (message) => {
        rows.replaceChildren();
        const tr = document.createElement('tr');
        cell(tr, message).colSpan = 8;
        rows.append(tr);
    };
    const percent = (value) => value === null || value === undefined || value === ''
        ? '—' : `${Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`;
    const safeDetail = (raw) => {
        if (typeof raw !== 'string') return null;
        try {
            const href = new URL(raw, base.origin);
            if (href.origin !== base.origin || href.search || href.hash) return null;
            if (!new RegExp(`^${base.pathname.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/peserta/\\d+$`).test(href.pathname)) return null;
            return href.href;
        } catch (_) { return null; }
    };
    const setControls = (loaded = 0) => {
        info.textContent = `${total ? offset + 1 : 0}–${offset + loaded} dari ${total} peserta`;
        prev.disabled = offset <= 0;
        next.disabled = offset + loaded >= total;
    };
    async function load() {
        const id = ++sequence;
        controller?.abort();
        controller = new AbortController();
        empty('Memuat monitoring peserta…');
        info.textContent = 'Memuat data…';
        prev.disabled = true;
        next.disabled = true;
        const url = new URL(base.href);
        url.searchParams.set('draw', String(id));
        url.searchParams.set('start', String(offset));
        url.searchParams.set('length', String(pageSize));
        url.searchParams.set('status', status.value);
        url.searchParams.set('search[value]', search.value.trim());
        url.searchParams.set('search[regex]', 'false');
        // Explicit allowlist: only the display name is sortable; all other fields are safe display data.
        const names = ['name', 'enrollment_status', 'account_status', 'completed_count', 'finished_attempts', 'latest_score', 'best_score', 'detail_url'];
        names.forEach((name, index) => {
            url.searchParams.set(`columns[${index}][data]`, name);
            url.searchParams.set(`columns[${index}][name]`, name);
            url.searchParams.set(`columns[${index}][searchable]`, 'false');
            url.searchParams.set(`columns[${index}][orderable]`, String(index === 0));
            url.searchParams.set(`columns[${index}][search][value]`, '');
        });
        url.searchParams.set('order[0][column]', '0');
        url.searchParams.set('order[0][dir]', 'asc');
        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                credentials: 'same-origin', signal: controller.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const result = await response.json();
            if (sequence !== id) return;
            if (!Array.isArray(result.data)) throw new Error('Format respons tidak sesuai');
            total = Number(result.recordsFiltered || 0);
            if (offset >= total && offset > 0) {
                offset = Math.max(0, Math.floor((total - 1) / pageSize) * pageSize);
                return load();
            }
            rows.replaceChildren();
            result.data.forEach((entry) => {
                const tr = document.createElement('tr');
                const identity = document.createElement('td');
                const strong = document.createElement('strong');
                strong.textContent = entry.name ?? '—';
                const email = document.createElement('p');
                email.className = 'admin-v1-muted';
                email.textContent = entry.email ?? '—';
                identity.append(strong, email);
                tr.append(identity);
                cell(tr, entry.enrollment_status === 'active' ? 'Aktif' : entry.enrollment_status === 'revoked' ? 'Dicabut' : '—');
                cell(tr, entry.account_status);
                cell(tr, `${Number(entry.completed_count || 0)}/${publishedCount} (${Number(entry.progress_percent || 0)}%)`);
                cell(tr, Number(entry.finished_attempts || 0));
                cell(tr, percent(entry.latest_score));
                cell(tr, percent(entry.best_score));
                const action = document.createElement('td');
                const href = safeDetail(entry.detail_url);
                if (href) {
                    const link = document.createElement('a');
                    link.href = href;
                    link.textContent = 'Lihat';
                    action.append(link);
                } else action.textContent = '—';
                tr.append(action);
                rows.append(tr);
            });
            if (result.data.length === 0) empty('Tidak ada peserta yang sesuai filter.');
            setControls(result.data.length);
        } catch (error) {
            if (controller.signal.aborted || sequence !== id) return;
            empty('Gagal memuat daftar. Coba ulangi pencarian.');
            info.textContent = 'Data monitoring gagal dimuat';
            window.Siapegawai?.notify?.('error', 'Monitoring peserta gagal dimuat.');
        }
    }
    function refresh() { offset = 0; load(); }
    form.addEventListener('submit', (event) => { event.preventDefault(); refresh(); });
    search.addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(refresh, 300); });
    status.addEventListener('change', refresh);
    reset.addEventListener('click', (event) => { event.preventDefault(); search.value = ''; status.value = 'all'; refresh(); });
    prev.addEventListener('click', () => { offset = Math.max(0, offset - pageSize); load(); });
    next.addEventListener('click', () => { if (offset + pageSize < total) { offset += pageSize; load(); } });
    // Keep the server-rendered table visible until the first response; GET fallback stays available.
    ajaxFooter.hidden = false;
    plainPagination.hidden = true;
    load();
}
