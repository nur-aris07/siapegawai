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
    if (!files || files.length !== 1) { if (selected) selected.textContent = 'Pilih tepat satu file CSV.'; return; }
    // The server validates extension, UTF-8, byte limit and CSV structure regardless of client hints.
    if (!files[0].name.toLowerCase().endsWith('.csv')) {
      if (selected) selected.textContent = 'Hanya file .csv yang dapat diunggah.';
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
