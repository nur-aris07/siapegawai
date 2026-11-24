// Q-BULK-IMPORT-01 C-04: progressive enhancement; no inline handlers or unsafe HTML.
const dialog = document.getElementById('question-import-help');
if (dialog instanceof HTMLDialogElement) {
  const openButton = document.querySelector('[data-import-dialog-open]');
  let lastFocused = null;
  openButton?.addEventListener('click', () => {
    lastFocused = document.activeElement;
    dialog.showModal();
    dialog.querySelector('[data-import-dialog-close]')?.focus();
  });
  dialog.querySelectorAll('[data-import-dialog-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('close', () => { if (lastFocused instanceof HTMLElement) lastFocused.focus(); });
  dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
}
const zone = document.querySelector('[data-qi-dropzone]');
const fileInput = document.getElementById('import-file');
const selected = document.querySelector('[data-qi-selected]');
const modes = [...document.querySelectorAll('[data-qi-mode]')];
const currentMode = () => modes.find((mode) => mode.checked)?.value || 'csv';
const updateMode = () => {
  if (!(fileInput instanceof HTMLInputElement)) return;
  const zip = currentMode() === 'zip';
  fileInput.accept = zip ? '.zip,application/zip' : '.csv,text/csv';
  fileInput.value = '';
  const labels = {
    title: zip ? 'Tarik dan lepas file ZIP di sini' : 'Tarik dan lepas file CSV di sini',
    description: zip ? 'ZIP memuat questions.csv, media.csv, dan berkas gambar.' : 'CSV UTF-8; BOM didukung.',
    choose: zip ? 'Pilih file ZIP' : 'Pilih file CSV',
    hint: zip ? document.querySelector('[data-qi-upload-hint]')?.dataset.qiHintZip
      : document.querySelector('[data-qi-upload-hint]')?.dataset.qiHintCsv,
  };
  for (const [selector, value] of [
    ['[data-qi-drop-title]', labels.title],
    ['[data-qi-drop-description]', labels.description],
    ['[data-qi-choose-text]', labels.choose],
    ['[data-qi-upload-hint]', labels.hint],
  ]) {
    const element = document.querySelector(selector);
    if (element && typeof value === 'string') element.textContent = value;
  }
  const symbol = zone?.querySelector('.qi-upload-icon use');
  if (symbol) symbol.setAttribute('href', zip ? '#ic-package' : '#ic-clipboard');
  zone?.setAttribute('aria-label', zip ? 'Area unggah file ZIP' : 'Area unggah file CSV');
  if (selected) selected.textContent = 'Belum ada file dipilih';
};
modes.forEach((mode) => mode.addEventListener('change', updateMode));
if (fileInput instanceof HTMLInputElement) updateMode();
if (zone && fileInput instanceof HTMLInputElement) {
  const updateLabel = () => {
    const file = fileInput.files?.[0];
    if (selected) selected.textContent = file ? `${file.name} (${new Intl.NumberFormat('id-ID').format(file.size)} byte)` : 'Belum ada file dipilih';
  };
  fileInput.addEventListener('change', updateLabel);
  for (const type of ['dragenter', 'dragover']) zone.addEventListener(type, (event) => {
    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
    zone.classList.add('is-dragover');
  });
  for (const type of ['dragleave', 'dragend']) zone.addEventListener(type, (event) => {
    event.preventDefault(); zone.classList.remove('is-dragover');
  });
  zone.addEventListener('drop', (event) => {
    event.preventDefault(); zone.classList.remove('is-dragover');
    const files = event.dataTransfer?.files;
    if (!files || files.length !== 1) { if (selected) selected.textContent = 'Pilih tepat satu file sesuai mode.'; return; }
    // The server validates extension, UTF-8, byte limit and CSV structure regardless of client hints.
    if (!files[0].name.toLowerCase().endsWith(currentMode() === 'zip' ? '.zip' : '.csv')) {
      if (selected) selected.textContent = 'Ekstensi file tidak sesuai mode CSV/ZIP.';
      return;
    }
    try { fileInput.files = files; updateLabel(); }
    catch { if (selected) selected.textContent = 'Browser tidak mendukung drag-and-drop ini; pilih file melalui tombol.'; }
  });
}
const copyButton = document.querySelector('[data-qi-copy-errors]');
const copyMessage = document.querySelector('[data-qi-copy-result]');
copyButton?.addEventListener('click', async () => {
  const value = document.getElementById('qi-copy-errors-data')?.textContent?.trim();
  if (!value) return;
  if (!navigator.clipboard?.writeText) {
    if (copyMessage) copyMessage.textContent = 'Salin otomatis tidak didukung oleh browser ini. Gunakan unduh CSV error.';
    return;
  }
  try { await navigator.clipboard.writeText(value); if (copyMessage) copyMessage.textContent = 'Seluruh daftar kesalahan berhasil disalin.'; }
  catch { if (copyMessage) copyMessage.textContent = 'Gagal mengakses clipboard. Gunakan unduh CSV error.'; }
});
