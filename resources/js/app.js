/* Siapegawai frontend interactions. No inline handlers, styles or unsafe HTML. */
const toastHost = document.getElementById('notification-region');
const recentNotifications = new Map();
const notificationLabels = { success: 'Berhasil', info: 'Informasi', warning: 'Perhatian', error: 'Kesalahan' };
function notify(type, message) {
    if (!toastHost || !notificationLabels[type] || typeof message !== 'string') return;
    const content = message.trim().slice(0, 300);
    if (!content) return;
    const fingerprint = `${type}:${content}`;
    const lastAt = recentNotifications.get(fingerprint) || 0;
    if (Date.now() - lastAt < 3000) return;
    recentNotifications.set(fingerprint, Date.now());
    if (toastHost.children.length >= 3) toastHost.firstElementChild?.remove();
    const box = document.createElement('div');
    box.className = `ui-toast ui-toast-${type}`;
    box.setAttribute('role', type === 'error' ? 'alert' : 'status');
    const label = document.createElement('strong');
    label.textContent = notificationLabels[type];
    const text = document.createElement('p');
    text.textContent = content;
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'ui-toast-close';
    close.setAttribute('aria-label', 'Tutup notifikasi');
    close.textContent = '×';
    close.addEventListener('click', () => box.remove());
    box.append(label, text, close);
    toastHost.append(box);
    if (type !== 'error') window.setTimeout(() => box.remove(), 5200);
}
window.Siapegawai = Object.freeze({ notify });
document.querySelectorAll('[data-toast-message]').forEach(el => {
    notify(['success', 'info', 'warning', 'error'].includes(el.dataset.toastType) ? el.dataset.toastType : 'info', el.textContent || '');
});

// Public navigation; button controls only the public navigation menu.
const menuToggle = document.getElementById('menu-toggle');
const mainNav = document.getElementById('main-nav');
menuToggle?.addEventListener('click', () => {
    const next = menuToggle.getAttribute('aria-expanded') !== 'true';
    menuToggle.setAttribute('aria-expanded', String(next));
    menuToggle.setAttribute('aria-label', next ? 'Tutup menu navigasi' : 'Buka menu navigasi');
    mainNav?.classList.toggle('is-open', next);
});

// Bank sample: live search within the server-rendered page; category links remain GET forms.
const bankForm = document.getElementById('bank-filter-form');
if (bankForm) {
    const search = document.getElementById('bank-search');
    const results = document.getElementById('bank-results');
    const items = Array.from(results?.querySelectorAll('.bank-item') || []);
    const count = results?.querySelector('.bank-count');
    if (search && items.length && results) {
        const empty = document.createElement('div');
        empty.className = 'empty-search';
        empty.hidden = true;
        const heading = document.createElement('h2');
        heading.textContent = 'Tidak ada soal yang cocok';
        const detail = document.createElement('p');
        detail.textContent = 'Ubah kata kunci atau pilih kategori lain.';
        empty.append(heading, detail);
        results.append(empty);
        function updateSearch() {
            const query = search.value.trim().toLocaleLowerCase('id-ID');
            let shown = 0;
            items.forEach(item => {
                const matching = !query || item.textContent.toLocaleLowerCase('id-ID').includes(query);
                item.hidden = !matching;
                if (matching) shown++;
            });
            empty.hidden = shown > 0;
            if (count) count.textContent = `Menampilkan ${shown} dari ${items.length} contoh soal · ${shown} soal ditemukan`;
        }
        search.addEventListener('input', updateSearch);
    }
}

// Registration redirects to WhatsApp once per tab. It does NOT imply message sent.
const whatsapp = document.querySelector('[data-auto-wa]');
if (whatsapp) {
    const marker = `siapegawai-wa-autoredirect-${document.getElementById('registration-reference')?.textContent.trim() || 'registration'}`;
    try {
        if (!sessionStorage.getItem(marker)) {
            sessionStorage.setItem(marker, '1');
            window.location.assign(whatsapp.href);
        }
    } catch (_) { /* In restricted browsers, manual WA button still works. */ }
}
document.getElementById('copy-reference')?.addEventListener('click', async () => {
    const el = document.getElementById('registration-reference');
    if (!el) return;
    try { await navigator.clipboard.writeText(el.textContent.trim()); notify('success', 'Kode referensi disalin.'); }
    catch (_) { const range = document.createRange(); range.selectNodeContents(el); window.getSelection()?.removeAllRanges(); window.getSelection()?.addRange(range); notify('info', 'Salin kode yang telah dipilih.'); }
});

// Demo SKD: server owns score and expiry. Frontend owns temporary navigation only.
const exam = document.getElementById('demo-exam');
if (exam) {
    const questions = Array.from(exam.querySelectorAll('[data-question]'));
    const navigation = Array.from(exam.querySelectorAll('[data-nav-question]'));
    const timerDisplay = document.getElementById('demo-timer');
    const flags = new Set();
    const flagCheckbox = document.getElementById('demo-flag');
    let current = 0;
    let submitting = false;
    let expiryTimer;
    const prev = document.getElementById('demo-prev');
    const next = document.getElementById('demo-next');
    const answeredCount = () => questions.filter(q => Boolean(q.querySelector('input[type="radio"]:checked'))).length;
    function refresh() {
        const answered = answeredCount();
        for (const id of ['answered-count', 'exam-toolbar-count', 'sidebar-count']) {
            const element = document.getElementById(id);
            if (element) element.textContent = String(answered);
        }
        navigation.forEach((button, index) => {
            const answeredQuestion = Boolean(questions[index].querySelector('input[type="radio"]:checked'));
            button.classList.toggle('answered', answeredQuestion);
            button.classList.toggle('flagged', flags.has(index));
            button.classList.toggle('current', index === current);
            button.textContent = String(index + 1) + (flags.has(index) ? '*' : '');
            button.setAttribute('aria-label', `Soal ${index + 1}, ${answeredQuestion ? 'sudah dijawab' : 'belum dijawab'}${flags.has(index) ? ', ditandai' : ''}`);
        });
        questions.forEach(q => q.querySelectorAll('label.option').forEach(label => label.classList.toggle('selected', Boolean(label.querySelector('input:checked')))));
    }
    function show(index) {
        if (index < 0 || index >= questions.length) return;
        current = index;
        questions.forEach((question, i) => { question.hidden = i !== current; });
        navigation.forEach((button, i) => i === current ? button.setAttribute('aria-current', 'step') : button.removeAttribute('aria-current'));
        document.getElementById('current-question-count').textContent = String(current + 1);
        const topic = questions[current].dataset.category || '';
        const toolbar = document.getElementById('exam-toolbar-topic');
        if (toolbar) toolbar.textContent = `${topic} · Soal ${current + 1} dari ${questions.length}`;
        if (prev) prev.disabled = current === 0;
        if (next) next.disabled = current === questions.length - 1;
        if (flagCheckbox) flagCheckbox.checked = flags.has(current);
        const flagText = document.getElementById('demo-flag-text');
        if (flagText) flagText.textContent = flags.has(current) ? 'Hapus tanda tinjau' : 'Tandai untuk ditinjau';
        refresh();
        questions[current].querySelector('h2')?.focus({ preventScroll: true });
    }
    navigation.forEach((button, index) => button.addEventListener('click', () => show(index)));
    prev?.addEventListener('click', () => show(current - 1));
    next?.addEventListener('click', () => show(current + 1));
    flagCheckbox?.addEventListener('change', () => {
        flagCheckbox.checked ? flags.add(current) : flags.delete(current);
        refresh();
        document.getElementById('demo-flag-text').textContent = flags.has(current) ? 'Hapus tanda tinjau' : 'Tandai untuk ditinjau';
    });
    exam.addEventListener('change', refresh);
    const expiresAt = Number(timerDisplay?.dataset.expires || 0) * 1000;
    function tick() {
        const seconds = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
        if (timerDisplay) timerDisplay.textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
        document.getElementById('timer-container')?.classList.toggle('warning', seconds <= 60);
        if (seconds === 0 && !submitting) { submitting = true; window.clearInterval(expiryTimer); exam.submit(); }
    }
    exam.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        const answered = answeredCount();
        if (!window.confirm(`Kumpulkan latihan? ${answered} dari ${questions.length} soal sudah dijawab.`)) { event.preventDefault(); return; }
        submitting = true;
        window.clearInterval(expiryTimer);
    });
    show(0); tick();
    expiryTimer = window.setInterval(tick, 1000);
}

// Admin index remains one URL: Blade on normal GET, Yajra JSON on AJAX GET.
const regTable = document.querySelector('[data-registration-table]');
if (regTable) {
    const body = document.getElementById('registration-rows');
    const info = document.getElementById('registration-info');
    const search = document.getElementById('registration-search');
    const prev = document.getElementById('registration-prev');
    const next = document.getElementById('registration-next');
    let start = 0, shown = 0, total = 0, draw = 0, debounce;
    const length = 10;
    function cell(row, value) { const td = document.createElement('td'); td.textContent = String(value ?? '—'); row.append(td); return td; }
    async function load() {
        const requestId = ++draw;
        const params = new URLSearchParams({ draw: String(requestId), start: String(start), length: String(length), 'search[value]': search.value, 'search[regex]': 'false' });
        const columns = ['reference_code', 'applicant_name', 'email', 'status', 'submitted_at', 'detail_url'];
        columns.forEach((name, index) => {
            params.set(`columns[${index}][data]`, name);
            params.set(`columns[${index}][name]`, name);
            params.set(`columns[${index}][searchable]`, ['reference_code', 'applicant_name', 'email', 'status'].includes(name) ? 'true' : 'false');
            params.set(`columns[${index}][orderable]`, ['reference_code', 'status', 'submitted_at'].includes(name) ? 'true' : 'false');
        });
        params.set('order[0][column]', '4'); params.set('order[0][dir]', 'desc');
        body.replaceChildren(); cell(body.insertRow(), 'Memuat…').colSpan = 6;
        try {
            const response = await fetch(`${regTable.dataset.url}?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('Gagal memuat registrasi');
            const json = await response.json();
            if (Number(json.draw) !== draw) return;
            shown = json.data?.length || 0; total = Number(json.recordsFiltered || 0);
            body.replaceChildren();
            for (const record of json.data || []) {
                const row = document.createElement('tr');
                cell(row, record.reference_code); cell(row, record.applicant_name); cell(row, record.email); cell(row, record.status); cell(row, record.submitted_at);
                const action = document.createElement('td'); const link = document.createElement('a');
                // Reject absolute cross-origin destinations from unexpected JSON.
                try { const url = new URL(record.detail_url, window.location.origin); if (url.origin === window.location.origin && /^https?:$/.test(url.protocol)) { link.href = url.href; } }
                catch (_) { /* No link will be set. */ }
                link.textContent = 'Detail'; action.append(link); row.append(action); body.append(row);
            }
            if (!shown) cell(body.insertRow(), 'Belum ada data yang cocok').colSpan = 6;
            info.textContent = `${total ? start + 1 : 0}–${start + shown} dari ${total} registrasi`;
            prev.disabled = start === 0; next.disabled = start + shown >= total;
        } catch (_) {
            if (requestId !== draw) return;
            body.replaceChildren(); cell(body.insertRow(), 'Gagal memuat. Gunakan tombol Muat ulang.').colSpan = 6;
            info.textContent = 'Gagal memuat registrasi';
            notify('error', 'Daftar registrasi tidak dapat dimuat. Coba lagi.');
        }
    }
    search?.addEventListener('input', () => { window.clearTimeout(debounce); debounce = window.setTimeout(() => { start = 0; load(); }, 350); });
    document.getElementById('registration-refresh')?.addEventListener('click', load);
    prev?.addEventListener('click', () => { start = Math.max(0, start - length); load(); });
    next?.addEventListener('click', () => { if (start + shown < total) { start += length; load(); } });
    load();
}
