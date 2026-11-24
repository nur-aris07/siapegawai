import test from 'node:test';
import assert from 'node:assert/strict';
import { summarizeFairness } from '../../tools/rel-verify/SGP-FIG-P4-QQ-FAIRNESS-BOUNDS.mjs';

test('clock uncertainty preserved; synthetic observation is never final pass', () => {
  const r = summarizeFairness({
    essentialImageCount: 2,
    expiresServerMs: 100000,
    essentialDecodedClientMs: 90000,
    samples: [{ clientSentEpochMs: 89800, clientReceivedEpochMs: 90200, serverDateHeaderMs: 90000 }],
    attemptId: 'secret-identifier', privateUrl: '/private/secret',
  });
  assert.equal(r.status, 'PASS_SYNTHETIC_OBSERVABILITY_ONLY');
  assert.ok(r.usableWindowLowerMs > 0);
  assert.ok(r.usableWindowLowerMs <= r.usableWindowUpperMs);
  assert.equal(r.issueToReadyMs, null);
  assert.equal(r.v03Final, 'OPEN_REAL_DEVICE_AND_THRESHOLD');
  assert.ok(!JSON.stringify(r).includes('secret'));
});

test('expired image readiness reports a blocking defect', () => {
  const r = summarizeFairness({ essentialImageCount: 1, expiresServerMs: 85000,
    essentialDecodedClientMs: 90000, samples: [{clientSentEpochMs: 89800,
      clientReceivedEpochMs: 90200, serverDateHeaderMs: 90000}] });
  assert.equal(r.status, 'FAIL_BLOCKING_IMAGE_NOT_READY_BEFORE_DEADLINE');
  assert.equal(r.usableWindowLowerMs, 0);
  assert.equal(r.usableWindowUpperMs, 0);
});

test('missing or inconsistent capture is inconclusive, not falsely green', () => {
  assert.equal(summarizeFairness({}).status, 'INCONCLUSIVE_MISSING_MEASUREMENT');
  assert.equal(summarizeFairness({ essentialImageCount: 1, expiresServerMs: 99000,
    essentialDecodedClientMs: 90000, samples: [{clientSentEpochMs: 90000,
      clientReceivedEpochMs: 80000,serverDateHeaderMs: 90000}] }).status,
    'INCONCLUSIVE_CLOCK_OR_CAPTURE');
});
