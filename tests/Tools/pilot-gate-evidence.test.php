<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/rel-verify/SGP-PILOT-GATE-EVIDENCE-VERIFY.php';

function verifyAssert(bool $ok, string $label): void
{
    if (!$ok) {
        throw new RuntimeException('ASSERT_FAILED_' . $label);
    }
}
function summaryFixture(string $id): array
{
    $e = PilotGateEvidenceVerify::INPUTS[$id];
    return [
        'delivery' => $e['delivery'], 'run_id' => $e['run_id'],
        'payload_sha256' => str_repeat('a', 64),
        'prior_summary' => [
            'package' => $e['delivery'], 'run_id' => $e['run_id'],
            'baseline' => ['baseline' => $e['baseline']],
            'pilot_go' => 'HOLD', 'main_apply' => 'APPLIED_VERIFIED',
            'stages' => [
                'candidate_extract' => ['state' => 'PASS'],
                'php_lint' => ['state' => 'PASS'],
                'source_transaction_self_test' => ['state' => 'PASS'],
                'main_apply' => ['state' => 'PASS'],
                'full_regression' => ['state' => 'PASS'],
                'fig_p4_pdf_focused' => ['state' => 'PASS'],
                'fig_p4_browser_clock' => ['state' => 'PASS'],
            ],
            'source_after' => ['counts' => ['ADD' => 0, 'UPDATE' => 0, 'SKIP' => 6, 'CONFLICT' => 0]],
        ],
        'private_notes' => 'canary-unapproved@example.net TOKEN=SECRET_NOT_FOR_SHARE question-answer=XYZ',
    ];
}
function writeFixture(string $root, string $id, array $data): string
{
    $path = $root . '/' . PilotGateEvidenceVerify::INPUTS[$id]['relative_path'];
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }
    file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    return $path;
}
function evaluateFixture(array $m, string $root): array
{
    return PilotGateEvidenceVerify::evaluate($m, $root, '2026-11-24T13:00:00Z');
}

$root = sys_get_temp_dir() . '/sg-gate-test-' . bin2hex(random_bytes(6));
if (!mkdir($root, 0700) || file_put_contents($root . '/artisan', 'synthetic fake project') === false) {
    throw new RuntimeException('SYNTHETIC_TEST_ROOT_FAILED');
}
$checks = 0;
$test = function (string $label, callable $fn) use (&$checks): void {
    $fn(); $checks++; echo 'PASS ' . $label . PHP_EOL;
};
$m = PilotGateEvidenceVerify::sampleManifest();
$test('GI03 exact six GP and three PR; GP06 HOLD', function () use ($m, $root): void {
    $r = evaluateFixture($m, $root)['report'];
    verifyAssert(array_keys($r['gate_records']) === PilotGateEvidenceVerify::IDS, 'GP_IDS');
    verifyAssert(array_keys($r['pr_records']) === PilotGateEvidenceVerify::PRS, 'PR_IDS');
    verifyAssert($r['gp06'] === 'HOLD' && $r['pilot_go'] === false, 'NO_PILOT_GO');
});
$test('GI04 exact AC-FIG 01 through 12 from source crosswalk', function () use ($m, $root): void {
    $r = evaluateFixture($m, $root)['report'];
    verifyAssert(count($r['fig_records']) === 12, 'CASE_COUNT');
    verifyAssert(array_keys($r['fig_records']) === \App\Support\FigP4OperationalEvidence::CASES, 'CASE_ORDER');
    verifyAssert($r['fig_records']['AC-FIG-12']['technical_evidence_status'] === 'BLOCKED_NO_INDEPENDENT_TARGET', 'V02_BLOCKED');
});
$test('GI05 missing latest evidence NOT PASS', function () use ($m, $root): void {
    $r = evaluateFixture($m, $root)['report'];
    verifyAssert($r['evidence']['fig_ops']['state'] === 'MISSING_EVIDENCE', 'MISSING_FIG_OPS');
    verifyAssert($r['fig_records']['AC-FIG-07']['technical_evidence_status'] === 'NOT_PROVEN', 'NO_FIG_EVIDENCE_REUSE');
});
foreach (array_keys(PilotGateEvidenceVerify::INPUTS) as $id) {
    writeFixture($root, $id, summaryFixture($id));
}
$test('GI06 valid but unpinned evidence is UNSEALED', function () use ($m, $root): void {
    $r = evaluateFixture($m, $root)['report'];
    verifyAssert($r['evidence']['pilot_access']['state'] === 'UNSEALED_HASH', 'UNSEALED');
    verifyAssert($r['evidence']['fig_ops']['state'] === 'UNSEALED_HASH', 'UNSEALED_FIG');
});
$pinned = $m;
foreach ($pinned['inputs'] as &$entry) {
    $entry['expected_sha256'] = hash_file('sha256', $root . '/' . $entry['relative_path']);
    $entry['collected_at_utc'] = '2026-11-24T12:00:00Z';
}
unset($entry);
$test('GI07 pin allows synthetic reported PASS but no final approval', function () use ($pinned, $root): void {
    $r = evaluateFixture($pinned, $root)['report'];
    verifyAssert($r['evidence']['fig_ops']['state'] === 'HASH_MATCHED_REPORTED_PASS', 'PINNED');
    verifyAssert($r['fig_records']['AC-FIG-07']['technical_evidence_status'] === 'PARTIAL_SYNTHETIC_CLASS_PDF', 'ONLY_PARTIAL');
    verifyAssert($r['fig_records']['AC-FIG-07']['final_gate_status'] === 'OPEN_EXTERNAL_SIGNOFF', 'NO_CROSSWALK_AUTOPASS');
});
$test('GI06 tampered bytes are flagged', function () use ($pinned, $root): void {
    $id = 'pilot_access'; $path = $root . '/' . PilotGateEvidenceVerify::INPUTS[$id]['relative_path'];
    $before = file_get_contents($path);
    file_put_contents($path, $before . ' ');
    $r = evaluateFixture($pinned, $root);
    verifyAssert($r['invalid'] && $r['report']['evidence'][$id]['state'] === 'HASH_MISMATCH', 'TAMPER');
    file_put_contents($path, $before);
});
$test('GI06 malformed JSON rejected', function () use ($m, $root): void {
    $id = 'pilot_access'; $path = $root . '/' . PilotGateEvidenceVerify::INPUTS[$id]['relative_path'];
    $before = file_get_contents($path);
    file_put_contents($path, '{broken-broken-not-json');
    $r = evaluateFixture($m, $root);
    verifyAssert($r['invalid'] && $r['report']['evidence'][$id]['state'] === 'MALFORMED_EVIDENCE_JSON', 'JSON');
    file_put_contents($path, $before);
});
$test('GI06 wrong run or baseline rejected', function () use ($m, $root): void {
    $wrong = $m; $wrong['inputs'][0]['expected_run_id'] = '000000000000';
    $r = evaluateFixture($wrong, $root);
    verifyAssert($r['invalid'] && $r['report']['evidence']['pilot_access']['state'] === 'MANIFEST_PROVENANCE_CONFLICT', 'WRONG_RUN');
    $id = 'pilot_access'; $path = $root . '/' . PilotGateEvidenceVerify::INPUTS[$id]['relative_path'];
    $data = summaryFixture($id); $data['prior_summary']['baseline']['baseline'] = str_repeat('f', 40);
    writeFixture($root, $id, $data);
    $r2 = evaluateFixture($m, $root);
    verifyAssert($r2['invalid'] && $r2['report']['evidence'][$id]['state'] === 'SUMMARY_PROVENANCE_CONFLICT', 'WRONG_BASELINE');
    writeFixture($root, $id, summaryFixture($id));
});
$test('GI06 duplicate evidence IDs rejected', function () use ($m, $root): void {
    $wrong = $m; $wrong['inputs'][1] = $wrong['inputs'][0];
    $r = evaluateFixture($wrong, $root);
    verifyAssert($r['invalid'], 'DUPLICATE');
});
$test('GI15 path traversal blocked before read', function () use ($m, $root): void {
    $wrong = $m; $wrong['inputs'][0]['relative_path'] = '.siapegawai-backup/../../.env';
    $r = evaluateFixture($wrong, $root);
    verifyAssert($r['invalid'] && $r['report']['evidence']['pilot_access']['state'] === 'MANIFEST_PROVENANCE_CONFLICT', 'TRAVERSAL');
});
$test('GI15 symbolic link input cannot be used', function () use ($m, $root): void {
    $path = $root . '/' . PilotGateEvidenceVerify::INPUTS['pilot_access']['relative_path'];
    $saved = file_get_contents($path); unlink($path);
    $created = function_exists('symlink') && @symlink($root . '/artisan', $path);
    if ($created) {
        $r = evaluateFixture($m, $root);
        verifyAssert($r['invalid'] && $r['report']['evidence']['pilot_access']['state'] === 'UNSAFE_EVIDENCE_PATH', 'SYMLINK');
        unlink($path);
    }
    file_put_contents($path, $saved);
});
$test('GI06 corrupt stage declaration rejected', function () use ($m, $root): void {
    $id = 'fig_ops'; $data = summaryFixture($id);
    $data['prior_summary']['stages']['main_apply']['state'] = 'NOT_RUN';
    writeFixture($root, $id, $data);
    $r = evaluateFixture($m, $root);
    verifyAssert($r['invalid'] && $r['report']['evidence'][$id]['state'] === 'SUMMARY_PROVENANCE_CONFLICT', 'FALSE_PASS');
    writeFixture($root, $id, summaryFixture($id));
});
$test('GI08 edition/media/legal digest changes make review stale', function () use ($pinned, $root): void {
    $changed = $pinned;
    foreach ($changed['current_binding'] as $k => $_) { $changed['current_binding'][$k] = str_repeat('a', 64); }
    $changed['reviewed_binding'] = $changed['current_binding'];
    $changed['current_binding']['edition_sha256'] = str_repeat('b', 64);
    $r = evaluateFixture($changed, $root);
    verifyAssert($r['report']['binding_status'] === 'STALE_REVIEW_REQUIRED', 'STALE');
    verifyAssert($r['report']['gate_records']['GP-02']['final_gate_status'] !== 'PASS', 'NO_STALE_APPROVAL');
});
$test('GI09 claimed human approval not authenticated', function () use ($m, $root): void {
    $wrong = $m;
    $wrong['human_signoffs'][] = ['reviewer' => 'canary-unapproved@example.net', 'approved' => true, 'final_gate_status' => 'PASS'];
    $r = evaluateFixture($wrong, $root);
    verifyAssert($r['invalid'] && $r['report']['untrusted_approval_claim'] === true, 'SELF_SIGNED');
    verifyAssert($r['report']['gp06'] === 'HOLD', 'GO_FAIL_CLOSED');
});
$test('GI10-14 all GP remain non-final with only synthetic evidence', function () use ($pinned, $root): void {
    $r = evaluateFixture($pinned, $root)['report'];
    foreach ($r['gate_records'] as $id => $g) {
        verifyAssert($g['final_gate_status'] !== 'PASS' && $g['review_decision'] === 'NOT_AUTHENTICATED', $id);
    }
    verifyAssert($r['public_go'] === false && $r['sales_go'] === false && $r['seo_go'] === false, 'ALL_FLAGS_HOLD');
});
$test('GI17 old and future timestamps rejected or stale', function () use ($pinned, $root): void {
    $stale = $pinned; $stale['inputs'][0]['collected_at_utc'] = '2025-01-01T00:00:00Z';
    $r = evaluateFixture($stale, $root)['report'];
    verifyAssert($r['evidence']['pilot_access']['state'] === 'STALE_EVIDENCE', 'STALE_EVIDENCE');
    $future = $pinned; $future['inputs'][0]['collected_at_utc'] = '2030-01-01T00:00:00Z';
    $q = evaluateFixture($future, $root);
    verifyAssert($q['invalid'] && $q['report']['evidence']['pilot_access']['state'] === 'INVALID_OR_FUTURE_EVIDENCE_TIME', 'FUTURE');
});
$test('GI15 share-safe serializer contains no canary secrets or paths', function () use ($pinned, $root): void {
    $r = evaluateFixture($pinned, $root)['report'];
    $json = json_encode($r, JSON_THROW_ON_ERROR);
    foreach (['canary-unapproved@example.net', 'SECRET_NOT_FOR_SHARE', 'question-answer=XYZ', '.env', 'SHARE-SUMMARY.json', 'E:/'] as $secret) {
        verifyAssert(!str_contains($json, $secret), 'REDACTION_' . $secret);
    }
});
$test('GI17 deterministic snapshot for identical inputs and as-of', function () use ($pinned, $root): void {
    $x = evaluateFixture($pinned, $root)['report'];
    $y = evaluateFixture($pinned, $root)['report'];
    verifyAssert(hash('sha256', json_encode($x)) === hash('sha256', json_encode($y)), 'DETERMINISM');
});
$test('GI16 CLI emits report only within bounded untracked project folder', function () use ($root): void {
    $runId = '0123456789ab';
    ob_start();
    $a = PilotGateEvidenceVerify::cli(['tool.php', '--project', $root, '--run-id', $runId, '--init']);
    ob_end_clean();
    ob_start();
    $b = PilotGateEvidenceVerify::cli(['tool.php', '--project', $root, '--run-id', $runId, '--verify', '--as-of', '2026-11-24T13:00:00Z']);
    ob_end_clean();
    verifyAssert($a === 0 && $b === 0, 'CLI_CODE');
    $folder = $root . '/.siapegawai-backup/m4-pilot-gate-integration/' . $runId;
    $packet = file_get_contents($folder . '/GATE-REPORT.share.json');
    verifyAssert(is_string($packet) && !str_contains($packet, 'SECRET_NOT_FOR_SHARE'), 'CLI_REDACTION');
    verifyAssert(is_file($folder . '/OWNER-READINESS.md'), 'CLI_OWNER_PACKET');
});
$test('GI02/19 CLI writes none of source, env, DB, public feature flags', function () use ($root): void {
    verifyAssert(file_get_contents($root . '/artisan') === 'synthetic fake project', 'SOURCE_UNCHANGED');
    verifyAssert(!file_exists($root . '/.env'), 'ENV_UNTOUCHED');
    verifyAssert(!file_exists($root . '/pilot-go.flag'), 'NO_PILOT_FLAG');
});
$test('GI01 tool baseline is published Git HEAD; GI18 source transaction tested by installer', function (): void {
    verifyAssert(PilotGateEvidenceVerify::BASELINE === '698a944557167522899b1404b282089d2a5cee27', 'BASELINE_SHA');
});

echo 'GATE_EVIDENCE_UNIT_TESTS PASS (' . $checks . ' cases; no DB, network, outbound mail, or source writes)' . PHP_EOL;
