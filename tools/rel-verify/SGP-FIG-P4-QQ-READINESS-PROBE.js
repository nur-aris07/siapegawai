/*
 * FIG-P4 / V-03 read-only browser probe for a currently visible QQ question.
 * Use as DevTools Snippet on a synthetic QA account, after opening the timed
 * question with network throttling already enabled. Does not fetch/retry media,
 * change timers, send credentials/URLs, or touch server state.
 * Limitations: started AFTER HTML/JS arrival; cannot infer exact server issue
 * time, device clock skew, or user fairness from one sample. V-03 stays OPEN.
 */
(() => {
  'use strict';
  const exam = document.querySelector('#qq-exam[data-expires-ms][data-server-now-ms]');
  if (!exam) {
    console.warn('FIG-P4: open a timed QQ question first. No changes made.');
    return;
  }
  const images = Array.from(exam.querySelectorAll('img[src]')).filter((img) =>
    img.closest('.fig-participant-figure') || /\/gambar\//.test(img.currentSrc || img.src)
  );
  const expires = Number(exam.dataset.expiresMs);
  const serverAtRender = Number(exam.dataset.serverNowMs);
  const navigation = performance.getEntriesByType('navigation')[0];
  const started = performance.now();
  const state = {
    label: 'FIG-P4 V03 READINESS / OBSERVATION ONLY',
    imageCount: images.length,
    serverBudgetAtRenderMs: Number.isFinite(expires - serverAtRender) ? Math.round(expires - serverAtRender) : null,
    browserTimeToReadyMs: null,
    brokenImages: null,
    observedAtMs: null,
    responseEndMs: navigation?.responseEnd ?? null,
    // No auth token, object path, media URL, participant identifier or cookie.
  };
  let done = false;
  const summarize = () => {
    if (done) return;
    const ready = images.filter((img) => img.complete && img.naturalWidth > 0);
    const broken = images.filter((img) => img.complete && img.naturalWidth === 0);
    state.observedAtMs = Math.round(performance.now());
    state.brokenImages = broken.length;
    state.readyImages = ready.length;
    const total = images.length;
    if (ready.length + broken.length === total || performance.now() - started >= 15000) {
      done = true;
      clearInterval(interval);
      state.browserTimeToReadyMs = ready.length === total ? Math.round(performance.now() - started) : null;
      state.observerLimitMs = 15000;
      state.status = total === 0 ? 'NO_FIGURAL_IMAGES' : broken.length ? 'MISSING_OR_BROKEN_IMAGE'
        : ready.length !== total ? 'SOME_IMAGES_STILL_LOADING' : 'ALL_IMAGES_READY_AFTER_PROBE_START';
      console.info(`FIG-P4 V03 STATUS: ${state.status}`);
      console.table(state);
      console.warn('FIG-P4 V03 remains OPEN. Record screen/video, network profile, issued/remaining seconds and actual operator verdict separately.');
    }
  };
  const interval = setInterval(summarize, 100);
  summarize();
})();
