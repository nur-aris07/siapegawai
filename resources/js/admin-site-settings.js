/* SITE-SETTINGS-01 C-06: Yajra DataTables and native modal without full-page navigation. */
const panel = document.querySelector('[data-site-settings-table]');
if (panel) {
    const body = document.getElementById('site-settings-rows');
    const search = document.getElementById('site-settings-search');
    const sizeSelect = document.getElementById('site-settings-page-size');
    const info = document.getElementById('site-settings-info');
    const prev = document.getElementById('site-settings-prev');
    const next = document.getElementById('site-settings-next');
    const columnKeys = ['label', 'key', 'value', 'kind'];
    let offset = 0, total = 0, sequence = 0, activeController, debounce;
    let orderColumn = 0, orderDir = 'asc';
    const addCell = (row, value, heading = false) => {
        const cell = document.createElement(heading ? 'th' : 'td');
        cell.textContent = value === '' || value == null ? '—' : String(value);
        if (heading) cell.scope = 'row';
        row.append(cell);
        return cell;
    };
    const placeholder = (message) => {
        body.replaceChildren();
        const tr = body.insertRow();
        addCell(tr, message).colSpan = 4;
    };
    const safeKey = (key) => typeof key === 'string' && (
        ['site_name', 'tagline', 'support_email', 'support_url', 'footer_note', 'social_instagram', 'social_youtube', 'social_tiktok'].includes(key)
        || /^custom_[a-z][a-z0-9_]{1,59}$/.test(key)
    );
    async function load() {
        const current = ++sequence;
        activeController?.abort();
        activeController = new AbortController();
        const count = Number(sizeSelect?.value || 10);
        const url = new URL(panel.dataset.url, location.origin);
        url.searchParams.set('draw', String(current));
        url.searchParams.set('start', String(offset));
        url.searchParams.set('length', String(count));
        url.searchParams.set('search[value]', (search?.value || '').trim().slice(0, 90));
        url.searchParams.set('search[regex]', 'false');
        columnKeys.forEach((name, i) => {
            url.searchParams.set(`columns[${i}][data]`, name);
            url.searchParams.set(`columns[${i}][name]`, name);
            url.searchParams.set(`columns[${i}][searchable]`, String(i < 3));
            url.searchParams.set(`columns[${i}][orderable]`, String(i < 3));
            url.searchParams.set(`columns[${i}][search][value]`, '');
        });
        url.searchParams.set('order[0][column]', String(orderColumn));
        url.searchParams.set('order[0][dir]', orderDir);
        info.textContent = 'Memuat…';
        placeholder('Memuat pengaturan…');
        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                credentials: 'same-origin', signal: activeController.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const json = await response.json();
            if (current !== sequence) return;
            if (!Array.isArray(json.data)) throw new Error('Format tabel tidak valid');
            total = Number(json.recordsFiltered || 0);
            body.replaceChildren();
            json.data.forEach(item => {
                const tr = document.createElement('tr');
                addCell(tr, item.label, true);
                addCell(tr, item.key).className = 'site-settings-key';
                addCell(tr, item.value).className = 'site-settings-value';
                const action = document.createElement('td');
                const group = document.createElement('div');
                group.className = 'site-settings-row-actions';
                if (safeKey(item.key)) {
                    const link = document.createElement('a');
                    const editUrl = new URL(panel.dataset.url, location.origin);
                    editUrl.searchParams.set('setting', item.key);
                    link.href = editUrl.pathname + editUrl.search;
                    link.textContent = 'Edit';
                    link.dataset.siteSettingsOpenEditor = '';
                    group.append(link);
                }
                const del = document.createElement('button');
                del.type = 'button';
                del.disabled = true;
                del.textContent = 'Hapus';
                del.title = 'Penghapusan pengaturan tidak tersedia';
                del.setAttribute('aria-label', `Hapus ${String(item.label || 'pengaturan')} tidak tersedia`);
                group.append(del);
                action.append(group); tr.append(action); body.append(tr);
            });
            if (!json.data.length) placeholder('Tidak ada pengaturan yang sesuai.');
            info.textContent = `${total ? offset + 1 : 0}–${offset + json.data.length} dari ${total} pengaturan`;
            prev.disabled = offset === 0;
            next.disabled = offset + json.data.length >= total;
        } catch (error) {
            if (activeController.signal.aborted || current !== sequence) return;
            placeholder('Daftar pengaturan gagal dimuat. Coba Muat ulang.');
            info.textContent = 'Tidak dapat memuat data';
            window.Siapegawai?.notify?.('error', 'Daftar pengaturan gagal dimuat.');
        }
    }
    const refresh = () => { offset = 0; load(); };
    search?.addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(refresh, 300); });
    sizeSelect?.addEventListener('change', refresh);
    document.getElementById('site-settings-refresh')?.addEventListener('click', load);
    prev?.addEventListener('click', () => { offset = Math.max(0, offset - Number(sizeSelect.value)); load(); });
    next?.addEventListener('click', () => { if (offset + Number(sizeSelect.value) < total) { offset += Number(sizeSelect.value); load(); } });
    document.querySelectorAll('[data-site-settings-sort]').forEach(button => button.addEventListener('click', () => {
        const index = columnKeys.indexOf(button.dataset.siteSettingsSort);
        if (index < 0 || index > 2) return;
        orderDir = orderColumn === index && orderDir === 'asc' ? 'desc' : 'asc';
        orderColumn = index;
        refresh();
    }));
    load();
}
// Progressive enhancement: fallback links still work when JS is disabled.
// Fetch the server-rendered editor to keep the version token fresh and prevent
// a navigation/reload. Do NOT set X-Requested-With: this route otherwise serves
// the Yajra JSON table instead of the Blade editor.
let editorFetchController = null;
let editorSequence = 0;
let editorTrigger = null;
const editorMessage = document.getElementById('site-settings-editor-message');
function announceEditorError(message) {
    if (editorMessage) {
        editorMessage.textContent = message;
        editorMessage.hidden = false;
    }
    window.Siapegawai?.notify?.('error', message);
}
function dismissEditor(dialog) {
    if (!dialog?.open) return;
    dialog.close();
}
function activateEditor(dialog, trigger, fromNavigation = false, editorUrl = null) {
    dialog.removeAttribute('open');
    dialog.removeAttribute('data-open-on-load');
    const prior = document.getElementById('site-settings-dialog');
    if (prior && prior !== dialog) prior.remove();
    if (!dialog.isConnected) document.body.append(dialog);
    editorTrigger = trigger;
    dialog.addEventListener('close', () => {
        if (dialog.open) return; // Ignore queued close event from SSR promotion.
        dialog.remove();
        if (fromNavigation) {
            const url = new URL(location.href);
            url.searchParams.delete('setting');
            url.searchParams.delete('create');
            history.replaceState(null, '', url.pathname + url.search + url.hash);
        }
        if (editorTrigger?.isConnected) editorTrigger.focus();
        else document.getElementById('site-settings-create')?.focus();
        editorTrigger = null;
    });
    dialog.querySelectorAll('.site-settings-close, .site-settings-editor-actions a').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            dismissEditor(dialog);
        });
    });
    // The URL stays unchanged while editing. Immediately before an actual form
    // submit, set the server fallback URL so Laravel's redirect-back on 422
    // reopens the same editor with validation errors and old input.
    if (editorUrl) dialog.querySelector('form')?.addEventListener('submit', () => {
        history.replaceState(null, '', editorUrl.pathname + editorUrl.search + editorUrl.hash);
    });
    dialog.showModal();
    dialog.querySelector('#site-settings-custom-label, #site-settings-custom-value, #site-settings-input-value')?.focus();
}

// Open the initial SSR deep link as a true modal, rather than leaving a
// non-modal <dialog open> at its document position.
const initiallyOpen = document.querySelector('#site-settings-dialog[data-open-on-load]');
if (initiallyOpen && typeof initiallyOpen.showModal === 'function') {
    initiallyOpen.close();
    // The listener ignores any queued close event while the new modal is open.
    activateEditor(initiallyOpen, null, true);
}

if (panel && typeof HTMLDialogElement !== 'undefined') {
    const workspace = panel.closest('.site-settings-panel');
    document.addEventListener('click', async event => {
        const link = event.target.closest('a[data-site-settings-open-editor]');
        if (!link || !workspace?.contains(link) || event.defaultPrevented || event.button !== 0
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const destination = new URL(link.href, location.href);
        if (destination.origin !== location.origin || destination.pathname !== location.pathname
            || (!destination.searchParams.has('create') && !destination.searchParams.has('setting'))) return;
        event.preventDefault();
        if (editorMessage) editorMessage.hidden = true;
        const old = document.getElementById('site-settings-dialog');
        if (old?.open) return;
        editorFetchController?.abort();
        editorFetchController = new AbortController();
        const request = ++editorSequence;
        link.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(destination.href, {
                credentials: 'same-origin', headers: { Accept: 'text/html' },
                signal: editorFetchController.signal,
            });
            if (!response.ok || response.redirected || new URL(response.url).pathname !== destination.pathname
                || !(response.headers.get('content-type') || '').includes('text/html')) {
                throw new Error('Editor tidak tersedia');
            }
            const markup = new DOMParser().parseFromString(await response.text(), 'text/html');
            const inertDialog = markup.querySelector('#site-settings-dialog[data-open-on-load]');
            if (!inertDialog) throw new Error('Editor tidak ditemukan');
            // DOMParser creates an inert document. Import into the active one;
            // showModal() on the parsed node itself can throw InvalidStateError.
            const dialog = document.importNode(inertDialog, true);
            if (typeof dialog.showModal !== 'function') throw new Error('Dialog tidak didukung');
            if (request !== editorSequence) return;
            activateEditor(dialog, link, false, destination);
        } catch (error) {
            if (error.name !== 'AbortError' && request === editorSequence)
                announceEditorError('Editor tidak dapat dibuka. Coba lagi atau muat ulang halaman.');
        } finally {
            link.removeAttribute('aria-busy');
        }
    });
}
