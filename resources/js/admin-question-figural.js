/* FIG-P2 C-03: unified hierarchical media composer.
 * Authoritative security/validation stays in Laravel. No CDN, inline handlers or remote URLs.
 * Native desktop drag-and-drop, accessible cross-group move menu + same-group Up/Down on touch/keyboard.
 */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('[data-fig-editor-form]');
  const composer = form?.querySelector('[data-fig-composer]');
  const preview = form?.querySelector('[data-fig-preview]');
  if (!form || !composer || !preview) return;

  const hierarchy = composer.querySelector('[data-fig-hierarchy]');
  const input = composer.querySelector('[data-fig-files]');
  const drop = composer.querySelector('[data-fig-dropzone]');
  const dropHint = composer.querySelector('[data-fig-drop-hint]');
  const status = composer.querySelector('[data-fig-status]');
  const hidden = composer.querySelector('[data-fig-hidden-fields]');
  const count = composer.querySelector('[data-fig-count]');
  const submitButtons = [...form.querySelectorAll('button[type="submit"]')];
  const slotNames = new Map([
    ['stem', 'Pertanyaan'], ['option_A', 'Pilihan A'], ['option_B', 'Pilihan B'],
    ['option_C', 'Pilihan C'], ['option_D', 'Pilihan D'], ['option_E', 'Pilihan E'],
    ['explanation', 'Pembahasan'],
  ]);
  const groups = new Map([...hierarchy.querySelectorAll('[data-fig-group]')]
    .map(group => [group.dataset.figGroup, { group, list: group.querySelector('[data-fig-group-list]') }]));
  const allowed = new Set(['image/jpeg', 'image/png', 'image/webp']);
  const added = new Map();
  let nextId = 1;
  let busy = 0;
  let context = 'qq';
  let version = 'draft';
  let pointerSort = null;
  let fileDragDepth = 0;
  const el = (tag, className = '', text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = String(text);
    return node;
  };
  const field = (name, value) => {
    const node = el('input');
    node.type = 'hidden'; node.name = name; node.value = String(value ?? '');
    return node;
  };
  const cards = () => [...hierarchy.querySelectorAll('[data-fig-persisted],[data-fig-new]')];
  const activeItems = () => cards().filter(item => !item.classList.contains('is-pending-remove'));
  const slotOf = item => item.closest('[data-fig-group]')?.dataset.figGroup ?? 'stem';
  const listFor = slot => groups.get(slot)?.list;

  function inform(message, invalid = false) {
    status.textContent = message;
    status.classList.toggle('is-error', invalid);
    drop.classList.toggle('is-invalid', invalid);
    input.setAttribute('aria-invalid', invalid ? 'true' : 'false');
  }
  function setBusy(flag) {
    busy = Math.max(0, busy + (flag ? 1 : -1));
    submitButtons.forEach(button => { button.disabled = busy > 0; });
  }
  function refresh() {
    let total = 0;
    for (const [slot, {group, list}] of groups) {
      const available = [...list.children].filter(item => !item.classList.contains('is-pending-remove'));
      total += available.length;
      group.querySelector('[data-fig-group-count]').textContent = `${available.length} gambar`;
      group.classList.toggle('is-empty', available.length === 0);
      [...list.children].forEach(item => {
        const slotInput = item.querySelector('[data-fig-slot]');
        if (slotInput) slotInput.value = slot;
        if (item.classList.contains('is-pending-remove')) return;
        const rank = available.indexOf(item) + 1;
        const positionInput = item.querySelector('[data-fig-position]');
        if (positionInput) positionInput.value = String(rank);
        const rankLabel = item.querySelector('[data-fig-rank]');
        if (rankLabel) rankLabel.textContent = available.length > 1 ? `Urutan ${rank}` : '';
        const up = item.querySelector('[data-fig-move="up"]');
        const down = item.querySelector('[data-fig-move="down"]');
        if (up) { up.disabled = rank <= 1; up.hidden = available.length <= 1; }
        if (down) { down.disabled = rank === available.length; down.hidden = available.length <= 1; }
      });
    }
    count.textContent = String(total);
    renderPreview();
  }
  function control(label, action) {
    const b = el('button', 'fig-composer-control', label);
    b.type = 'button'; b.dataset.figMove = action;
    return b;
  }
  function moveMenu() {
    const menu = el('details', 'fig-composer-move-menu');
    menu.append(el('summary', '', 'Pindahkan ke…'));
    const choices = el('div', 'fig-composer-move-options');
    for (const [slot, title] of slotNames) {
      const b = el('button', '', title);
      b.type = 'button'; b.dataset.figDestination = slot;
      choices.append(b);
    }
    menu.append(choices);
    return menu;
  }
  function addCard(entry) {
    const card = el('li', 'fig-composer-card');
    card.dataset.figNew = String(entry.id);
    card.draggable = false;
    const thumb = el('div', 'fig-composer-thumb');
    const image = el('img'); image.src = entry.src; image.alt = 'Gambar belum disimpan';
    thumb.append(image);
    const detail = el('div', 'fig-composer-card-detail');
    const top = el('div', 'fig-composer-card-top');
    const grip = el('span', 'fig-composer-grip', '⠿');
    grip.title = 'Geser untuk memindahkan atau mengurutkan gambar';
    grip.setAttribute('aria-hidden', 'true');
    const name = el('strong', '', entry.file.name); name.title = entry.file.name;
    const rank = el('span', 'fig-composer-position'); rank.dataset.figRank = '';
    top.append(grip, name, rank);
    const slotInput = field('', entry.slot); slotInput.removeAttribute('name'); slotInput.dataset.figSlot = '';
    const controls = el('div', 'fig-composer-fields');
    const altLabel = el('label', '', 'Teks alternatif');
    const alt = el('input'); alt.type = 'text'; alt.maxLength = 300;
    alt.placeholder = 'Deskripsikan unsur penting'; alt.dataset.figAlt = '';
    altLabel.append(alt);
    const captionLabel = el('label', '', 'Keterangan');
    const caption = el('input'); caption.type = 'text'; caption.maxLength = 300;
    caption.placeholder = 'Opsional'; caption.dataset.figCaption = '';
    captionLabel.append(caption);
    controls.append(altLabel, captionLabel);
    const actions = el('div', 'fig-composer-card-actions');
    actions.append(control('Naik', 'up'), control('Turun', 'down'), moveMenu(), control('Hapus', 'remove'));
    detail.append(top, slotInput, controls, actions);
    card.append(thumb, detail);
    listFor(entry.slot).append(card);
    return card;
  }
  function applyAction(item, action) {
    if (action === 'remove') {
      if (item.hasAttribute('data-fig-new')) {
        added.delete(Number(item.dataset.figNew));
        item.remove();
      } else {
        const removing = item.classList.toggle('is-pending-remove');
        item.querySelector('[data-fig-remove-value]').value = removing ? '1' : '0';
        item.querySelector('[data-fig-remove]').textContent = removing ? 'Batalkan lepas' : 'Jadwalkan lepas';
      }
    } else if (!item.classList.contains('is-pending-remove')) {
      const list = item.parentElement;
      const siblings = [...list.children].filter(card => !card.classList.contains('is-pending-remove'));
      const i = siblings.indexOf(item);
      if (action === 'up' && i > 0) list.insertBefore(item, siblings[i - 1]);
      else if (action === 'down' && i >= 0 && i < siblings.length - 1) list.insertBefore(siblings[i + 1], item);
    }
    refresh();
  }
  hierarchy.addEventListener('click', event => {
    const control = event.target.closest('[data-fig-move],[data-fig-remove],[data-fig-destination]');
    const item = control?.closest('[data-fig-persisted],[data-fig-new]');
    if (!item) return;
    if (control.hasAttribute('data-fig-destination')) {
      const destination = control.dataset.figDestination;
      if (!groups.has(destination) || item.classList.contains('is-pending-remove')) return;
      const summary = item.querySelector('.fig-composer-move-menu summary');
      listFor(destination).append(item);
      item.querySelector('.fig-composer-move-menu').open = false;
      refresh();
      summary?.focus();
      inform(`Gambar dipindahkan ke ${slotNames.get(destination)}. Susunan akan disimpan bersama draft.`);
    } else applyAction(item, control.dataset.figMove || 'remove');
  });
  hierarchy.addEventListener('input', refresh);
  hierarchy.addEventListener('change', refresh);

  // Desktop: pointer-based sorting from the handle across all named group lanes.
  // This avoids native HTML5 drag failure over nested inputs; mobile/keyboard use
  // the explicit Move To + Up/Down controls, not touch gestures that scroll the page.
  hierarchy.addEventListener('pointerdown', event => {
    const handle = event.target.closest('.fig-composer-grip');
    const item = handle?.closest('[data-fig-persisted],[data-fig-new]');
    if (!item || event.button !== 0 || item.classList.contains('is-pending-remove')
      || window.matchMedia('(pointer:coarse)').matches) return;
    pointerSort = {item, id:event.pointerId, startX:event.clientX, startY:event.clientY, active:false};
  });
  window.addEventListener('pointermove', event => {
    if (!pointerSort || pointerSort.id !== event.pointerId) return;
    const drag = pointerSort;
    if (!drag.active) {
      if (Math.hypot(event.clientX - drag.startX, event.clientY - drag.startY) < 7) return;
      drag.active = true;
      drag.item.classList.add('is-dragging');
    }
    if (event.cancelable) event.preventDefault();
    // The moving card is pointer-transparent so group detection is stable.
    const underneath = document.elementFromPoint(event.clientX, event.clientY);
    const targetGroup = underneath?.closest('[data-fig-group]');
    hierarchy.querySelectorAll('.is-sort-target').forEach(g => g.classList.remove('is-sort-target'));
    if (!targetGroup) return;
    const targetList = targetGroup.querySelector('[data-fig-group-list]');
    targetGroup.classList.add('is-sort-target');
    const targetCard = underneath.closest('[data-fig-persisted],[data-fig-new]');
    if (targetCard && targetCard !== drag.item && targetCard.parentElement === targetList) {
      const mid = targetCard.getBoundingClientRect().top + targetCard.getBoundingClientRect().height / 2;
      targetList.insertBefore(drag.item, event.clientY > mid ? targetCard.nextSibling : targetCard);
    } else if (!targetCard || targetCard !== drag.item && targetCard.parentElement !== targetList) {
      targetList.append(drag.item);
    }
  }, {passive:false});
  function finishSort() {
    if (!pointerSort) return;
    const changed = pointerSort.active;
    pointerSort.item.classList.remove('is-dragging');
    pointerSort = null;
    hierarchy.querySelectorAll('.is-sort-target').forEach(g => g.classList.remove('is-sort-target'));
    if (changed) refresh();
  }
  window.addEventListener('pointerup', finishSort);
  window.addEventListener('pointercancel', finishSort);
  // Prevent the browser starting a competing native drag operation from nested content.
  hierarchy.addEventListener('dragstart', event => event.preventDefault());

  const readData = file => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result));
    reader.onerror = () => reject(new Error('Berkas gagal dibaca.'));
    reader.readAsDataURL(file);
  });
  const decodeData = src => new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve({width:image.naturalWidth, height:image.naturalHeight});
    image.onerror = () => reject(new Error('Isi gambar tidak dapat dibuka.'));
    image.src = src;
  });
  async function validate(file) {
    if (!allowed.has(file.type)) throw new Error(`Hanya JPEG, PNG, atau WebP. ${file.name} ditolak.`);
    if (!file.size || file.size > 5 * 1024 * 1024) throw new Error('Ukuran gambar harus 1 byte sampai 5 MB.');
    const bytes = new Uint8Array(await file.slice(0, 16).arrayBuffer());
    const png = bytes.length >= 8 && [137,80,78,71,13,10,26,10].every((n,i) => bytes[i] === n);
    const jpg = bytes.length >= 3 && bytes[0] === 255 && bytes[1] === 216 && bytes[2] === 255;
    const webp = bytes.length >= 12 && [82,73,70,70].every((n,i) => bytes[i] === n)
      && [87,69,66,80].every((n,i) => bytes[i + 8] === n);
    if (!(file.type === 'image/png' && png || file.type === 'image/jpeg' && jpg || file.type === 'image/webp' && webp))
      throw new Error(`Isi berkas tidak cocok dengan format gambar: ${file.name}`);
    const src = await readData(file);
    const size = await decodeData(src);
    if (size.width < 1 || size.height < 1 || size.width > 4096 || size.height > 4096
      || size.width * size.height > 12000000) throw new Error('Dimensi maksimum 4096 px / 12 megapiksel.');
    return src;
  }
  async function choose(files) {
    if (!files?.length) return;
    const candidates = [...files];
    if (candidates.length + added.size > 12) { inform('Maksimal 12 gambar baru per penyimpanan.', true); return; }
    setBusy(true); inform('Memeriksa isi berkas gambar…');
    try {
      const ready = [];
      for (const file of candidates) ready.push({file, src:await validate(file)});
      for (const entry of ready) {
        entry.id = nextId++; entry.slot = 'stem';
        added.set(entry.id, entry); addCard(entry);
      }
      inform(`${added.size} gambar baru siap. Geser ke bagian soal yang sesuai atau gunakan Pindahkan ke…`);
      refresh();
    } catch (error) { inform(error.message || 'Gambar tidak dapat digunakan.', true); }
    finally { setBusy(false); input.value = ''; }
  }
  input.addEventListener('change', () => choose(input.files));
  const isFiles = event => [...(event.dataTransfer?.types || [])].includes('Files');
  function highlightDrop(active) {
    drop.classList.toggle('is-over', active);
    dropHint.textContent = active ? 'Lepaskan gambar untuk menambahkan' : 'Seret gambar ke sini';
  }
  drop.addEventListener('dragenter', event => {
    if (!isFiles(event)) return;
    event.preventDefault(); fileDragDepth += 1; highlightDrop(true);
  });
  drop.addEventListener('dragover', event => {
    if (!isFiles(event)) return;
    event.preventDefault(); event.dataTransfer.dropEffect = 'copy'; highlightDrop(true);
  });
  drop.addEventListener('dragleave', event => {
    if (!isFiles(event)) return;
    fileDragDepth = Math.max(0, fileDragDepth - 1);
    if (fileDragDepth === 0 || !drop.contains(event.relatedTarget)) highlightDrop(false);
  });
  drop.addEventListener('drop', event => {
    event.preventDefault(); fileDragDepth = 0; highlightDrop(false);
    choose(event.dataTransfer?.files);
  });
  drop.addEventListener('keydown', event => {
    if ((event.key === 'Enter' || event.key === ' ') && event.target === drop) {
      event.preventDefault(); input.click();
    }
  });

  function saveFileControls() {
    if (typeof DataTransfer !== 'function') throw new Error('Browser membutuhkan dukungan upload multi-file Chrome/Edge terbaru.');
    refresh();
    const transfer = new DataTransfer();
    hidden.replaceChildren();
    for (const [slot, {list}] of groups) {
      const items = [...list.children].filter(item => !item.classList.contains('is-pending-remove'));
      if (items.length > 8) throw new Error(`${slotNames.get(slot)}: maksimal delapan gambar.`);
      items.forEach((item, index) => {
        const rank = index + 1;
        if (item.hasAttribute('data-fig-persisted')) {
          item.querySelector('[data-fig-slot]').value = slot;
          item.querySelector('[data-fig-position]').value = String(rank);
          return;
        }
        const entry = added.get(Number(item.dataset.figNew));
        if (!entry) throw new Error('Susunan gambar berubah. Muat ulang editor.');
        transfer.items.add(entry.file);
        hidden.append(field('media_slots[]',slot), field('media_positions[]',rank),
          field('media_alts[]',item.querySelector('[data-fig-alt]').value),
          field('media_captions[]',item.querySelector('[data-fig-caption]').value));
      });
    }
    input.files = transfer.files;
  }
  form.addEventListener('submit', event => {
    if (busy > 0) { event.preventDefault(); inform('Pemeriksaan berkas masih berlangsung.', true); return; }
    try { saveFileControls(); }
    catch (error) { event.preventDefault(); inform(error.message || 'Susunan belum siap disimpan.', true); }
  });

  const previewDisclosure = preview.querySelector('[data-fig-preview-disclosure]');
  const mobileQuery = window.matchMedia('(max-width: 760px)');
  const syncPreview = () => { previewDisclosure.open = !mobileQuery.matches; };
  if (mobileQuery.matches) previewDisclosure.open = false;
  mobileQuery.addEventListener?.('change', syncPreview);
  const contextButtons = [...preview.querySelectorAll('[data-fig-context]')];
  const versionButtons = [...preview.querySelectorAll('[data-fig-version]')];
  contextButtons.forEach(button => button.addEventListener('click', () => {
    context = button.dataset.figContext;
    contextButtons.forEach(other => {
      other.classList.toggle('is-active', other === button);
      other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
    });
    renderPreview();
  }));
  versionButtons.forEach(button => button.addEventListener('click', () => {
    version = button.dataset.figVersion;
    versionButtons.forEach(other => other.setAttribute('aria-pressed', other === button ? 'true' : 'false'));
    renderPreview();
  }));
  const paperQuestion = preview.querySelector('[data-fig-preview-question]');
  const paperOptions = preview.querySelector('[data-fig-preview-options]');
  const paperExplanation = preview.querySelector('[data-fig-preview-explanation]');
  const paperTitle = preview.querySelector('[data-fig-context-title]');
  function renderPreview() {
    if (!paperQuestion) return;
    const published = version === 'published' ? preview.querySelector('[data-fig-published-source]')?.content : null;
    const text = name => published
      ? published.querySelector(`[data-fig-published-${name}]`)?.textContent ?? ''
      : form.elements.namedItem(name)?.value ?? '';
    paperTitle.textContent = {qq:'Quick Quiz',cat:'CAT / Tryout',class:'Latihan Kelas'}[context] || 'Pratinjau';
    preview.dataset.context = context;
    const sourceMedia = slot => {
      if (published) return [...published.querySelectorAll('[data-fig-published-media]')]
        .filter(img => img.dataset.figPublishedMedia === slot)
        .sort((a,b) => Number(a.dataset.figOrder) - Number(b.dataset.figOrder))
        .map(img => ({src:img.src,alt:img.alt}));
      return activeItems().filter(item => slotOf(item) === slot).map(item => {
        const image = item.querySelector('.fig-composer-thumb img');
        return image ? {src:image.src,alt:item.querySelector('[data-fig-alt]')?.value || image.alt} : null;
      }).filter(Boolean);
    };
    const media = slot => sourceMedia(slot).map(image => {
      const img = el('img', 'fig-preview-media');
      img.src = image.src; img.alt = image.alt || 'Ilustrasi soal'; return img;
    });
    const body = String(text('body')).trim();
    const bodyMedia = media('stem');
    const parts = body ? [el('p','fig-preview-question-text',body)]
      : bodyMedia.length ? [] : [el('p','fig-preview-question-text','Pertanyaan belum diisi.')];
    paperQuestion.replaceChildren(...parts, ...bodyMedia);
    paperOptions.replaceChildren();
    for (const label of ['A','B','C','D','E']) {
      const answer = published ? published.querySelector(`[data-fig-published-option="${label}"]`)?.textContent || ''
        : String(text('option_' + label.toLowerCase()));
      const images = media('option_' + label);
      if (label === 'E' && !answer.trim() && !images.length) continue;
      const item = el('div', 'fig-preview-option');
      item.append(el('strong','fig-preview-option-letter',label));
      const content = el('div', 'fig-preview-option-content');
      if (answer.trim()) content.append(el('span','',answer));
      else if (!images.length) content.append(el('span','fig-preview-empty','Pilihan belum diisi'));
      content.append(...images); item.append(content); paperOptions.append(item);
    }
    const explanation = String(text('explanation'));
    const correct = published ? published.querySelector('[data-fig-published-correct]')?.textContent || ''
      : String(text('correct_option'));
    const explanationMedia = media('explanation');
    const explanationParts = explanation.trim() ? [el('p','',explanation.trim())]
      : explanationMedia.length ? [] : [el('p','','Pembahasan belum tersedia.')];
    paperExplanation.replaceChildren(el('strong','fig-preview-answer',correct ? 'Kunci: ' + correct : 'Kunci belum ditentukan'),
      ...explanationParts, ...explanationMedia);
  }
  form.addEventListener('input', event => { if (!event.target.closest('[data-fig-composer]')) renderPreview(); });
  form.addEventListener('change', renderPreview);
  refresh();
  window.addEventListener('pagehide', () => { added.clear(); hidden.replaceChildren(); }, {once:true});
});
