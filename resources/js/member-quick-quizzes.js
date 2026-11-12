// QQ participant runtime: async server-rendered state. Never trust the browser for scoring, deadlines or quota.
const examRoot = document.getElementById('qq-exam');
if (examRoot) {
    const feedbackMinimumMs = 5000;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const countdown = document.getElementById('qq-countdown');
    const headingProgress = document.getElementById('qq-heading-progress');
    const takeUrl = window.location.pathname;
    let root = examRoot;
    let timer = null;
    let pending = false;
    let retryAfter = 0;
    let serverOffset = 0;

    const serverNow = () => Date.now() - serverOffset;
    const recalibrate = () => { serverOffset = Date.now() - Number(root.dataset.serverNowMs); };
    const requestState = async (url, fields = null) => {
        const controller = new AbortController();
        const deadline = setTimeout(() => controller.abort(), 15000);
        try {
            const body = new FormData();
            if (fields !== null) for (const [name, value] of Object.entries(fields)) body.append(name, value);
            const response = await fetch(url, {
                method: fields === null ? 'GET' : 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                credentials: 'same-origin', cache: 'no-store',
                ...(fields === null ? {} : { body }), signal: controller.signal,
            });
            if (!response.ok) throw new Error(`Server belum menerima permintaan (${response.status}).`);
            if (!response.headers.get('content-type')?.includes('application/json')) {
                throw new Error('Sesi perlu diperbarui.');
            }
            return await response.json();
        } finally { clearTimeout(deadline); }
    };

    const renderState = (data, focus = true) => {
        if (data.phase === 'finished' && data.redirect) {
            window.location.assign(data.redirect); // Only leave the exam when all questions are done.
            return;
        }
        if (!['question', 'feedback'].includes(data.phase) || typeof data.html !== 'string') {
            throw new Error('Status quiz dari server tidak valid.');
        }
        // The trusted Blade fragment escapes all published question, option and explanation text.
        // DOMParser + replaceWith avoids inserting unescaped text via innerHTML.
        const parsed = new DOMParser().parseFromString(data.html, 'text/html');
        const nextRoot = parsed.getElementById('qq-exam');
        if (!nextRoot || nextRoot.dataset.phase !== data.phase) {
            throw new Error('Tampilan quiz dari server tidak valid.');
        }
        if (timer !== null) clearInterval(timer);
        root.replaceWith(document.importNode(nextRoot, true));
        root = document.getElementById('qq-exam');
        pending = false;
        retryAfter = 0;
        headingProgress.textContent = `Soal ${data.index} dari ${data.total} · ${data.seconds_per_question} detik/soal`;
        recalibrate();
        bindPhase();
        if (focus) {
            const target = root.querySelector(data.phase === 'feedback' ? '#qq-feedback-heading' : '#qq-question-title');
            target?.focus({ preventScroll: true });
            if (data.phase === 'question') root.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    const recover = async (error, status) => {
        try {
            const data = await requestState(takeUrl);
            renderState(data, false);
            const current = document.getElementById(root.dataset.phase === 'feedback' ? 'qq-feedback-status' : 'qq-save-status');
            if (current) current.textContent = 'Status disinkronkan kembali dengan server.';
        } catch {
            pending = false;
            retryAfter = Date.now() + 3000;
            const retry = document.getElementById('qq-answer-submit') ?? document.getElementById('qq-next-button');
            if (retry) {
                retry.hidden = false;
                retry.disabled = true;
                setTimeout(() => { if (retry.isConnected) retry.disabled = false; }, 3000);
            }
            root.querySelectorAll('input[name="answer"]').forEach(input => { input.disabled = false; });
            if (status?.isConnected) status.textContent = `${error.message} Periksa koneksi lalu coba kembali, atau muat ulang untuk memulihkan sesi.`;
            else {
                const help = root.querySelector('[role="status"]');
                if (help) help.textContent = `${error.message} Periksa koneksi lalu coba kembali atau muat ulang.`;
            }
        }
    };

    const submitState = async (url, fields, status) => {
        if (pending || Date.now() < retryAfter) return;
        pending = true;
        status.textContent = fields.item_id ? 'Menyimpan dan mengunci jawaban…' : 'Memuat soal berikutnya…';
        try {
            const data = await requestState(url, fields);
            renderState(data);
        } catch (error) { await recover(error, status); }
    };

    const bindPhase = () => {
        const phase = root.dataset.phase;
        if (phase === 'question') {
            const form = root.querySelector('#qq-answer-form');
            const choices = [...form.querySelectorAll('input[name="answer"]')];
            const button = form.querySelector('#qq-answer-submit');
            const status = form.querySelector('#qq-save-status');
            button.hidden = true; // Without JS, the ordinary POST button remains available.
            const send = (choice) => {
                if (pending || Date.now() < retryAfter) return;
                choices.forEach(input => { input.disabled = true; });
                button.disabled = true;
                submitState(root.dataset.answerUrl, {
                    item_id: form.querySelector('[name="item_id"]').value,
                    answer: choice,
                }, status);
            };
            form.addEventListener('submit', event => {
                event.preventDefault();
                const selected = form.querySelector('input[name="answer"]:checked');
                if (selected) send(selected.value);
            });
            choices.forEach(input => input.addEventListener('change', () => {
                if (input.checked) send(input.value);
            }));
            const tick = () => {
                const left = Math.ceil((Number(root.dataset.expiresMs) - serverNow()) / 1000);
                countdown.textContent = left > 0 ? `${left} detik` : 'Waktu habis';
                if (left <= 0 && !pending && Date.now() >= retryAfter) send('');
            };
            tick();
            timer = setInterval(tick, 200);
        } else if (phase === 'feedback') {
            const form = root.querySelector('#qq-next-form');
            const button = form.querySelector('#qq-next-button');
            const status = form.querySelector('#qq-feedback-status');
            let shownMs = 0;
            let lastVisible = document.hidden ? null : performance.now();
            button.disabled = true;
            const advance = () => {
                button.disabled = true;
                submitState(root.dataset.nextUrl, {}, status);
            };
            const tick = () => {
                const now = performance.now();
                if (lastVisible !== null) shownMs += Math.max(0, now - lastVisible);
                lastVisible = document.hidden ? null : now;
                const visibleLeft = Math.max(0, feedbackMinimumMs - shownMs);
                const serverLeft = Math.max(0, Number(root.dataset.feedbackUntilMs) - serverNow());
                const left = Math.max(visibleLeft, serverLeft);
                countdown.textContent = left > 0 ? `${Math.ceil(left / 1000)} detik` : 'Siap';
                if (!document.hidden && left <= 0 && !pending && Date.now() >= retryAfter) {
                    button.disabled = false;
                    advance();
                }
            };
            form.addEventListener('submit', event => {
                event.preventDefault();
                if (!pending && !button.disabled && Date.now() >= retryAfter) advance();
            });
            tick();
            timer = setInterval(tick, 200);
        }
    };

    recalibrate();
    bindPhase();
}
