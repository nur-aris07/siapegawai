/* Admin workspace v1: no inline events/HTML; Yajra server-side at the same URL. */
const menuButton = document.getElementById('admin-menu-toggle');
const menuPanel = document.getElementById('admin-primary-navigation');
if (menuButton && menuPanel) {
    const setOpen = (open) => {
        menuPanel.classList.toggle('is-open', open);
        menuButton.setAttribute('aria-expanded', String(open));
        menuButton.setAttribute('aria-label', open ? 'Tutup navigasi admin' : 'Buka navigasi admin');
    };
    menuButton.addEventListener('click', () => setOpen(menuButton.getAttribute('aria-expanded') !== 'true'));
    menuPanel.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') setOpen(false); });
}

const table = document.querySelector('[data-questions-table]');
if (table) {
    const rows = document.getElementById('questions-rows');
    const search = document.getElementById('questions-search');
    const subject = document.getElementById('questions-subject');
    const status = document.getElementById('questions-status');
    const info = document.getElementById('questions-info');
    const prev = document.getElementById('questions-prev');
    const next = document.getElementById('questions-next');
    const size = 10;
    let offset = 0;
    let count = 0;
    let draw = 0;
    let debounce;
    let controller;

    const td = (tr, value) => {
        const cell = document.createElement('td');
        cell.textContent = value == null ? '—' : String(value);
        tr.append(cell);
        return cell;
    };
    function placeholder(message) {
        rows.replaceChildren();
        const tr = rows.insertRow();
        td(tr, message).colSpan = 6;
    }
    function trustedSameOriginPath(raw) {
        if (typeof raw !== 'string') return null;
        try {
            const url = new URL(raw, window.location.origin);
            if (url.origin === window.location.origin && /^https?:$/.test(url.protocol) && url.pathname.startsWith('/admin/soal/')) return url.href;
        } catch (_) { /* invalid URL */ }
        return null;
    }
    function dateInJakarta(value) {
        if (!value) return '—';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return String(value).slice(0,10);
        return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'Asia/Jakarta' }).format(d);
    }
    async function load() {
        const current = ++draw;
        controller?.abort();
        controller = new AbortController();
        placeholder('Memuat soal…');
        info.textContent = 'Memuat…';
        const url = new URL(table.dataset.url, window.location.origin);
        url.searchParams.set('draw', String(current));
        url.searchParams.set('start', String(offset));
        url.searchParams.set('length', String(size));
        url.searchParams.set('search[value]', search.value.trim());
        url.searchParams.set('search[regex]', 'false');
        url.searchParams.set('status', status.value);
        url.searchParams.set('subject', subject.value);
        const columns = [
            ['code','questions.code'], ['subject_code','subjects.code'], ['summary','summary'],
            ['status','questions.status'], ['created_at','questions.created_at'], ['detail_url','detail_url'],
        ];
        columns.forEach(([data, name], i) => {
            url.searchParams.set(`columns[${i}][data]`, data);
            url.searchParams.set(`columns[${i}][name]`, name);
            url.searchParams.set(`columns[${i}][searchable]`, String(i !== 2 && i < 5));
            url.searchParams.set(`columns[${i}][orderable]`, String(i < 5 && i !== 2));
            url.searchParams.set(`columns[${i}][search][value]`, '');
        });
        url.searchParams.set('order[0][column]', '4');
        url.searchParams.set('order[0][dir]', 'desc');
        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin', signal: controller.signal,
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const json = await response.json();
            if (current !== draw) return;
            if (!Array.isArray(json.data)) throw new Error('Unexpected response');
            count = Number(json.recordsFiltered || 0);
            rows.replaceChildren();
            json.data.forEach(q => {
                const tr = document.createElement('tr');
                td(tr, q.code);
                td(tr, [q.subject_code, q.topic_name].filter(Boolean).join(' · '));
                td(tr, q.summary);
                const statusCell = document.createElement('td');
                const tag = document.createElement('span');
                const state = ['draft','published','archived'].includes(q.status) ? q.status : '';
                tag.className = `admin-v1-status ${state}`;
                const activeVersion = Number(q.active_version_number);
                tag.textContent = state === 'published' && Number.isInteger(activeVersion) && activeVersion > 0
                    ? `Terbit v${activeVersion}`
                    : ({ draft:'Draft', published:'Terbit', archived:'Arsip' })[state] || '—';
                statusCell.append(tag);
                const pendingVersion = Number(q.pending_draft_version_number);
                if (state === 'published' && Number.isInteger(pendingVersion) && pendingVersion > 0) {
                    const draftTag = document.createElement('span');
                    draftTag.className = 'admin-v1-status draft';
                    draftTag.textContent = `Revisi draft v${pendingVersion}`;
                    statusCell.append(draftTag);
                }
                tr.append(statusCell);
                td(tr, dateInJakarta(q.created_at));
                const action = document.createElement('td');
                const href = trustedSameOriginPath(q.detail_url);
                if (href) { const link = document.createElement('a'); link.href = href; link.textContent = 'Kelola'; action.append(link); }
                else action.textContent = '—';
                tr.append(action); rows.append(tr);
            });
            if (!json.data.length) placeholder('Belum ada soal yang sesuai filter. Tambah soal baru atau ubah pencarian.');
            info.textContent = `${count ? offset + 1 : 0}–${offset + json.data.length} dari ${count} soal`;
            prev.disabled = offset <= 0;
            next.disabled = offset + json.data.length >= count;
        } catch (error) {
            if (controller.signal.aborted || current !== draw) return;
            placeholder('Gagal memuat soal. Klik Muat ulang.');
            info.textContent = 'Tidak dapat memuat data';
            window.Siapegawai?.notify?.('error', 'Daftar soal gagal dimuat.');
        }
    }
    const resetAndLoad = () => { offset = 0; load(); };
    search?.addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(resetAndLoad, 300); });
    subject?.addEventListener('change', resetAndLoad);
    status?.addEventListener('change', resetAndLoad);
    document.getElementById('questions-refresh')?.addEventListener('click', load);
    prev?.addEventListener('click', () => { offset = Math.max(0, offset - size); load(); });
    next?.addEventListener('click', () => { if (offset + size < count) { offset += size; load(); } });
    load();
}
