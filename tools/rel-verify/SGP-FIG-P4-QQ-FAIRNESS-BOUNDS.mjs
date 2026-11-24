/*
 * FIG-P4 V03: conservative server/browser clock uncertainty analysis.
 * Does not edit timers, send requests, read cookies or log identifiers.
 * Input must contain only redacted timestamp measurements from synthetic QA.
 */
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';

export function summarizeFairness(input) {
  if (!input || typeof input !== 'object') throw Error('MEASUREMENT_REQUIRED');
  const { expiresServerMs, essentialDecodedClientMs, samples, essentialImageCount } = input;
  if (!Number.isFinite(expiresServerMs) || !Number.isFinite(essentialDecodedClientMs)
      || !Number.isInteger(essentialImageCount) || essentialImageCount < 1
      || !Array.isArray(samples) || samples.length < 1) {
    return { status: 'INCONCLUSIVE_MISSING_MEASUREMENT', v03Final: 'OPEN' };
  }
  let offsetMin = -Infinity, offsetMax = Infinity;
  let valid = 0;
  for (const sample of samples.slice(0, 8)) {
    const { clientSentEpochMs, clientReceivedEpochMs, serverDateHeaderMs } = sample || {};
    if (![clientSentEpochMs, clientReceivedEpochMs, serverDateHeaderMs].every(Number.isFinite)
        || clientReceivedEpochMs < clientSentEpochMs || clientReceivedEpochMs - clientSentEpochMs > 20000) continue;
    // HTTP Date is generally second resolution; the server event occurred
    // at an unknown point between send and receive.
    const lower = serverDateHeaderMs - 1000 - clientReceivedEpochMs;
    const upper = serverDateHeaderMs + 1000 - clientSentEpochMs;
    offsetMin = Math.max(offsetMin, lower);
    offsetMax = Math.min(offsetMax, upper);
    valid++;
  }
  if (!valid || offsetMax < offsetMin) {
    return { status: 'INCONCLUSIVE_CLOCK_OR_CAPTURE', v03Final: 'OPEN', validSamples: valid };
  }
  const readyEarliestServerMs = essentialDecodedClientMs + offsetMin;
  const readyLatestServerMs = essentialDecodedClientMs + offsetMax;
  const remainingLowerMs = Math.max(0, Math.floor(expiresServerMs - readyLatestServerMs));
  const remainingUpperMs = Math.max(0, Math.ceil(expiresServerMs - readyEarliestServerMs));
  return {
    status: remainingUpperMs <= 0 ? 'FAIL_BLOCKING_IMAGE_NOT_READY_BEFORE_DEADLINE'
      : 'PASS_SYNTHETIC_OBSERVABILITY_ONLY',
    validSamples: valid,
    essentialImageCount,
    clockUncertaintyMs: Math.ceil(offsetMax - offsetMin),
    usableWindowLowerMs: remainingLowerMs,
    usableWindowUpperMs: remainingUpperMs,
    issueToReadyMs: null, // Server issuance not independently witnessed.
    v03Final: 'OPEN_REAL_DEVICE_AND_THRESHOLD',
    note: 'Bounded server-time estimate; legibility, real devices, thresholds, and issuance remain unproven.',
  };
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  const fs = await import('node:fs');
  if (process.argv.length !== 3 || !process.argv[2].endsWith('.json')) {
    process.stderr.write('Usage: node SGP-FIG-P4-QQ-FAIRNESS-BOUNDS.mjs redacted-measurement.json\n');
    process.exit(2);
  }
  const raw = fs.readFileSync(process.argv[2], 'utf8');
  const input = JSON.parse(raw);
  // Explicit whitelist: never mirror input identifiers/URLs into shared output.
  process.stdout.write(JSON.stringify(summarizeFairness(input), null, 2) + '\n');
}
