/* S5.4: reliable autosave while the tab stays open; server remains authoritative.
 * One serial request per question prevents a stale in-flight response overwriting
 * a newer answer from the same tab. Failed writes remain pending for retry.
 */
const root = document.getElementById('member-exam');
if (root) {
    const questions = Array.from(root.querySelectorAll('[data-member-question]'));
    const nav = Array.from(root.querySelectorAll('[data-member-nav]'));
    const timer = document.getElementById('member-countdown');
    const status = document.getElementById('member-save-status');
    const retryButton = document.getElementById('member-retry-save');
    const flag = document.getElementById('member-flag');
    const submitForm = document.getElementById('member-submit-form');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const saveUrl = root.dataset.saveUrl;
    const resultUrl = root.dataset.resultUrl;
    const expires = Number(timer?.dataset.expires || 0) * 1000;
    const serverNow = Number(timer?.dataset.serverNow || 0) * 1000;
    const serverOffset = serverNow ? serverNow - Date.now() : 0;
    const states = questions.map(() => ({ revision: 0, saved: 0, running: null, retry: null, failures: 0, hardError: false }));
    let current = 0;
    let closed = false;
    let expired = false;
    let submitting = false;
    let notifiedFailure = false;

    const online = () => navigator.onLine !== false;
    const isDirty = state => state.saved < state.revision;
    const anyDirty = () => states.some(isDirty);
    const questionAnswered = q => Boolean(q.querySelector('input[type="radio"]:checked'));
    const setStatus = text => { if (status) status.textContent = text; };

    function refresh() {
        const answered = questions.filter(questionAnswered).length;
        ['member-answered-label', 'member-answered-side'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = String(answered);
        });
        nav.forEach((button, i) => {
            const flagged = button.dataset.flagged === '1';
            button.classList.toggle('answered', questionAnswered(questions[i]));
            button.classList.toggle('flagged', flagged);
            button.classList.toggle('current', i === current);
            button.textContent = `${i+1}${flagged ? '*' : ''}`;
            button.setAttribute('aria-label', `Soal ${i+1}${questionAnswered(questions[i])?' sudah dijawab':''}${flagged?' ditandai':''}`);
        });
        questions.forEach(q => q.querySelectorAll('label.member-option').forEach(label =>
            label.classList.toggle('selected', Boolean(label.querySelector('input:checked')))
        ));
    }
    function show(i) {
        if (i < 0 || i >= questions.length) return;
        current = i;
        questions.forEach((q, index) => { q.hidden = index !== i; });
        nav.forEach((n, index) => index === i ? n.setAttribute('aria-current','step') : n.removeAttribute('aria-current'));
        const previous = document.getElementById('member-prev');
        const next = document.getElementById('member-next');
        if (previous) previous.disabled = i === 0;
        if (next) next.disabled = i === questions.length - 1;
        if (flag) flag.checked = nav[i].dataset.flagged === '1';
        const topic = document.getElementById('member-topic');
        if (topic) topic.textContent = `Soal ${i+1} dari ${questions.length}`;
        refresh();
    }
    function updateSaveStatus() {
        if (closed) return;
        if (expired) { setStatus(online() ? 'Waktu habis · membuka hasil…' : 'Waktu habis · sambungkan internet untuk melihat hasil'); return; }
        const pending = anyDirty();
        if (retryButton) retryButton.hidden = !pending;
        if (!pending) { setStatus('Semua jawaban tersimpan'); notifiedFailure = false; return; }
        if (!online()) { setStatus('Offline · perubahan belum tersimpan, jangan tutup halaman'); return; }
        if (states.some(s => s.hardError)) { setStatus('Sesi perlu diperbarui · jawaban belum tersimpan'); return; }
        if (states.some(s => s.failures > 0)) { setStatus('Koneksi bermasalah · menyimpan ulang otomatis…'); return; }
        setStatus('Menyimpan jawaban…');
    }
    function retryLater(i) {
        const state = states[i];
        if (closed || expired || state.hardError || state.retry !== null) return;
        const wait = Math.min(30000, Math.pow(2, Math.min(5, state.failures)) * 1000);
        state.retry = window.setTimeout(() => { state.retry = null; if (online()) void send(i); }, wait);
    }
    async function send(i) {
        const state = states[i];
        if (closed || expired || state.hardError || !isDirty(state) || !online() || !csrf || !saveUrl) {
            updateSaveStatus(); return;
        }
        if (state.running) return state.running;
        if (state.retry !== null) { window.clearTimeout(state.retry); state.retry = null; }
        state.running = (async () => {
            while (!closed && !expired && online() && !state.hardError && isDirty(state)) {
                const sendingRevision = state.revision;
                const q = questions[i];
                const body = {
                    item_id: Number(q.dataset.itemId),
                    answer: q.querySelector('input[type="radio"]:checked')?.value || null,
                    flagged: nav[i].dataset.flagged === '1',
                };
                const controller = new AbortController();
                const timeout = window.setTimeout(() => controller.abort(), 12000);
                try {
                    const response = await fetch(saveUrl, {
                        method: 'PATCH', credentials:'same-origin',
                        headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
                        body: JSON.stringify(body), signal: controller.signal,
                    });
                    const data = await response.json().catch(() => ({}));
                    if (response.status === 409) {
                        closed = true;
                        if (online()) window.location.assign(data.result_url || resultUrl);
                        return;
                    }
                    if ([401, 403, 419, 422].includes(response.status)) {
                        state.hardError = true;
                        throw new Error(data.message || 'Sesi perlu diperbarui untuk menyimpan jawaban.');
                    }
                    if (!response.ok || data.saved !== true) throw new Error(data.message || 'Jawaban belum tersimpan.');
                    state.saved = Math.max(state.saved, sendingRevision);
                    state.failures = 0;
                } catch (error) {
                    state.failures += 1;
                    if (!notifiedFailure) {
                        window.Siapegawai?.notify?.('error','Jawaban belum tersimpan. Kami akan mencoba lagi saat koneksi tersedia.');
                        notifiedFailure = true;
                    }
                    retryLater(i);
                    break;
                } finally {
                    window.clearTimeout(timeout);
                    updateSaveStatus();
                }
            }
        })();
        try { await state.running; }
        finally { state.running = null; updateSaveStatus(); }
    }
    function change(i) {
        if (closed || expired) return;
        states[i].revision += 1;
        states[i].hardError = false;
        states[i].failures = 0;
        if (states[i].retry !== null) { window.clearTimeout(states[i].retry); states[i].retry = null; }
        refresh(); updateSaveStatus(); void send(i);
    }
    function syncAll() {
        if (closed || expired) return;
        states.forEach((state, i) => {
            if (isDirty(state)) { state.hardError = false; void send(i); }
        });
        updateSaveStatus();
    }
    async function flushPending() {
        syncAll();
        // Never submit if the user still has unsent answers. Bound the wait to
        // prevent a stalled request from blocking the interface indefinitely.
        const deadline = Date.now() + 16000;
        while (anyDirty() && online() && !closed && !expired && Date.now() < deadline) {
            await new Promise(resolve => window.setTimeout(resolve, 250));
        }
        return !anyDirty();
    }
    function openResultAfterDeadline() {
        if (expired && online()) { closed = true; window.location.assign(resultUrl); }
        else updateSaveStatus();
    }
    nav.forEach((button,i) => button.addEventListener('click', () => show(i)));
    document.getElementById('member-prev')?.addEventListener('click', () => show(current-1));
    document.getElementById('member-next')?.addEventListener('click', () => show(current+1));
    flag?.addEventListener('change', () => { nav[current].dataset.flagged = flag.checked?'1':'0'; change(current); });
    questions.forEach((q,i) => q.addEventListener('change', () => change(i)));
    retryButton?.addEventListener('click', syncAll);
    window.addEventListener('online', () => { if (expired) openResultAfterDeadline(); else syncAll(); });
    window.addEventListener('offline', updateSaveStatus);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { tick(); if (!expired) syncAll(); } });
    submitForm?.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting || closed || expired) return;
        const answered = questions.filter(questionAnswered).length;
        if (!window.confirm(`Kumpulkan jawaban? ${answered} dari ${questions.length} soal dijawab.`)) return;
        if (!online()) { setStatus('Offline · hubungkan internet sebelum mengumpulkan'); return; }
        submitting = true;
        const submitButton = submitForm.querySelector('[type="submit"]');
        if (submitButton) submitButton.disabled = true;
        const saved = await flushPending();
        if (saved && !closed && !expired) { closed = true; submitForm.submit(); return; }
        submitting = false;
        if (submitButton) submitButton.disabled = false;
        updateSaveStatus();
        window.Siapegawai?.notify?.('error','Masih ada jawaban yang belum terkirim. Periksa koneksi lalu coba kumpulkan lagi.');
    });
    function tick() {
        const seconds = Math.max(0, Math.ceil((expires - (Date.now() + serverOffset))/1000));
        if (timer) timer.textContent = `${String(Math.floor(seconds/60)).padStart(2,'0')}:${String(seconds%60).padStart(2,'0')}`;
        if (seconds === 0 && !expired && !closed) {
            expired = true;
            states.forEach(state => { if (state.retry !== null) { window.clearTimeout(state.retry); state.retry = null; } });
            setStatus('Waktu habis · membuka hasil…');
            window.setTimeout(openResultAfterDeadline, 800);
        }
    }
    window.addEventListener('beforeunload', event => {
        if (!closed && !expired && anyDirty()) { event.preventDefault(); event.returnValue = ''; }
    });
    show(0); updateSaveStatus(); tick(); window.setInterval(tick, 1000);
}
