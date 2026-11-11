// QQ-CORE-02 progressive enhancement. Server alone authorizes answers, timeouts, quota and next-question issuance.
const root = document.getElementById('qq-exam');
if (root) {
    const phase = root.dataset.phase;
    const serverOffset = Date.now() - Number(root.dataset.serverNowMs);
    const serverNow = () => Date.now() - serverOffset;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const form = document.getElementById('qq-answer-form');
    const countdown = document.getElementById('qq-countdown');
    let pending = false;
    let retryAt = 0;
    const post = async (url, fields) => {
        const body = new FormData();
        for (const [name, value] of Object.entries(fields)) body.append(name, value);
        const response = await fetch(url, {method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'text/html'},credentials:'same-origin',body,redirect:'follow'});
        if (!response.ok) throw new Error('Permintaan gagal ('+response.status+').');
        window.location.assign(response.url);
    };
    if (phase === 'question' && form) {
        const status = document.getElementById('qq-save-status');
        const button = document.getElementById('qq-answer-submit');
        const choices = [...form.querySelectorAll('[name="answer"]')];
        const submit = async (choice) => {
            if (pending || Date.now() < retryAt) return;
            pending = true;
            button.disabled = true;
            // Freeze visible selection while the server locks the first submitted answer.
            // Otherwise a slow request could display B while the server persisted A.
            choices.forEach(input => { input.disabled = true; });
            status.textContent = 'Mengunci jawaban di server…';
            try { await post(root.dataset.answerUrl, {item_id:form.querySelector('[name=item_id]').value,answer:choice}); }
            catch (error) {
                pending = false; retryAt = Date.now()+3000; button.disabled = false;
                choices.forEach(input => { input.disabled = false; });
                status.textContent = error.message+' Coba kembali atau muat ulang untuk pemulihan.';
            }
        };
        form.addEventListener('submit', (event) => {event.preventDefault();const selected=form.querySelector('[name="answer"]:checked');if (selected) submit(selected.value);});
        // Product contract: choosing an option immediately locks it (no second confirmation).
        form.querySelectorAll('[name="answer"]').forEach(input => {
            input.addEventListener('change', () => { if (input.checked) submit(input.value); });
        });
        const tick = () => {
            if (pending) return;
            const left = Math.ceil((Number(root.dataset.expiresMs)-serverNow())/1000);
            countdown.textContent = left > 0 ? `${left} detik` : 'Waktu habis';
            if (left <= 0) submit('');
        };
        tick(); setInterval(tick, 200);
    }
    if (phase === 'feedback') {
        const form = document.getElementById('qq-next-form');
        const button = document.getElementById('qq-next-button');
        const status = document.getElementById('qq-feedback-status');
        let visibleSince = document.hidden ? null : Date.now();
        button.disabled = true;
        const advance = async () => {
            if (pending || Date.now() < retryAt) return;
            pending = true;
            status.textContent = 'Memuat soal berikutnya…';
            try { await post(root.dataset.nextUrl, {}); }
            catch (error) { pending = false; retryAt = Date.now()+3000; button.disabled = false; status.textContent = error.message+' Gunakan tombol untuk mencoba kembali.'; }
        };
        const refresh = () => {
            if (document.hidden) { visibleSince = null; return; }
            if (visibleSince === null) visibleSince = Date.now();
            const left = Math.max(0,Math.ceil((3000-(Date.now()-visibleSince))/1000));
            countdown.textContent = left > 0 ? `${left} detik` : 'Siap';
            if (left===0 && !pending && Date.now()>=retryAt) {button.disabled=false; advance();}
        };
        document.addEventListener('visibilitychange', refresh);
        form?.addEventListener('submit', event => {if (button.disabled || pending) event.preventDefault();});
        refresh(); setInterval(refresh,200);
    }
}
