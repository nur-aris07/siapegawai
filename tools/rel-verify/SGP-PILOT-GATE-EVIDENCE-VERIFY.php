<?php

declare(strict_types=1);

/**
 * Offline, read-only M4 evidence validator. Only writes its own report files in
 * the explicitly scoped ignored .siapegawai-backup/m4-pilot-gate-integration/run-id.
 * Does NOT authenticate human approvals or authorize a real pilot.
 */
final class PilotGateEvidenceVerify
{
    public const IDS = ['GP-01', 'GP-02', 'GP-03', 'GP-04', 'GP-05', 'GP-06'];
    public const PRS = ['PR-08', 'PR-09', 'PR-10'];
    public const BASELINE = '698a944557167522899b1404b282089d2a5cee27';
    public const INPUTS = [
        'pilot_access' => [
            'run_id' => 'eaf00b732768',
            'delivery' => 'C-PILOT-ACCESS-01-INTEGRATED-20261124',
            'baseline' => '8ec3a9edcc7dbc9759dacdcd0e3a547844ea4218',
            'relative_path' => '.siapegawai-backup/m4-pilot-access/eaf00b732768/SHARE-SUMMARY.json',
        ],
        'learning_ops' => [
            'run_id' => 'bbfc460b6dc9',
            'delivery' => 'C-PILOT-LEARNING-OPS-01-INTEGRATED-20261124',
            'baseline' => '02835ee316123f8b9c2dd67f76e7c546ea330270',
            'relative_path' => '.siapegawai-backup/m4-pilot-learning-ops/bbfc460b6dc9/SHARE-SUMMARY.json',
        ],
        'fig_ops' => [
            'run_id' => '702f7909a88a',
            'delivery' => 'C-PILOT-OPS-FIG-01-INTEGRATED-20261124',
            'baseline' => 'cb4d9b72035213fca3ba9281fa38ed3c3fcf9f12',
            'relative_path' => '.siapegawai-backup/m4-pilot-ops-fig-closure/702f7909a88a/SHARE-SUMMARY.json',
        ],
    ];
    private const MAX_MANIFEST = 65536;
    private const MAX_EVIDENCE = 2097152;
    private const AGE_DAYS = 90;

    public static function sampleManifest(): array
    {
        $entries = [];
        foreach (self::INPUTS as $id => $expected) {
            $entries[] = [
                'id' => $id, 'relative_path' => $expected['relative_path'],
                'expected_sha256' => null,
                'collected_at_utc' => null,
                'environment' => 'local_synthetic',
                'evidence_owner_role' => 'engineering',
                'expected_run_id' => $expected['run_id'],
                'expected_delivery' => $expected['delivery'],
                'expected_baseline' => $expected['baseline'],
            ];
        }
        return [
            'schema' => 'SGP-M4-GATE-EVIDENCE-1',
            'inputs' => $entries,
            'current_binding' => [
                'edition_sha256' => null, 'media_sha256' => null,
                'legal_sha256' => null, 'policy_sha256' => null,
            ],
            'reviewed_binding' => null,
            'human_signoffs' => [],
        ];
    }

    private static function validHash(mixed $s): bool
    {
        return is_string($s) && preg_match('/^[a-f0-9]{64}$/D', $s) === 1;
    }
    private static function validRun(mixed $s): bool
    {
        return is_string($s) && preg_match('/^[a-f0-9]{12}$/D', $s) === 1;
    }
    private static function safeRelative(mixed $name): bool
    {
        if (!is_string($name) || strlen($name) > 240 || str_contains($name, '\\') || str_contains($name, ':') || str_starts_with($name, '/') || str_contains($name, '//')) {
            return false;
        }
        $segments = explode('/', $name);
        foreach ($segments as $segment) {
            if (in_array($segment, ['', '.', '..'], true) || !preg_match('/^[A-Za-z0-9_.-]+$/D', $segment)) {
                return false;
            }
        }
        return true;
    }
    private static function safePath(string $root, string $relative): ?string
    {
        if (!self::safeRelative($relative) || !str_starts_with($relative, '.siapegawai-backup/')) {
            return null;
        }
        $cursor = $root;
        foreach (explode('/', $relative) as $segment) {
            $cursor .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($cursor)) {
                return null;
            }
        }
        if (!is_file($cursor)) {
            return $cursor;
        }
        $resolved = realpath($cursor);
        $realRoot = realpath($root);
        $norm = static fn (string $p): string => PHP_OS_FAMILY === 'Windows' ? strtolower(str_replace('\\', '/', $p)) : $p;
        if ($resolved === false || $realRoot === false || !str_starts_with($norm($resolved), $norm($realRoot) . (PHP_OS_FAMILY === 'Windows' ? '/' : DIRECTORY_SEPARATOR))) {
            return null;
        }
        return $cursor;
    }
    private static function parseUtc(mixed $date): ?int
    {
        if (!is_string($date) || !preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/D', $date)) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $date, new DateTimeZone('UTC'));
        return $parsed && $parsed->format('Y-m-d\TH:i:s\Z') === $date ? $parsed->getTimestamp() : null;
    }
    private static function fixedError(string $code): array
    {
        return ['state' => $code, 'technical_evidence_status' => 'NOT_PROVEN'];
    }
    /** @return array{report:array, invalid:bool} */
    public static function evaluate(array $manifest, string $root, string $asOf): array
    {
        $asOfTs = self::parseUtc($asOf);
        if ($asOfTs === null) {
            throw new RuntimeException('AS_OF_UTC_INVALID');
        }
        $entries = $manifest['inputs'] ?? null;
        if (($manifest['schema'] ?? null) !== 'SGP-M4-GATE-EVIDENCE-1' || !is_array($entries) || count($entries) !== 3 || !array_is_list($entries)) {
            throw new RuntimeException('MANIFEST_SCHEMA_INVALID');
        }
        $seen = []; $runs = []; $results = []; $rawAccepted = []; $invalid = false;
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                $invalid = true; continue;
            }
            $id = $entry['id'] ?? null;
            if (!is_string($id) || !array_key_exists($id, self::INPUTS) || isset($seen[$id])) {
                $invalid = true; continue;
            }
            $seen[$id] = true;
            $expect = self::INPUTS[$id];
            $item = ['id' => $id, 'environment' => 'local_synthetic', 'owner_role' => 'engineering',
                'source_run' => $expect['run_id'], 'expected_baseline' => $expect['baseline'],
                'sha256' => null, 'age_days' => null, 'state' => 'NOT_PROVEN',
                'technical_evidence_status' => 'NOT_PROVEN'];
            foreach (['relative_path', 'expected_run_id', 'expected_delivery', 'expected_baseline'] as $key) {
                if (($entry[$key] ?? null) !== ($key === 'expected_run_id' ? $expect['run_id'] : ($key === 'expected_delivery' ? $expect['delivery'] : ($key === 'expected_baseline' ? $expect['baseline'] : $expect['relative_path'])))) {
                    $item['state'] = 'MANIFEST_PROVENANCE_CONFLICT'; $invalid = true; break;
                }
            }
            if ($item['state'] === 'MANIFEST_PROVENANCE_CONFLICT') {
                $results[$id] = $item; continue;
            }
            if (isset($runs[$expect['run_id']])) {
                $item['state'] = 'DUPLICATE_RUN_ID'; $invalid = true;
                $results[$id] = $item; continue;
            }
            $runs[$expect['run_id']] = true;
            if (($entry['environment'] ?? null) !== 'local_synthetic' || ($entry['evidence_owner_role'] ?? null) !== 'engineering') {
                $item['state'] = 'ENVIRONMENT_OR_OWNER_CONFLICT'; $invalid = true;
                $results[$id] = $item; continue;
            }
            $path = self::safePath($root, $entry['relative_path']);
            if ($path === null) {
                $item['state'] = 'UNSAFE_EVIDENCE_PATH'; $invalid = true;
                $results[$id] = $item; continue;
            }
            if (!is_file($path)) {
                $item['state'] = 'MISSING_EVIDENCE';
                $results[$id] = $item; continue;
            }
            if (!is_readable($path) || filesize($path) > self::MAX_EVIDENCE || filesize($path) < 10) {
                $item['state'] = 'UNREADABLE_OR_OVERSIZE'; $invalid = true;
                $results[$id] = $item; continue;
            }
            $json = file_get_contents($path);
            if ($json === false || !preg_match('//u', $json)) {
                $item['state'] = 'INVALID_UTF8'; $invalid = true;
                $results[$id] = $item; continue;
            }
            $digest = hash('sha256', $json); $item['sha256'] = $digest;
            $pin = $entry['expected_sha256'] ?? null;
            if ($pin !== null && !self::validHash($pin)) {
                $item['state'] = 'MALFORMED_HASH_PIN'; $invalid = true;
                $results[$id] = $item; continue;
            }
            if ($pin !== null && !hash_equals($pin, $digest)) {
                $item['state'] = 'HASH_MISMATCH'; $invalid = true;
                $results[$id] = $item; continue;
            }
            try {
                $data = json_decode($json, true, 256, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $item['state'] = 'MALFORMED_EVIDENCE_JSON'; $invalid = true;
                $results[$id] = $item; continue;
            }
            if (!is_array($data) || !is_array($data['prior_summary'] ?? null)) {
                $item['state'] = 'INVALID_SUMMARY_SCHEMA'; $invalid = true;
                $results[$id] = $item; continue;
            }
            $prior = $data['prior_summary'];
            $actualBaseline = $prior['baseline']['baseline'] ?? null;
            $source = $prior['source_after'] ?? null;
            if (($data['run_id'] ?? null) !== $expect['run_id'] || ($prior['run_id'] ?? null) !== $expect['run_id'] ||
                ($data['delivery'] ?? null) !== $expect['delivery'] || ($prior['package'] ?? null) !== $expect['delivery'] ||
                $actualBaseline !== $expect['baseline'] || !is_array($source) ||
                ($prior['main_apply'] ?? null) !== 'APPLIED_VERIFIED' ||
                ($prior['pilot_go'] ?? null) !== 'HOLD' ||
                ($source['counts']['CONFLICT'] ?? null) !== 0 ||
                ($source['counts']['ADD'] ?? null) !== 0 ||
                ($source['counts']['UPDATE'] ?? null) !== 0 ||
                !self::validHash($data['payload_sha256'] ?? null) ||
                !is_array($prior['stages'] ?? null) || ($prior['stages']['main_apply']['state'] ?? null) !== 'PASS') {
                $item['state'] = 'SUMMARY_PROVENANCE_CONFLICT'; $invalid = true;
                $results[$id] = $item; continue;
            }
            foreach (['candidate_extract', 'php_lint', 'source_transaction_self_test', 'main_apply'] as $stage) {
                if (($prior['stages'][$stage]['state'] ?? null) !== 'PASS') {
                    $item['state'] = 'REQUIRED_QA_STAGE_NOT_PASS'; $invalid = true; break;
                }
            }
            if ($item['state'] === 'REQUIRED_QA_STAGE_NOT_PASS') {
                $results[$id] = $item; continue;
            }
            $date = $entry['collected_at_utc'] ?? null;
            if ($date !== null) {
                $t = self::parseUtc($date);
                if ($t === null || $t > $asOfTs) {
                    $item['state'] = 'INVALID_OR_FUTURE_EVIDENCE_TIME'; $invalid = true;
                    $results[$id] = $item; continue;
                }
                $item['age_days'] = (int) floor(($asOfTs - $t) / 86400);
                if ($item['age_days'] > self::AGE_DAYS) {
                    $item['state'] = 'STALE_EVIDENCE';
                    $results[$id] = $item; continue;
                }
            }
            $rawAccepted[$id] = $prior;
            $item['technical_evidence_status'] = 'PASS_REPORTED_SYNTHETIC_NOT_HUMAN_APPROVAL';
            $item['state'] = $pin === null ? 'UNSEALED_HASH' : ($date === null ? 'UNKNOWN_EVIDENCE_AGE' : 'HASH_MATCHED_REPORTED_PASS');
            $results[$id] = $item;
        }
        if (count($seen) !== 3) {
            $invalid = true;
        }
        foreach (self::INPUTS as $id => $expect) {
            if (!isset($results[$id])) {
                $results[$id] = ['id' => $id, 'source_run' => $expect['run_id'], 'state' => 'MISSING_MANIFEST_SLOT',
                    'environment' => 'local_synthetic', 'technical_evidence_status' => 'NOT_PROVEN'];
                $invalid = true;
            }
        }
        $bindings = $manifest['current_binding'] ?? null;
        $reviewed = $manifest['reviewed_binding'] ?? null;
        $stale = false;
        if (!is_array($bindings) || array_keys($bindings) !== ['edition_sha256', 'media_sha256', 'legal_sha256', 'policy_sha256']) {
            $invalid = true;
        } else {
            foreach ($bindings as $digest) {
                if ($digest !== null && !self::validHash($digest)) {
                    $invalid = true;
                }
            }
        }
        if ($reviewed !== null) {
            if (!is_array($reviewed) || !is_array($bindings)) {
                $invalid = true;
            } else {
                foreach (['edition_sha256', 'media_sha256', 'legal_sha256', 'policy_sha256'] as $key) {
                    if (!self::validHash($reviewed[$key] ?? null) || !self::validHash($bindings[$key] ?? null) || $reviewed[$key] !== $bindings[$key]) {
                        $stale = true;
                    }
                }
            }
        }
        $signoffs = $manifest['human_signoffs'] ?? null;
        if (!is_array($signoffs) || !array_is_list($signoffs)) {
            $invalid = true;
            $signoffs = [];
        }
        $untrustedApproval = false;
        foreach ($signoffs as $row) {
            if (!is_array($row)) { $untrustedApproval = true; continue; }
            if (($row['approved'] ?? false) === true || ($row['final_gate_status'] ?? '') === 'PASS' || ($row['decision'] ?? '') === 'GO' || ($row['self_certified'] ?? false) === true) {
                $untrustedApproval = true;
            }
        }
        if ($untrustedApproval) {
            $invalid = true;
        }
        $figStages = [];
        // Source-linked crosswalk: only a verified, time-bounded, SHA-pinned
        // ops report is eligible as reported *synthetic* FIG evidence.
        if (($results['fig_ops']['state'] ?? '') === 'HASH_MATCHED_REPORTED_PASS') {
            foreach (($rawAccepted['fig_ops']['stages'] ?? []) as $stageId => $info) {
                if (is_string($stageId) && is_array($info) && ($info['state'] ?? '') === 'PASS') {
                    $figStages[$stageId] = 'PASS';
                }
            }
            if (($figStages['fig_p4_browser_clock'] ?? '') === 'PASS') {
                $figStages['fig_p4_fairness_synthetic'] = 'PASS';
            }
        }
        require_once dirname(__DIR__, 2) . '/app/Support/FigP4OperationalEvidence.php';
        $crosswalk = \App\Support\FigP4OperationalEvidence::reconcile($figStages);
        if (($crosswalk['case_count'] ?? null) !== 12 || array_keys($crosswalk['cases'] ?? []) !== \App\Support\FigP4OperationalEvidence::CASES) {
            throw new RuntimeException('FIG_CASES_CONTRACT_BROKEN');
        }
        $gateReasons = [
            'GP-01' => ['PARTIAL_AWAITING_HUMAN_PROTOCOL_SIGNOFF', 'Final cohort/protocol/window and independent scope approval', 'Founder / PM'],
            'GP-02' => ['OPEN_HUMAN_ACADEMIC_AND_MEDIA_RIGHTS', 'Original 30 items and 59 assets require independent keys/explanations/alt/rights review', 'Academic reviewer'],
            'GP-03' => ['OPEN_LEGAL_AND_REAL_TARGET', 'Draft policies; operator, processor, TLS and retention approval missing', 'Legal / Operator'],
            'GP-04' => ['BLOCKED_NO_INDEPENDENT_DB_AND_MEDIA_TARGET', 'Independent DB and both private-media restore with approved RTO/RPO not evidenced', 'Ops / Tech'],
            'GP-05' => ['OPEN_REAL_DEVICES_THRESHOLDS_AND_SIGNOFF', 'Physical device/network, agreed fairness thresholds and FIG signatures missing', 'QA / Founder'],
            'GP-06' => ['HOLD', 'Out-of-band Founder+Tech decision is required even after GP01..05', 'Founder + Tech'],
        ];
        $gates = [];
        $gateLinks = ['GP-01'=>'pilot_access','GP-02'=>'learning_ops',
            'GP-03'=>'pilot_access','GP-04'=>'fig_ops','GP-05'=>'fig_ops','GP-06'=>null];
        foreach ($gateReasons as $id => [$state, $reason, $actor]) {
            $link = $gateLinks[$id];
            $gates[$id] = [
                'id' => $id, 'final_gate_status' => $state, 'technical_evidence_status' => 'LOCAL_ONLY',
                'evidence_ids' => array_keys(self::INPUTS), 'linked_artifact_id' => $link,
                'linked_version_hash' => null, 'evidence_sha256' => $link !== null ? ($results[$link]['sha256'] ?? null) : null,
                'source_run' => $link !== null ? self::INPUTS[$link]['run_id'] : null,
                'environment' => 'local_synthetic', 'reviewer_role' => $actor,
                'review_decision' => 'NOT_AUTHENTICATED', 'reviewed_at_utc' => null,
                'blocking_reason' => $reason, 'next_owner_action' => $actor,
                'supersedes' => null, 'stale_review' => $stale,
            ];
        }
        $prs = [
            'PR-08' => ['id'=>'PR-08','state' => 'PREPARED_UNREVIEWED_EVIDENCE',
                'evidence_ids'=>array_keys(self::INPUTS),'pilot_go' => false],
            'PR-09' => ['id'=>'PR-09','state' => 'REGISTER_COMPLETE_GATES_OPEN',
                'evidence_ids'=>array_keys(self::INPUTS),'pilot_go' => false],
            'PR-10' => ['id'=>'PR-10','state' => 'BLOCKED_PRE_M5_DECISION',
                'evidence_ids'=>array_keys(self::INPUTS),'pilot_go' => false],
        ];
        $fig = [];
        foreach (\App\Support\FigP4OperationalEvidence::CASES as $id) {
            $fig[$id] = [
                'id' => $id, 'linked_artifact_id' => 'fig_ops',
                'linked_version_hash'=>null,'evidence_sha256'=>$results['fig_ops']['sha256']??null,
                'source_run' => self::INPUTS['fig_ops']['run_id'],
                'environment'=>'local_synthetic','reviewer_role'=>'QA / Tech',
                'technical_evidence_status' => $crosswalk['cases'][$id],
                'final_gate_status' => 'OPEN_EXTERNAL_SIGNOFF',
                'review_decision' => 'NOT_AUTHENTICATED','reviewed_at_utc'=>null,
                'supersedes'=>null,
            ];
        }
        return ['invalid' => $invalid,
            'report' => [
                'schema' => 'SGP-M4-GATE-SHARE-1',
                'as_of_utc' => $asOf, 'baseline_git' => self::BASELINE,
                'local_tool_validation' => $invalid ? 'FAIL_LOCAL_VALIDATION' : 'PASS_LOCAL_WITH_EXTERNAL_GAPS',
                'evidence' => $results,
                'gate_records' => $gates, 'pr_records' => $prs, 'fig_records' => $fig,
                'binding_status' => $stale ? 'STALE_REVIEW_REQUIRED' : 'NO_INDEPENDENTLY_AUTHENTICATED_REVIEW',
                'untrusted_approval_claim' => $untrustedApproval,
                'gp06' => 'HOLD', 'pilot_go' => false, 'public_go' => false,
                'seo_go' => false, 'sales_go' => false,
                'blocked_external' => ['GP-02', 'GP-03', 'GP-04', 'GP-05', 'GP-06'],
                'limitations' => [
                    'File hash validates exact bytes only; it does not authenticate source semantics or human reviewer identity.',
                    'No pilot activation, remote upload, SQL, real participant export or automatic approval.',
                    'Real-target restore, physical fairness and academic/legal approvals remain external.',
                ],
            ],
        ];
    }

    private static function atomicWrite(string $file, string $body): void
    {
        $tmp = $file . '.tmp';
        if (file_exists($file) || is_link($file) || file_exists($tmp) || is_link($tmp)) {
            throw new RuntimeException('REPORT_PATH_ALREADY_EXISTS');
        }
        if (file_put_contents($tmp, $body, LOCK_EX) !== strlen($body) || !rename($tmp, $file)) {
            throw new RuntimeException('REPORT_WRITE_FAILED');
        }
    }
    private static function json(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }
    private static function help(): void
    {
        echo "Offline gate evidence verifier\n";
        echo "  --project PATH --run-id aabbccddeeff --init [--collect]\n";
        echo "  --project PATH --run-id aabbccddeeff --verify [--as-of YYYY-MM-DDTHH:MM:SSZ]\n";
        echo "Writes ONLY ignored .siapegawai-backup/m4-pilot-gate-integration/<run-id>/ private packet.\n";
    }
    public static function cli(array $argv): int
    {
        if (in_array('--help', $argv, true)) { self::help(); return 0; }
        $args = [];
        foreach (array_slice($argv, 1) as $i => $val) {
            if (str_starts_with($val, '--') && str_contains($val, '=')) {
                [$name, $v] = explode('=', $val, 2); $args[$name] = $v;
            } elseif (str_starts_with($val, '--')) {
                $args[$val] = $argv[$i + 2] ?? null;
            }
        }
        $project = realpath((string) ($args['--project'] ?? ''));
        $runId = $args['--run-id'] ?? null;
        if ($project === false || !is_file($project . '/artisan') || !self::validRun($runId)) {
            throw new RuntimeException('PROJECT_OR_RUN_ID_INVALID');
        }
        $scope = $project . '/.siapegawai-backup/m4-pilot-gate-integration';
        foreach (['.siapegawai-backup', '.siapegawai-backup/m4-pilot-gate-integration', '.siapegawai-backup/m4-pilot-gate-integration/' . $runId] as $relative) {
            $p = $project . '/' . $relative;
            if (is_link($p) || (file_exists($p) && !is_dir($p))) {
                throw new RuntimeException('OUTPUT_SYMLINK_OR_NOT_DIRECTORY');
            }
            if (is_dir($p)) {
                $actual = realpath($p);
                $normalize = static fn (string $x): string => PHP_OS_FAMILY === 'Windows'
                    ? strtolower(str_replace('\\', '/', $x)) : $x;
                if ($actual === false || $normalize($actual) !== $normalize($p)) {
                    throw new RuntimeException('OUTPUT_SCOPE_REDIRECTED');
                }
            }
        }
        $runDir = $scope . '/' . $runId;
        if (!is_dir($runDir) && !mkdir($runDir, 0700, true)) {
            throw new RuntimeException('OUTPUT_SCOPE_MISSING');
        }
        $manifestPath = $runDir . '/manifest.json';
        if (is_link($manifestPath)) {
            throw new RuntimeException('MANIFEST_SYMLINK');
        }
        if (array_key_exists('--init', $args)) {
            $template = self::sampleManifest();
            if (array_key_exists('--collect', $args)) {
                foreach ($template['inputs'] as &$input) {
                    $source = self::safePath($project, $input['relative_path']);
                    if ($source !== null && is_file($source) && is_readable($source) && filesize($source) >= 10 && filesize($source) <= self::MAX_EVIDENCE) {
                        $input['expected_sha256'] = hash_file('sha256', $source);
                        $input['collected_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
                        $input['pin_origin'] = 'LOCAL_COLLECTION_ONLY_UNREVIEWED';
                    }
                }
                unset($input);
            }
            self::atomicWrite($manifestPath, self::json($template));
            echo "MANIFEST_CREATED (local byte pins are NOT reviewer attestation)\n";
            return 0;
        }
        if (!array_key_exists('--verify', $args)) {
            self::help(); return 2;
        }
        if (!is_file($manifestPath) || filesize($manifestPath) > self::MAX_MANIFEST || filesize($manifestPath) < 20) {
            throw new RuntimeException('MANIFEST_MISSING_OR_OVERSIZE');
        }
        $bytes = file_get_contents($manifestPath);
        if ($bytes === false || !preg_match('//u', $bytes)) {
            throw new RuntimeException('MANIFEST_UTF8_INVALID');
        }
        try { $manifest = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR); }
        catch (Throwable) { throw new RuntimeException('MANIFEST_JSON_INVALID'); }
        $asOf = (string) ($args['--as-of'] ?? gmdate('Y-m-d\TH:i:s\Z'));
        $eval = self::evaluate($manifest, $project, $asOf);
        $report = $eval['report'];
        // Only stable, generated summary fields; never echo raw provenance content.
        $encoded = self::json($report);
        $report['report_sha256'] = hash('sha256', $encoded);
        self::atomicWrite($runDir . '/GATE-REPORT.share.json', self::json($report));
        $md = "# SIAPEGAWAI — Owner M4 Gate Readiness (offline)\n\n"
            . "Generated as-of: `{$asOf}`. This is not an authorization or signature.\n\n"
            . "**Local validator:** `{$report['local_tool_validation']}` · **GP-06:** `HOLD` · **Pilot/public/SEO/sales:** `HOLD`\n\n"
            . "| Gate | Disposition | Next role |\n|---|---|---|\n";
        foreach ($report['gate_records'] as $id => $g) {
            $md .= "| {$id} | {$g['final_gate_status']} | {$g['next_owner_action']} |\n";
        }
        $md .= "\n## Input evidence\n\n| ID | Local validation | Evidence SHA-256 |\n|---|---|---|\n";
        foreach ($report['evidence'] as $id => $e) {
            $md .= '| ' . $id . ' | ' . ($e['state'] ?? 'NOT_PROVEN') . ' | ' . ($e['sha256'] ?? 'MISSING') . " |\n";
        }
        $md .= "\nOriginal private files remain local. Hash matches prove exact bytes only, not independent academic/legal approval.\n";
        self::atomicWrite($runDir . '/OWNER-READINESS.md', $md);
        echo 'GATE_EVIDENCE ' . $report['local_tool_validation'] . ' / GP06 HOLD / no domain writes' . PHP_EOL;
        echo 'Share only: ' . $runDir . '/GATE-REPORT.share.json' . PHP_EOL;
        return $eval['invalid'] ? 3 : 0;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try { exit(PilotGateEvidenceVerify::cli($argv)); }
    catch (Throwable $e) {
        fwrite(STDERR, 'GATE_EVIDENCE_ERROR ' . preg_replace('/[^A-Z0-9_]/', '_', $e->getMessage()) . PHP_EOL);
        exit(2);
    }
}
