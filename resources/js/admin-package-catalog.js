// PKG-CATALOG-01: local Vite progressive enhancement. No inline handlers or raw HTML injection.
const init = () => {
  const table = document.querySelector('[data-pkg-table]');
  if (!table) return;
  const rows = document.getElementById('pkg-rows');
  const search = document.getElementById('pkg-search');
  const size = document.getElementById('pkg-size');
  const info = document.getElementById('pkg-table-info');
  const error = document.getElementById('pkg-table-error');
  const prev = document.getElementById('pkg-prev');
  const next = document.getElementById('pkg-next');
  const create = document.getElementById('pkg-create-dialog');
  // Top-layer dialogs should not be descendants of a horizontally scrolling
  // admin workspace. Portal them to body before calling showModal().
  for (const dialog of [create]) {
    if (dialog && dialog.parentElement !== document.body) document.body.append(dialog);
  }
  let offset = 0, total = 0, busy = false, lastFocus = null, seq = 0;
  const cell = (tr, value, header = false) => {
    const td = document.createElement(header ? 'th' : 'td');
    if (header) td.scope = 'row';
    td.textContent = String(value ?? '—'); tr.append(td); return td;
  };
  const close = (dialog) => { if (dialog?.open) dialog.close(); lastFocus?.focus?.(); };
  document.querySelectorAll('[data-pkg-close]').forEach(b => b.addEventListener('click', () => close(b.closest('dialog'))));
  for (const dialog of [create]) {
    dialog?.addEventListener('close', () => { lastFocus?.focus?.(); });
    dialog?.addEventListener('click', e => { if (e.target === dialog) close(dialog); });
  }
  document.getElementById('pkg-create-open')?.addEventListener('click', e => {
    lastFocus = e.currentTarget; create.showModal(); create.querySelector('input[name="code"]')?.focus();
  });
  const query = async () => {
    if (busy) return; busy = true; const requestIndex = ++seq;
    error.hidden = true;
    const params = new URLSearchParams({draw: String(requestIndex), start: String(offset), length: size.value});
    params.set('search[value]', search.value); params.set('search[regex]', 'false');
    ['name','code','tier','availability'].forEach((field,i) => {
      params.set(`columns[${i}][data]`, field); params.set(`columns[${i}][name]`, field);
      params.set(`columns[${i}][searchable]`, 'true'); params.set(`columns[${i}][orderable]`, 'true');
    });
    params.set('order[0][column]', '2'); params.set('order[0][dir]', 'asc');
    try {
      const response = await fetch(`${table.dataset.url}?${params}`, {
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}, credentials: 'same-origin'});
      if (!response.ok) throw new Error('Daftar paket gagal dimuat.');
      const payload = await response.json(); total = Number(payload.recordsFiltered || 0);
      rows.replaceChildren();
      for (const data of payload.data || []) {
        const tr = document.createElement('tr');
        cell(tr, data.title || data.name, true); cell(tr, data.code); cell(tr, data.tier);
        const availability = {requestable: 'Menerima pengajuan', planned: 'Dalam perencanaan',
          closed: 'Pengajuan ditutup', archived: 'Diarsipkan'};
        cell(tr, data.is_archived ? 'Diarsipkan' : (availability[data.availability] || 'Dalam perencanaan'));
        cell(tr, data.published_version_id ? 'Terbit' : 'Draft');
        const actions = cell(tr, ''); actions.textContent = '';
        const open = document.createElement('a'); open.className = 'pkg-manage-link'; open.href = data.url;
        open.textContent = 'Kelola'; actions.append(open); rows.append(tr);
      }
      if (!total) {const tr = document.createElement('tr'); const td = document.createElement('td'); td.colSpan = 6; td.textContent = 'Tidak ada paket yang cocok.'; tr.append(td); rows.append(tr);}
      info.textContent = `${total ? offset + 1 : 0}–${Math.min(offset + Number(size.value),total)} dari ${total} paket`;
      prev.disabled = offset <= 0; next.disabled = offset + Number(size.value) >= total;
    } catch(e) {error.textContent = e.message || 'Gagal memuat paket.'; error.hidden = false; }
    finally {busy = false;}
  };
  let debounce;
  search.addEventListener('input', () => {clearTimeout(debounce); debounce = setTimeout(() => {offset = 0; query();}, 250);});
  size.addEventListener('change', () => {offset = 0; query();});
  document.getElementById('pkg-refresh').addEventListener('click', query);
  prev.addEventListener('click', () => {offset = Math.max(0, offset - Number(size.value)); query();});
  next.addEventListener('click', () => {if (offset + Number(size.value) < total) { offset += Number(size.value); query(); }});
  query();
};
// Full-page draft preview: no writes, HTML injection, remote dependencies or reload.
const initDetail = () => {
  const form = document.querySelector('[data-pkg-detail-draft]');
  if (!form) return;
  const open = document.getElementById('pkg-detail-preview-open');
  const panel = document.getElementById('pkg-detail-preview');
  if (!open || !panel) return;
  open.addEventListener('click', () => {
    const title = form.querySelector('[name="title"]').value.trim();
    const summary = form.querySelector('[name="summary"]').value.trim();
    const benefits = form.querySelector('[name="benefits"]').value;
    const valid = form.reportValidity();
    if (!valid) return;
    document.getElementById('pkg-detail-preview-title').textContent = title;
    document.getElementById('pkg-detail-preview-summary').textContent = summary;
    const list = document.getElementById('pkg-detail-preview-benefits');
    list.replaceChildren();
    benefits.split(/\r?\n/).map(x => x.trim()).filter(Boolean).forEach(text => {
      const item = document.createElement('li'); item.textContent = text; list.append(item);
    });
    panel.hidden = false;
    panel.scrollIntoView({block: 'nearest', behavior: 'auto'});
  });
  const publish = document.querySelector('[data-pkg-detail-publish]');
  const publishButton = publish?.querySelector('button[type="submit"]');
  const unsaved = document.getElementById('pkg-detail-unsaved');
  const initial = Array.from(form.querySelectorAll('[name="title"], [name="summary"], [name="benefits"]'), input => input.value);
  const trackUnsaved = () => {
    panel.hidden = true;
    const changed = Array.from(form.querySelectorAll('[name="title"], [name="summary"], [name="benefits"]'))
      .some((input, i) => input.value !== initial[i]);
    if (publishButton) publishButton.disabled = changed;
    if (unsaved) unsaved.hidden = !changed;
  };
  form.addEventListener('input', trackUnsaved);
  trackUnsaved();
};
// Dedicated sortable page: no paginated rows, no dependency on remote CDNs.
const initOrder = () => {
  const root = document.querySelector('[data-pkg-ordering]');
  if (!root) return;
  const list = document.getElementById('pkg-sort-list');
  const save = document.getElementById('pkg-order-save');
  const live = document.getElementById('pkg-order-live');
  const initial = Array.from(list.children, row => row.dataset.id);
  const coarse = window.matchMedia('(pointer: coarse)');
  const narrow = window.matchMedia('(max-width: 760px)');
  let dragged = null;
  const rows = () => Array.from(list.querySelectorAll('[data-pkg-sort-item]'));
  const refresh = () => {
    const order = rows();
    order.forEach((row, i) => {
      row.querySelector('.pkg-sort-rank').textContent = String(i + 1);
      row.querySelector('[data-pkg-move="up"]').disabled = i === 0;
      row.querySelector('[data-pkg-move="down"]').disabled = i === order.length - 1;
      row.draggable = !(coarse.matches || narrow.matches);
    });
    save.disabled = order.every((row, i) => row.dataset.id === initial[i]);
  };
  list.addEventListener('click', event => {
    const button = event.target.closest('[data-pkg-move]');
    if (!button || button.disabled) return;
    const item = button.closest('[data-pkg-sort-item]');
    if (!item) return;
    const direction = button.dataset.pkgMove;
    const adjacent = direction === 'up' ? item.previousElementSibling : item.nextElementSibling;
    if (!adjacent) return;
    if (direction === 'up') list.insertBefore(item, adjacent);
    else list.insertBefore(adjacent, item);
    refresh(); button.focus();
    live.textContent = `${item.querySelector('.pkg-sort-label strong').textContent} sekarang urutan ${rows().indexOf(item) + 1}.`;
  });
  // Desktop: drag only from the dedicated grip. No accidental drag while
  // tapping a form input, scrolling a touch screen, or choosing a plan.
  list.addEventListener('mousedown', event => {
    const item = event.target.closest('[data-pkg-sort-item]');
    if (item) item.dataset.gripActive = event.target.closest('.pkg-sort-grip') ? '1' : '0';
  });
  list.addEventListener('dragstart', event => {
    const item = event.target.closest('[data-pkg-sort-item]');
    if (!item || coarse.matches || narrow.matches || item.dataset.gripActive !== '1') {
      event.preventDefault(); return;
    }
    dragged = item;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', item.dataset.id);
    item.classList.add('is-dragging');
  });
  list.addEventListener('dragover', event => {
    if (!dragged) return;
    const target = event.target.closest('[data-pkg-sort-item]');
    if (!target || target === dragged) return;
    event.preventDefault(); event.dataTransfer.dropEffect = 'move';
    const bounds = target.getBoundingClientRect();
    if (event.clientY < bounds.top + bounds.height / 2) list.insertBefore(dragged, target);
    else list.insertBefore(dragged, target.nextSibling);
  });
  list.addEventListener('drop', event => {
    if (!dragged) return;
    event.preventDefault();
    const label = dragged.querySelector('.pkg-sort-label strong').textContent;
    refresh();
    live.textContent = `${label} sekarang urutan ${rows().indexOf(dragged) + 1}.`;
  });
  list.addEventListener('dragend', () => {
    if (dragged) dragged.classList.remove('is-dragging');
    dragged = null; refresh();
  });
  coarse.addEventListener?.('change', refresh);
  narrow.addEventListener?.('change', refresh);
  refresh();
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => { init(); initOrder(); initDetail(); }, {once: true});
else { init(); initOrder(); initDetail(); }
