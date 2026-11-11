/* QQ-CORE-01. CSP-safe, DOM-safe admin directory and editorial controls. */
const listing = document.querySelector('[data-qq-table]');
if (listing) {
    const rows = document.getElementById('qq-rows');
    const search = document.getElementById('qq-search');
    const status = document.getElementById('qq-status');
    const info = document.getElementById('qq-info');
    const previous = document.getElementById('qq-prev');
    const next = document.getElementById('qq-next');
    let start = 0, draw = 0, total = 0, abort, debounce;
    const pageSize = 10;
    function textCell(row, value) {
        const td = document.createElement('td'); td.textContent = String(value ?? '—'); row.append(td); return td;
    }
    async function load() {
        abort?.abort(); abort = new AbortController();
        const active = abort;
        const requestNo = ++draw;
        info.textContent = 'Memuat daftar…';
        const url = new URL(listing.dataset.url, location.origin);
        url.searchParams.set('draw', String(requestNo));
        url.searchParams.set('start', String(start));
        url.searchParams.set('length', String(pageSize));
        url.searchParams.set('search[value]', search.value.trim());
        if (status.value) url.searchParams.set('status',status.value);
        try {
            const response = await fetch(url,{credentials:'same-origin',signal:active.signal,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            if (!Array.isArray(payload.data)) throw new Error('Format tabel tidak sesuai');
            if (requestNo !== draw) return;
            rows.replaceChildren();
            total = Number(payload.recordsFiltered) || 0;
            payload.data.forEach(item=>{
                const tr = document.createElement('tr');
                textCell(tr,item.code); textCell(tr,item.title);
                textCell(tr,item.edition ? `v${item.edition}` : '—');
                textCell(tr,item.status);
                const action = document.createElement('td');
                if (typeof item.detail_url === 'string') {
                    const linkUrl=new URL(item.detail_url, location.origin);
                    if (linkUrl.origin===location.origin && linkUrl.pathname.startsWith('/admin/quiz-kilat/')) {
                        const link=document.createElement('a');link.href=linkUrl.href;link.textContent='Kelola';action.append(link);
                    }
                }
                tr.append(action);rows.append(tr);
            });
            if (!payload.data.length) {
                const tr=document.createElement('tr');const td=document.createElement('td');
                td.colSpan=5;td.textContent='Tidak ada quiz yang cocok.';tr.append(td);rows.append(tr);
            }
            info.textContent=total ? `${start+1}–${Math.min(start+payload.data.length,total)} dari ${total} quiz` : '0 quiz ditemukan';
            previous.disabled = start===0; next.disabled = start+pageSize>=total;
        } catch (_) {
            if (active.signal.aborted) return;
            info.textContent='Gagal memuat daftar. Gunakan Muat ulang.';
            rows.replaceChildren();
            const tr=document.createElement('tr');const td=document.createElement('td');td.colSpan=5;
            td.textContent='Koneksi gagal. Coba muat ulang.';tr.append(td);rows.append(tr);
        }
    }
    search.addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(()=>{start=0;load();},300);});
    status.addEventListener('change',()=>{start=0;load();});
    document.getElementById('qq-refresh')?.addEventListener('click',load);
    previous.addEventListener('click',()=>{start=Math.max(0,start-pageSize);load();});
    next.addEventListener('click',()=>{if (start+pageSize<total){start+=pageSize;load();}});
    load();
}

const editor = document.querySelector('[data-qq-editor]');
if (editor) {
    const list = document.getElementById('qq-selected-list');
    const count = document.getElementById('qq-selected-count');
    const search = document.getElementById('qq-question-search');
    const subject = document.getElementById('qq-question-subject');
    const message = document.getElementById('qq-search-message');
    const results = document.getElementById('qq-search-results');
    let abort, debounce;
    const updateCount = ()=>{count.textContent=String(list.children.length);};
    function actions(li) {
        const span=document.createElement('span');span.className='admin-v1-selected-actions';
        [['Naik','up'],['Turun','down'],['Hapus','remove']].forEach(([label,action])=>{
            const button=document.createElement('button');button.type='button';button.className='button button-outline button-compact';
            button.textContent=label;
            if (action==='remove') button.dataset.remove=''; else button.dataset.move=action;
            span.append(button);
        });li.append(span);
    }
    function add(item) {
        const id=String(item.version_id), code=String(item.code??'');
        if (!/^\d+$/.test(id)) return;
        if (Array.from(list.children).some(li=>li.dataset.versionId===id || li.dataset.code===code)) {
            message.textContent='Soal sudah dipilih.';return;
        }
        if (list.children.length>=40) { message.textContent='Maksimal 40 soal.'; return; }
        const li=document.createElement('li');li.dataset.versionId=id;li.dataset.code=code;
        const summary=document.createElement('span');summary.textContent=`${code} · ${item.subject} · v${item.version} — ${item.summary}`;li.append(summary);
        actions(li);
        const input=document.createElement('input');input.type='hidden';input.name='version_ids[]';input.value=id;li.append(input);
        list.append(li);updateCount();message.textContent='Soal ditambahkan ke draft.';
    }
    list.addEventListener('click', event=>{
        const button=event.target.closest('button');if (!button || !list.contains(button))return;
        const li=button.closest('li');if (!li)return;
        if (button.hasAttribute('data-remove'))li.remove();
        else if (button.dataset.move==='up' && li.previousElementSibling)list.insertBefore(li,li.previousElementSibling);
        else if (button.dataset.move==='down' && li.nextElementSibling)list.insertBefore(li.nextElementSibling,li);
        updateCount();
    });
    async function findQuestions() {
        abort?.abort();abort=new AbortController();const active=abort;
        message.textContent='Mencari soal terbit…';results.replaceChildren();
        const url=new URL(editor.dataset.searchUrl,location.origin);
        url.searchParams.set('q',search.value.trim());url.searchParams.set('subject',subject.value);
        try {
            const response=await fetch(url,{credentials:'same-origin',signal:active.signal,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
            if(!response.ok)throw new Error('Gagal memuat');
            const payload=await response.json();if(!Array.isArray(payload.data))throw new Error('Respons tidak sesuai');
            if(active.signal.aborted)return;
            payload.data.forEach(row=>{
                const wrapper=document.createElement('div');wrapper.className='admin-v1-search-row';
                const span=document.createElement('span');span.textContent=`${row.code} · ${row.subject} · v${row.version}: ${row.summary}`;
                const button=document.createElement('button');button.type='button';button.className='button button-outline button-compact';
                button.textContent='Tambahkan';button.addEventListener('click',()=>add(row));wrapper.append(span,button);results.append(wrapper);
            });
            message.textContent=payload.data.length ? `${payload.data.length} soal ditemukan (maksimal 30 per pencarian).` : 'Tidak ada soal terbit yang cocok.';
        } catch (_) {
            if(active.signal.aborted)return;
            message.textContent='Gagal mencari soal. Coba lagi.';
        }
    }
    search.addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(findQuestions,300);});
    subject.addEventListener('change',findQuestions);
    document.getElementById('qq-search-button')?.addEventListener('click',findQuestions);
    findQuestions();updateCount();
}
