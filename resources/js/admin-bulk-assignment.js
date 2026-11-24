/* SIAPEGAWAI Q-BULK-ASSIGN-01 C-02: one picker UI for initial creation and append-only edit.
 * All untrusted question content is rendered with textContent. No inline event handlers. */
const button = (label, onClick, className='button button-outline button-compact') => {
  const b=document.createElement('button');b.type='button';b.className=className;b.textContent=label;b.addEventListener('click',onClick);return b;
};
function makeSortable(list,onChange) {
  // Desktop grip-only native DnD; touch and keyboard have exact-position controls.
  let dragging=null;
  list.addEventListener('pointerdown',e=>{
    const li=e.target.closest('li');if(li) li.dataset.dragHandle=e.target.closest('.sg-bulk-drag,.sg-editor-grip')?'1':'0';
  });
  list.addEventListener('dragstart',e=>{
    const li=e.target.closest('li');
    if(!li || li.dataset.dragHandle!=='1' || matchMedia('(pointer:coarse)').matches) {e.preventDefault();return;}
    dragging=li;li.classList.add('is-dragging');e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain','ordered-item');
  });
  list.addEventListener('dragover',e=>{if(!dragging)return;e.preventDefault();const over=e.target.closest('li');if(over && over!==dragging && list.contains(over)) {
    const after=e.clientY > over.getBoundingClientRect().top+over.getBoundingClientRect().height/2;
    list.insertBefore(dragging,after?over.nextSibling:over);
  }});
  list.addEventListener('drop',e=>{if(dragging){e.preventDefault();dragging.classList.remove('is-dragging');dragging=null;onChange();}});
  list.addEventListener('dragend',()=>{if(dragging){dragging.classList.remove('is-dragging');dragging=null;onChange();}});
}
function initPins() {
  for(const host of document.querySelectorAll('[data-sg-editor-pins]')) {
    const list=host.querySelector('[data-sg-pins]');const count=host.querySelector('[data-sg-pin-count]');
    const refresh=()=>{
      const rows=Array.from(list.children);
      rows.forEach((li,i)=>{li.querySelector('[data-sg-rank]').textContent=String(i+1);li.querySelector('[data-sg-jump]').value=String(i+1);});
      count.textContent=String(rows.length);
    };
    list.querySelectorAll('li').forEach(li=>{li.draggable=true;});
    list.addEventListener('keydown',e=>{if(e.key==='Enter'&&e.target.matches('[data-sg-jump]')){e.preventDefault();e.target.closest('li')?.querySelector('[data-sg-jump-go]')?.click();}});
    list.addEventListener('click',e=>{
      const li=e.target.closest('li');if(!li)return;
      if(e.target.closest('[data-sg-remove]')){li.remove();refresh();return;}
      if(!e.target.closest('[data-sg-jump-go]'))return;
      const rows=Array.from(list.children);const from=rows.indexOf(li);
      const n=Number(li.querySelector('[data-sg-jump]').value);
      if(!Number.isInteger(n)||n<1||n>rows.length){li.querySelector('[data-sg-jump]').reportValidity();return;}
      li.remove();const remaining=Array.from(list.children);list.insertBefore(li,remaining[n-1]||null);refresh();
      li.querySelector('[data-sg-jump-go]').focus();
    });
    makeSortable(list,refresh);refresh();
  }
}
for (const host of document.querySelectorAll('[data-bulk-assignment]')) {
  const $=s=>host.querySelector(s);
  const results=$('[data-bulk-results]'), selected=$('[data-bulk-selected]'), status=$('[data-bulk-status]');
  const review=$('[data-bulk-review]'), summary=$('[data-bulk-summary]'), errors=$('[data-bulk-errors]');
  const previewBtn=$('[data-bulk-preview]'), confirmBtn=$('[data-bulk-confirm]');
  const create=host.dataset.mode==='create', max=Number(host.dataset.max)||100;
  const choices=new Map(); let page=1, more=false, totalPages=1, totalResults=0, candidateScope=false, visible=[], token=null, busy=false, seq=0;
  const csrf=document.querySelector('input[name="_token"]')?.value??'';
  function announce(message,isError=false){status.textContent=message;status.classList.toggle('sg-bulk-assign__status--error',isError);}
  function invalidate(){token=null;if(confirmBtn)confirmBtn.disabled=true;review.hidden=true;}
  function listItems(){return Array.from(selected.children).map(li=>choices.get(Number(li.dataset.versionId))).filter(Boolean);}
  function syncOrder() {
    const ordered=listItems();choices.clear();ordered.forEach(item=>choices.set(item.id,item));
    selected.querySelectorAll('li').forEach((li,i)=>{
      li.querySelector('[data-bulk-rank]').textContent=String(i+1);
      li.querySelector('[data-bulk-position]').value=String(i+1);
    });
    $('[data-bulk-count]').textContent=String(choices.size);invalidate();
  }
  function moveTo(index,pos) {
    const li=selected.children[index];if(!li || !Number.isInteger(pos) || pos<1 || pos>selected.children.length){announce('Nomor tujuan di luar daftar pilihan.',true);return;}
    li.remove();selected.insertBefore(li,selected.children[pos-1]||null);syncOrder();
    announce(`Soal dipindahkan ke urutan ${pos}.`);
  }
  function renderSelected() {
    selected.replaceChildren();$('[data-bulk-count]').textContent=String(choices.size);
    Array.from(choices.values()).forEach((item,i)=>{
      const li=document.createElement('li');li.dataset.versionId=String(item.id);li.draggable=true;
      const grip=document.createElement('span');grip.className='sg-bulk-drag';grip.textContent='⠿';grip.title='Geser untuk mengurutkan di desktop';grip.setAttribute('aria-hidden','true');
      const rank=document.createElement('strong');rank.dataset.bulkRank='';rank.textContent=String(i+1);
      const label=document.createElement('span');label.textContent=`${item.code} · ${item.subject||'—'} · v${item.version} — ${item.summary}`;
      const actions=document.createElement('div');actions.className='sg-bulk-assign__row-actions';
      const jump=document.createElement('input');jump.type='number';jump.min='1';jump.max=String(max);jump.value=String(i+1);jump.dataset.bulkPosition='';jump.setAttribute('aria-label',`Nomor tujuan untuk ${item.code}`);
      const jumpLabel=document.createElement('label');jumpLabel.textContent='Ke nomor ';jumpLabel.append(jump);
      actions.append(jumpLabel,button('Pindahkan',()=>moveTo(Array.from(selected.children).indexOf(li),Number(jump.value))),
        button('Hapus',()=>{choices.delete(item.id);invalidate();renderSelected();renderResults();}));
      li.append(grip,rank,label,actions);
      if(create){const hidden=document.createElement('input');hidden.type='hidden';hidden.name='version_ids[]';hidden.value=String(item.id);li.append(hidden);}
      selected.append(li);
    });
  }
  selected.addEventListener('keydown',e=>{if(e.key==='Enter'&&e.target.matches('[data-bulk-position]')){e.preventDefault();e.target.closest('li')?.querySelector('.sg-bulk-assign__row-actions button')?.click();}});
  makeSortable(selected,()=>{syncOrder();announce('Urutan pilihan diperbarui. Periksa sebelum melanjutkan.');});
  function refreshPageActions(){
    $('[data-bulk-page]').textContent=`Halaman ${page} dari ${totalPages}`;
    $('[data-bulk-page]').title=`${totalResults} ${candidateScope?'kandidat (metadata)':'hasil'} ditemukan`;
    $('[data-bulk-prev]').disabled=page<=1;
    $('[data-bulk-next]').disabled=!more;
    $('[data-bulk-select-page]').disabled=visible.length===0 || visible.every(item=>item.eligible===false||choices.has(item.id)) || choices.size>=max;
    $('[data-bulk-unselect-page]').disabled=!visible.some(item=>choices.has(item.id));
  }
  function renderResults(){
    results.replaceChildren();
    if(!visible.length){const tr=document.createElement('tr');const td=document.createElement('td');td.colSpan=3;td.textContent='Tidak ditemukan soal yang cocok.';tr.append(td);results.append(tr);}
    visible.forEach(item=>{
      const tr=document.createElement('tr');const check=document.createElement('td');const input=document.createElement('input');
      input.type='checkbox';input.checked=choices.has(item.id);input.disabled=item.eligible===false&&!input.checked;input.setAttribute('aria-label',`Pilih ${item.code}`);
      input.addEventListener('change',()=>{
        if(input.checked){if(item.eligible===false){input.checked=false;return;}if(choices.size>=max){input.checked=false;announce(`Maksimal ${max} soal dapat dipilih.`,true);return;}choices.set(item.id,item);}
        else choices.delete(item.id);
        invalidate();renderSelected();refreshPageActions();
      });check.append(input);
      const name=document.createElement('td');name.textContent=`${item.code} · ${item.subject||'—'} · v${item.version}`;
      const desc=document.createElement('td');desc.textContent=String(item.summary??'')+(item.eligible===false?' — Tidak dapat dipilih: '+String(item.eligibility_reason??'Versi tidak layak'):'');
      tr.append(check,name,desc);results.append(tr);
    });
    refreshPageActions();
  }
  async function fetchData(url,opts={}) {
    const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json',...(opts.body?{'Content-Type':'application/json','X-CSRF-TOKEN':csrf}:{})},...opts});
    const data=await response.json().catch(()=>({}));
    if(!response.ok){const msg=data.errors?Object.values(data.errors).flat().join(' '):(data.message||`HTTP ${response.status}`);throw new Error(msg);}
    return data;
  }
  async function search(){
    const my=++seq;const url=new URL(host.dataset.searchUrl,location.origin);
    url.searchParams.set('q',$('[data-bulk-search]').value.trim());url.searchParams.set('subject',$('[data-bulk-subject]').value.trim());url.searchParams.set('page',String(page));
    results.replaceChildren();announce('Mencari soal terbit…');
    try{const data=await fetchData(url);if(my!==seq)return;
      visible=(data.data||[]).map(x=>({...x,id:Number(x.id??x.version_id)})).filter(x=>Number.isInteger(x.id)&&x.id>0);
      totalPages=Math.max(1,Number(data.last_page)||1);
      totalResults=Math.max(0,Number(data.total_results)||0);candidateScope=data.eligibility_scope==='CANDIDATES_METADATA_ONLY';
      more=!!data.has_more;renderResults();announce(`${visible.length} ${candidateScope?'kandidat (metadata; integritas file diperiksa saat simpan)':'hasil'} pada halaman ${page} dari ${totalPages}; ${choices.size} soal dipilih dari semua halaman.`);
    }catch(e){if(my===seq){visible=[];more=false;totalPages=Math.max(1,page);totalResults=0;renderResults();announce(`Pencarian gagal: ${e.message}`,true);}}
  }
  const ids=()=>Array.from(choices.keys());
  async function preview(){
    if(create||busy)return;if(!choices.size){announce('Pilih minimal satu soal.',true);return;}
    busy=true;previewBtn.disabled=true;invalidate();announce('Memeriksa seluruh pilihan…');
    try{const data=await fetchData(host.dataset.previewUrl,{method:'POST',body:JSON.stringify({version_ids:ids()})});
      review.hidden=false;summary.textContent=`Existing ${data.existing_count} · valid ${data.valid_count} · invalid ${data.invalid_count} · total ${data.total_after}/${data.max}.`;
      errors.replaceChildren();(data.errors||[]).forEach(x=>{const li=document.createElement('li');li.textContent=`Pilihan ${x.index} (versi ${x.id}): ${x.message}`;errors.append(li);});
      token=data.can_confirm?data.token:null;confirmBtn.disabled=!token;
      announce(token?'Semua pilihan valid. Periksa urutan sebelum konfirmasi.':'Pilihan belum valid; perbaiki dan periksa ulang.',!token);
    }catch(e){announce(`Pratinjau gagal: ${e.message}`,true);}finally{busy=false;previewBtn.disabled=false;}
  }
  async function confirm(){
    if(create||busy||!token)return;busy=true;confirmBtn.disabled=true;previewBtn.disabled=true;
    try{const data=await fetchData(host.dataset.confirmUrl,{method:'POST',body:JSON.stringify({token})});
      announce(data.status==='already_applied'?'Permintaan sudah diterapkan, tidak ada duplikasi.':`${data.count} soal disimpan. Memuat ulang…`);window.location.reload();
    }catch(e){token=null;announce(`Konfirmasi ditolak: ${e.message} Lakukan pratinjau ulang.`,true);}finally{busy=false;previewBtn.disabled=false;}
  }
  $('[data-bulk-find]').addEventListener('click',()=>{page=1;search();});
  for(const key of ['[data-bulk-search]','[data-bulk-subject]']) $(key).addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();page=1;search();}});
  $('[data-bulk-prev]').addEventListener('click',()=>{if(page>1){page--;search();}});
  $('[data-bulk-next]').addEventListener('click',()=>{if(more){page++;search();}});
  $('[data-bulk-select-page]').addEventListener('click',()=>{
    let added=0;visible.forEach(x=>{if(x.eligible!==false&&!choices.has(x.id)&&choices.size<max){choices.set(x.id,x);added++;}});
    invalidate();renderSelected();renderResults();announce(`${added} soal ditambahkan dari halaman ini. Total ${choices.size}/${max}.`);
  });
  $('[data-bulk-unselect-page]').addEventListener('click',()=>{
    let removed=0;visible.forEach(item=>{if(choices.delete(item.id))removed++;});
    invalidate();renderSelected();renderResults();announce(`${removed} pilihan dibatalkan dari halaman ini. Total ${choices.size}/${max}.`);
  });
  $('[data-bulk-clear]').addEventListener('click',()=>{choices.clear();invalidate();renderSelected();renderResults();announce('Semua pilihan dikosongkan.');});
  if(previewBtn)previewBtn.addEventListener('click',preview);
  if(confirmBtn)confirmBtn.addEventListener('click',confirm);
  if(create){
    for(const node of host.parentElement.querySelectorAll('[data-sg-restore-id]')) {
      const id=Number(node.dataset.sgRestoreId);if(Number.isInteger(id)&&id>0&&!choices.has(id)&&choices.size<max)
        choices.set(id,{id,code:`Versi #${id}`,subject:'Perlu diperiksa',version:'?',summary:'Pilihan sebelumnya setelah validasi gagal; pastikan versi masih layak.'});
    }
    const form=host.closest('form');
    form?.addEventListener('submit',()=>{syncOrder();});
    announce('Cari dan pilih beberapa soal, kemudian simpan sebagai draft bersama identitasnya.');
  }
  renderSelected();search();
}
initPins();
