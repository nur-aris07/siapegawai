/*
 * FIG-P4 V-03 / observation-only Quick Quiz essential-image readiness probe.
 * Paste into DevTools Snippets after opening a synthetic QA question under
 * a preselected network throttle. Never sends network requests or changes
 * timers, answer state, local storage, session cookies or DOM.
 *
 * The server 'now' attribute is generated during HTML response, not at
 * browser arrival, and device clocks may differ. Consequently this reports
 * observational metrics only and MUST NOT report a fairness PASS on its own.
 */
(() => {
  'use strict';
  const exam = document.querySelector('#qq-exam[data-expires-ms][data-server-now-ms]');
  if (!exam || exam.dataset.phase !== 'question') {
    console.warn('FIG-P4 V03: Open an active timed QQ question before running the probe. No changes.');
    return;
  }
  const images = [...exam.querySelectorAll('img[src]')].filter((img) =>
    img.closest('.fig-participant-figure') || /\/gambar\//.test(img.currentSrc || img.src)
  );
  const expiry = Number(exam.dataset.expiresMs);
  const issuedServerNow = Number(exam.dataset.serverNowMs);
  if (!Number.isFinite(expiry) || !Number.isFinite(issuedServerNow) || expiry <= issuedServerNow) {
    console.warn('FIG-P4 V03: Invalid server expiry/clock fields. No changes.');
    return;
  }
  const started = performance.now();
  const navigation = performance.getEntriesByType('navigation')[0] || null;
  const resourceEndMs = (img) => {
    const key = img.currentSrc || img.src;
    // Lookups only; resource URLs are never emitted. Cached/AJAX timings can be absent.
    const entries = performance.getEntriesByName(key, 'resource');
    return entries.length ? Math.round(entries[entries.length - 1].responseEnd) : null;
  };
  const report = {
    status: 'OBSERVING',
    verdict: 'V03_OPEN_MEASUREMENTS_ONLY',
    pageWidthPx: document.documentElement.clientWidth,
    hiddenTab: document.visibilityState !== 'visible',
    essentialImageCount: images.length,
    serverBudgetAtHtmlGenerationMs: Math.round(expiry - issuedServerNow),
    // Relative browser timings: NOT a substitute for server-authoritative issue time.
    probeStartFromNavigationMs: Math.round(started),
    navigationResponseEndMs: navigation ? Math.round(navigation.responseEnd) : null,
    essentialResourceResponseEndMs: null,
    essentialReadyAfterProbeMs: null,
    readyAtProbeStart: images.filter((img) => img.complete && img.naturalWidth > 0).length,
    decodedImages: null,
    brokenImages: null,
    unfinishedImages: null,
    observerMaxMs: 20000,
    context: 'Potential AJAX question: navigation/resource timings may describe earlier page activity.',
    clock: 'Server expires and serverNow are from HTML; device clock offset not established.',
    privacy: 'No image URLs, attempt IDs, cookies, passwords, storage keys or answer values included.',
  };
  if (images.length === 0) {
    report.status = 'NO_FIGURAL_IMAGES';
    console.info('FIG-P4 V03 STATUS: NO_FIGURAL_IMAGES / NOT FAIRNESS PASS');
    console.table(report);
    return;
  }
  let stop = false;
  const finish = () => {
    if (stop) return;
    const elapsed = performance.now() - started;
    const ready = images.filter((img) => img.complete && img.naturalWidth > 0);
    const broken = images.filter((img) => img.complete && img.naturalWidth === 0);
    const unfinished = images.length - ready.length - broken.length;
    if (unfinished > 0 && elapsed < report.observerMaxMs) return;
    stop = true;
    clearInterval(interval);
    report.essentialReadyAfterProbeMs = unfinished === 0 && broken.length === 0 ? Math.round(elapsed) : null;
    report.decodedImages = ready.length;
    report.brokenImages = broken.length;
    report.unfinishedImages = unfinished;
    report.essentialResourceResponseEndMs = images.map(resourceEndMs)
      .filter((v) => v !== null).reduce((max, v) => Math.max(max, v), 0) || null;
    report.status = broken.length > 0 ? 'BROKEN_ESSENTIAL_IMAGE'
      : unfinished > 0 ? 'ESSENTIAL_IMAGE_TIMEOUT' : 'IMAGES_READY_OBSERVED';
    console.info(`FIG-P4 V03 STATUS: ${report.status} / V03_OPEN_MEASUREMENTS_ONLY`);
    console.table(report);
    console.warn('V-03 requires real network HAR, elapsed time from server issuance, device profile and signed usable-window policy. This snippet is observation only.');
  };
  const interval = setInterval(finish, 100);
  for (const img of images) {
    if (typeof img.decode === 'function') {
      img.decode().then(finish).catch(finish);
    }
  }
  finish();
})();
