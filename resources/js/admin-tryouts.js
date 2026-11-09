/* Siapegawai tryout editor: DOM-safe textContent, AJAX search and ordered pinned versions. */
const listing = document.querySelector('[data-tryouts-table]');
if (listing) {
    const rows = document.getElementById('tryouts-rows');
    const info = document.getElementById('tryouts-info');
    const previous = document.getElementById('tryouts-prev');
    const next = document.getElementById('tryouts-next');
    const search = document.getElementById('tryouts-search');
    const status = document.getElementById('tryouts-status');
    let offset = 0;
    let count = 0;
    let sequence = 0;
    let active;
    let debounce;
    const length = 10;
    const textCell = (row, value) => { const cell = document.createElement('td'); cell.textContent = value == null ? '—' : String(value); row.append(cell); return cell; };
    const setEmpty = message => { rows.replaceChildren(); const tr=rows.insertRow(); textCell(tr,message).colSpan=6; };
    async function load() {
        const current = ++sequence;
        active?.abort();
        active = new AbortController();
        setEmpty('Memuat tryout…');
        info.textContent = 'Memuat…';
        const url = new URL(listing.dataset.url, location.origin);
        const columns = ['code','name','duration_minutes','items_count','status','detail_url'];
        columns.forEach((col, i) => {
            url.searchParams.set(`columns[${i}][data]`,col);
            url.searchParams.set(`columns[${i}][name]`,col);
            url.searchParams.set(`columns[${i}][searchable]`,String(i<2));
            url.searchParams.set(`columns[${i}][orderable]`,String(i<5));
        });
        url.searchParams.set('draw',String(current));
        url.searchParams.set('start',String(offset));
        url.searchParams.set('length',String(length));
        url.searchParams.set('search[value]',search.value.trim());
        url.searchParams.set('status',status.value);
        url.searchParams.set('order[0][column]','0');
        url.searchParams.set('order[0][dir]','asc');
        try {
            const response = await fetch(url,{credentials:'same-origin',signal:active.signal,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (current!==sequence || !Array.isArray(data.data)) return;
            count=Number(data.recordsFiltered||0);
            rows.replaceChildren();
            data.data.forEach(item => {
                const tr=document.createElement('tr');
                textCell(tr,item.code); textCell(tr,item.name);
                textCell(tr,item.duration_minutes + ' menit'); textCell(tr,item.items_count);
                const cell=textCell(tr,({draft:'Draft',published:'Terbit',archived:'Arsip'})[item.status]||item.status);
                cell.className = 'admin-v1-tryout-status-cell';
                const action=document.createElement('td');
                try {
                    const detail=new URL(item.detail_url,location.origin);
                    if (detail.origin===location.origin && detail.pathname.startsWith('/admin/tryout/')) {
                        const a=document.createElement('a'); a.href=detail.href; a.textContent='Kelola'; action.append(a);
                    }
                } catch (_) { /* Untrusted URL ignored. */ }
                tr.append(action); rows.append(tr);
            });
            if (!data.data.length) setEmpty('Belum ada tryout yang cocok. Buat template baru atau ubah filter.');
            info.textContent=`${count?offset+1:0}–${offset+data.data.length} dari ${count} tryout`;
            previous.disabled=offset<=0; next.disabled=offset+data.data.length>=count;
        } catch (_) {
            if (active.signal.aborted || current!==sequence) return;
            setEmpty('Gagal memuat data. Klik Muat ulang.'); info.textContent='Kesalahan memuat daftar';
        }
    }
    const reset=()=>{offset=0;load();};
    search.addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(reset,300);});
    status.addEventListener('change',reset);
    document.getElementById('tryouts-refresh')?.addEventListener('click',load);
    previous.addEventListener('click',()=>{offset=Math.max(0,offset-length);load();});
    next.addEventListener('click',()=>{if(offset+length<count){offset+=length;load();}});
    load();
}

const editor = document.querySelector('[data-tryout-editor]');
if (editor) {
    const list = document.getElementById('tryout-selected-list');
    const count = document.getElementById('tryout-selected-count');
    const results = document.getElementById('tryout-search-results');
    const message = document.getElementById('tryout-search-message');
    const search = document.getElementById('tryout-question-search');
    const subject = document.getElementById('tryout-question-subject');
    let abort;
    let debounce;
    const buttons = li => {
        const wrapper=document.createElement('span'); wrapper.className='admin-v1-selected-actions';
        [['up','Naik'],['down','Turun'],['remove','Hapus']].forEach(([action,label])=>{
            const b=document.createElement('button');b.type='button';b.className='button button-outline button-compact';
            b.textContent=label;
            if(action==='remove')b.dataset.remove='';else b.dataset.move=action;
            wrapper.append(b);
        });
        li.append(wrapper);
    };
    const refresh=()=>{count.textContent=String(list.children.length);};
    function add(row) {
        const id=String(row.version_id);
        if (!/^\d+$/.test(id)) return;
        if (list.querySelector(`li[data-version-id="${id}"]`)) { message.textContent='Versi tersebut sudah dipilih.'; return; }
        const code=String(row.code);
        if (Array.from(list.children).some(li=>li.dataset.code===code)) { message.textContent='Soal tersebut sudah ada dalam daftar versi pilihan.'; return; }
        if (list.children.length >= 100) { message.textContent='Batas maksimal 100 soal dalam satu tryout.'; return; }
        const li=document.createElement('li');li.dataset.versionId=id;li.dataset.code=code;
        const desc=document.createElement('span');desc.textContent=`${code} · ${row.subject} · v${row.version} — ${row.summary}`;li.append(desc);
        buttons(li);
        const hidden=document.createElement('input');hidden.type='hidden';hidden.name='version_ids[]';hidden.value=id;li.append(hidden);
        list.append(li);refresh();message.textContent='Soal ditambahkan. Simpan draft untuk menyimpan susunan.';
    }
    Array.from(list.children).forEach(li=>{
        const text=li.querySelector('span')?.textContent||'';
        li.dataset.code=text.split(' · ')[0];
    });
    list.addEventListener('click', event => {
        const button=event.target.closest('button'); if(!button || !list.contains(button))return;
        const li=button.closest('li'); if(!li)return;
        if(button.hasAttribute('data-remove')) li.remove();
        else if(button.dataset.move==='up' && li.previousElementSibling)list.insertBefore(li,li.previousElementSibling);
        else if(button.dataset.move==='down' && li.nextElementSibling)list.insertBefore(li.nextElementSibling,li);
        refresh();
    });
    async function searchQuestions() {
        abort?.abort();abort=new AbortController();
        message.textContent='Mencari soal terbit…'; results.replaceChildren();
        const url=new URL(editor.dataset.searchUrl,location.origin);
        url.searchParams.set('q',search.value.trim());
        url.searchParams.set('subject',subject.value);
        try {
            const response=await fetch(url,{credentials:'same-origin',signal:abort.signal,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
            if(!response.ok)throw new Error('HTTP '+response.status);
            const payload=await response.json();
            if(!Array.isArray(payload.data))throw new Error('Format hasil tidak sesuai');
            payload.data.forEach(row=>{
                const div=document.createElement('div');div.className='admin-v1-search-row';
                const text=document.createElement('span');text.textContent=`${row.code} · ${row.subject} · v${row.version}: ${row.summary}`;
                const addButton=document.createElement('button');addButton.type='button';addButton.className='button button-outline button-compact';
                addButton.textContent='Tambahkan';addButton.addEventListener('click',()=>add(row));
                div.append(text,addButton);results.append(div);
            });
            message.textContent=payload.data.length ? `Menampilkan ${payload.data.length} soal terbit (maksimal 25 per pencarian).` : 'Tidak ada soal terbit yang cocok.';
        } catch (_) {
            if(abort.signal.aborted)return;
            message.textContent='Gagal mencari soal. Coba lagi.';
        }
    }
    search.addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(searchQuestions,350);});
    subject.addEventListener('change',searchQuestions);
    document.getElementById('tryout-search-button')?.addEventListener('click',searchQuestions);
    searchQuestions();refresh();
}
