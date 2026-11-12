<?php
/**
 * REL-VERIFY-01C / v1.15 — two independent, read-only MySQL inventory comparisons.
 * NO restoration, writes, migration or user data export. Does not mark A-25 PASS.
 * Requires the MySQL CLI and two separately configured, read-only login paths.
 */
declare(strict_types=1);

const SGP_REL_C_BASELINE = 'cac9fa670e100155d6297ab654e7c1e971afed84';
const SGP_REL_C_MAX_TABLES = 200;

function usage(): void
{
    echo <<<'HELP'
REL-VERIFY-01C / v1.15 — Independent MySQL restore comparison (READ ONLY)

Default: DRY RUN. --run is required for any DB connections.
The tool NEVER performs mysqldump, import, migration, INSERT, DELETE or UPDATE.

Required when --run:
  --source-db=siapegawai_staging_source
  --restore-db=siapegawai_restore_verify_testing
  --source-login-path=sgp_staging_source_ro
  --restore-login-path=sgp_restore_verify_ro

Optional:
  --mysql-bin=mysql
  --out-dir=.siapegawai-backup/rel-verify-01c/db

Both login-path accounts MUST be SELECT-only on their respective DBs.
The target MUST be a disposable, already independently restored *_restore_verify_testing DB.
The source MUST be a *_source staging/testing DB, never the live DB.
This tool compares schema metadata, indexes, foreign keys, migrations and exact
per-table row counts; it does NOT compare row contents or prove restore success.
A-25 stays OPEN pending file parity, app/PDF checks, RTO/RPO and reviewer sign-off.
HELP;
}

function opts(array $argv): array
{
    $opts = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help') { $opts['help'] = true; continue; }
        if ($arg === '--run') { $opts['run'] = true; continue; }
        if (!preg_match('/^--([a-z-]+)=(.*)$/D', $arg, $m)) {
            throw new RuntimeException('Unknown argument. Use --help.');
        }
        if (isset($opts[$m[1]])) throw new RuntimeException('Duplicate argument --'.$m[1]);
        $opts[$m[1]] = $m[2];
    }
    $allowed = ['help', 'run', 'source-db', 'restore-db', 'source-login-path', 'restore-login-path', 'mysql-bin', 'out-dir'];
    foreach (array_keys($opts) as $k) if (!in_array($k, $allowed, true)) throw new RuntimeException('Unknown option --'.$k);
    return $opts;
}

function validateConfig(array $o): array
{
    foreach (['source-db', 'restore-db', 'source-login-path', 'restore-login-path'] as $k) {
        if (!isset($o[$k]) || $o[$k] === '') throw new RuntimeException('Missing required --'.$k);
    }
    $src = $o['source-db']; $dst = $o['restore-db'];
    if (!preg_match('/^siapegawai_[a-z0-9_]+_source$/D', $src)) {
        throw new RuntimeException('Source DB must be explicitly named siapegawai_*_source (staging/testing only).');
    }
    if (!preg_match('/^siapegawai_(?:[a-z0-9_]+_)?restore_verify_testing$/D', $dst)) {
        throw new RuntimeException('Restore DB must end in restore_verify_testing; never use app/production DB.');
    }
    if ($src === $dst) throw new RuntimeException('Source and restore DB must differ.');
    foreach (['source-login-path', 'restore-login-path'] as $k) {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/D', $o[$k])) {
            throw new RuntimeException('Invalid MySQL login path name: '.$k);
        }
    }
    if ($o['source-login-path'] === $o['restore-login-path']) {
        throw new RuntimeException('Source and restored DB require DIFFERENT read-only login paths.');
    }
    $bin = $o['mysql-bin'] ?? 'mysql';
    if ($bin === '' || str_contains($bin, "\n") || str_contains($bin, "\r")) {
        throw new RuntimeException('Invalid mysql client binary path.');
    }
    return [$src, $dst, $bin];
}

/** MySQL returns tab-separated, headerless rows. Never display raw stderr or query contents. */
function mysqlRead(string $bin, string $loginPath, string $db, string $sql, string $label): array
{
    $args = [
        $bin, '--login-path='.$loginPath, '--database='.$db,
        '--batch', '--skip-column-names', '--raw', '--silent', '--connect-timeout=8',
        '--default-character-set=utf8mb4', '--execute='.$sql,
    ];
    $spec = [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']];
    $proc = @proc_open($args, $spec, $pipes);
    if (!is_resource($proc)) throw new RuntimeException('Unable to invoke mysql CLI for '.$label.'; check PATH.');
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $status = proc_close($proc);
    // Do not expose raw stderr: client messages can contain hostname, user or secret paths.
    if ($status !== 0 || $out === false) {
        throw new RuntimeException('MySQL read-only query FAILED for '.$label.' (exit '.$status.'); check mysql login-path, SELECT grants, DB and client. STDERR redacted.');
    }
    if (strlen($out) > 4_000_000) throw new RuntimeException('Excessive metadata result for '.$label.'; stop and inspect offline.');
    $out = trim(str_replace("\r\n", "\n", $out));
    return $out === '' ? [] : explode("\n", $out);
}

function snapshot(string $bin, string $loginPath, string $db, string $label): array
{
    $actual = mysqlRead($bin, $loginPath, $db, 'START TRANSACTION READ ONLY; SELECT DATABASE(); COMMIT;', $label.' db guard');
    if (count($actual) !== 1 || trim($actual[0]) !== $db) {
        throw new RuntimeException('DB identity mismatch for '.$label.'; STOP.');
    }
    $tableNames = mysqlRead($bin, $loginPath, $db,
        "START TRANSACTION READ ONLY; SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME; COMMIT;",
        $label.' tables');
    if (!$tableNames || count($tableNames) > SGP_REL_C_MAX_TABLES) {
        throw new RuntimeException('Missing/excessive tables on '.$label.' (max 200); review DB selection.');
    }
    foreach ($tableNames as $name) {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/D', $name)) throw new RuntimeException('Unexpected table identifier; STOP.');
    }
    $schema = mysqlRead($bin, $loginPath, $db,
        "START TRANSACTION READ ONLY; SELECT CONCAT_WS('|', TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COALESCE(COLUMN_DEFAULT,'[SQL_NULL]'), EXTRA, COALESCE(COLLATION_NAME,'[SQL_NULL]')) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION; COMMIT;",
        $label.' columns');
    $indexes = mysqlRead($bin, $loginPath, $db,
        "START TRANSACTION READ ONLY; SELECT CONCAT_WS('|', TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE, COALESCE(SUB_PART,'[SQL_NULL]')) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX; COMMIT;",
        $label.' indexes');
    $constraints = mysqlRead($bin, $loginPath, $db,
        "START TRANSACTION READ ONLY; SELECT CONCAT_WS('|', TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION, COLUMN_NAME, COALESCE(REFERENCED_TABLE_NAME,'[SQL_NULL]'), COALESCE(REFERENCED_COLUMN_NAME,'[SQL_NULL]')) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION; COMMIT;",
        $label.' constraints');
    if (!in_array('migrations', $tableNames, true)) throw new RuntimeException('Missing Laravel migrations table in '.$label);
    $migrations = mysqlRead($bin, $loginPath, $db,
        'START TRANSACTION READ ONLY; SELECT CONCAT(migration,CHAR(9),batch) FROM `migrations` ORDER BY migration; COMMIT;',
        $label.' migrations');
    // Every SELECT explicitly runs in a READ ONLY transaction on the chosen database.
    $union = [];
    foreach ($tableNames as $name) {
        $union[] = "SELECT '".$name."' AS t, COUNT(*) AS n FROM `".$name."`";
    }
    $countsLines = mysqlRead($bin, $loginPath, $db,
        'START TRANSACTION READ ONLY; '.implode(' UNION ALL ', $union).'; COMMIT;', $label.' exact counts');
    $counts = [];
    foreach ($countsLines as $line) {
        $cells = explode("\t", $line);
        if (count($cells) !== 2 || !in_array($cells[0], $tableNames, true) || !ctype_digit($cells[1]) || isset($counts[$cells[0]])) {
            throw new RuntimeException('Unexpected aggregate output in '.$label.'; STOP.');
        }
        $counts[$cells[0]] = (int)$cells[1];
    }
    if (count($counts) !== count($tableNames)) throw new RuntimeException('Incomplete per-table counts in '.$label.'; STOP.');
    ksort($counts, SORT_STRING);
    return [
        'names'=>$tableNames,
        'counts'=>$counts,
        'column_sha256'=>hash('sha256', implode("\n", $schema)),
        'index_sha256'=>hash('sha256', implode("\n", $indexes)),
        'constraint_sha256'=>hash('sha256', implode("\n", $constraints)),
        'migration_sha256'=>hash('sha256', implode("\n", $migrations)),
        'migration_count'=>count($migrations),
    ];
}

function resultFor(array $source, array $restore): array
{
    $tableNamesEqual = $source['names'] === $restore['names'];
    $countMismatches = [];
    foreach (array_unique(array_merge(array_keys($source['counts']), array_keys($restore['counts']))) as $table) {
        if (($source['counts'][$table] ?? null) !== ($restore['counts'][$table] ?? null)) {
            // No table names, filenames, row values, IDs or credentials in report.
            $countMismatches[] = substr(hash('sha256', $table), 0, 12);
        }
    }
    sort($countMismatches);
    $checks = [
        'table_names_equal'=>$tableNamesEqual,
        'column_schema_equal'=>hash_equals($source['column_sha256'], $restore['column_sha256']),
        'indexes_equal'=>hash_equals($source['index_sha256'], $restore['index_sha256']),
        'key_constraints_equal'=>hash_equals($source['constraint_sha256'], $restore['constraint_sha256']),
        'migrations_equal'=>hash_equals($source['migration_sha256'], $restore['migration_sha256']),
        'exact_row_counts_equal'=>$countMismatches === [],
    ];
    return [
        'status'=>!in_array(false, $checks, true) ? 'PASS_COMPARISON_ONLY' : 'FLAG',
        'checks'=>$checks,
        'tables_source'=>count($source['names']),
        'tables_restored'=>count($restore['names']),
        'migrations_source'=>$source['migration_count'],
        'migrations_restored'=>$restore['migration_count'],
        'different_table_count'=>count($countMismatches),
        'different_table_id_hashes'=>$countMismatches,
        'limitations'=>'Row contents, independent restore operation, file parity, application/PDF checks and RTO/RPO NOT verified.',
        'gate_a25'=>'OPEN',
    ];
}

function saveEvidence(string $argPath, array $evidence): void
{
    $project = realpath(dirname(__DIR__, 2));
    if ($project === false) throw new RuntimeException('Missing project root.');
    $base = $project.DIRECTORY_SEPARATOR.'.siapegawai-backup'.DIRECTORY_SEPARATOR.'rel-verify-01c';
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $argPath);
    if (str_contains($path, '..') || str_contains($path, "\0")) throw new RuntimeException('Unsafe evidence destination.');
    $abs = preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) ? $path : $project.DIRECTORY_SEPARATOR.$path;
    $normalizedBase = rtrim(str_replace('\\','/', $base), '/');
    $normalized = rtrim(str_replace('\\','/', $abs), '/');
    if (!str_starts_with(strtolower($normalized), strtolower($normalizedBase.'/'))) {
        throw new RuntimeException('Evidence output must be inside .siapegawai-backup/rel-verify-01c/');
    }
    if (is_link($project.DIRECTORY_SEPARATOR.'.siapegawai-backup') || is_link($base)) {
        throw new RuntimeException('Symlink evidence root forbidden.');
    }
    if (!is_dir($base) && !@mkdir($base, 0750, true) && !is_dir($base)) {
        throw new RuntimeException('Cannot create evidence parent.');
    }
    $parts = explode('/', trim(substr($normalized, strlen($normalizedBase)), '/'));
    $current = $base;
    foreach ($parts as $part) {
        if ($part === '' || $part === '.' || $part === '..') throw new RuntimeException('Unsafe evidence subdirectory.');
        $current .= DIRECTORY_SEPARATOR.$part;
        if (is_link($current)) throw new RuntimeException('Symlink in evidence target is forbidden.');
        if (!is_dir($current) && !@mkdir($current, 0750) && !is_dir($current)) {
            throw new RuntimeException('Cannot create evidence subdirectory.');
        }
    }
    $json = json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    $md = '# REL-VERIFY-01C — Read-only MySQL Comparison Evidence' . "\n\n"
        . "> **NOT an A-25 sign-off.** Source and restored DB were read with separate login paths; no data changed.\n\n"
        . '- Result: **'.$evidence['status']."**\n"
        . '- Tables source / restore: '.$evidence['tables_source'].' / '.$evidence['tables_restored']."\n"
        . '- Migrations source / restore: '.$evidence['migrations_source'].' / '.$evidence['migrations_restored']."\n"
        . '- Table row-count mismatches: '.$evidence['different_table_count']."\n\n"
        . "| Check | Match |\n|---|---|\n";
    foreach ($evidence['checks'] as $key=>$equal) $md .= '| '.str_replace('_', ' ', $key).' | '.($equal ? 'Yes' : 'NO').' |' . "\n";
    $md .= "\n**A-25: OPEN.** Requires independent restoration logs, real PDF and app checks, 01B file parity, RTO/RPO and reviewer.\n";
    $files = ['REL-VERIFY-01C-DB-COMPARE.json'=>$json, 'REL-VERIFY-01C-DB-COMPARE.md'=>$md];
    foreach ($files as $name=>$bytes) {
        $target = $current.DIRECTORY_SEPARATOR.$name;
        if (is_link($target)) throw new RuntimeException('Evidence symlink forbidden.');
        if (file_put_contents($target, $bytes, LOCK_EX) !== strlen($bytes)) throw new RuntimeException('Unable to write evidence.');
    }
    echo "[EVIDENCE] Redacted comparison JSON/MD written under ignored rel-verify-01c folder.\n";
}

function run(array $argv): int
{
    $o = opts($argv);
    if (!empty($o['help'])) { usage(); return 0; }
    echo "REL-VERIFY-01C / v1.15 — READ-ONLY MySQL restore comparison\n";
    if (empty($o['run'])) {
        echo "[DRY RUN] No MySQL connection, no dump, no file output. Use --help, then --run on isolated staging.\n";
        return 0;
    }
    [$src,$dst,$bin] = validateConfig($o);
    echo "[GUARD] Source and restore DB names are distinct staging/test identifiers; separate login paths supplied.\n";
    echo "[SOURCE] Reading metadata and exact per-table counts (SELECT only).\n";
    $source = snapshot($bin, $o['source-login-path'], $src, 'source');
    echo "[RESTORE] Reading metadata and exact per-table counts (SELECT only).\n";
    $restore = snapshot($bin, $o['restore-login-path'], $dst, 'restore');
    $result = resultFor($source,$restore);
    $evidence = array_merge([
        'tool'=>'REL-VERIFY-01C v1.15',
        'baseline'=>SGP_REL_C_BASELINE,
        'generated_wib'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->format(DateTimeInterface::ATOM),
        'data_exposure'=>'schema hash, counts and hashed table identifiers only; no rows or connection secrets',
    ], $result);
    if (isset($o['out-dir'])) saveEvidence($o['out-dir'], $evidence);
    echo '[RESULT] '.$result['status'].'; tables '.count($source['names']).'/'.count($restore['names'])
       .'; mismatched count tables '.$result['different_table_count'].".\n";
    foreach ($result['checks'] as $name=>$matched) echo '  '.($matched?'[PASS] ':'[FLAG] ').$name."\n";
    echo "[GATE] A-25 OPEN: no row-content parity, application/PDF smoke, independent restore proof or RTO/RPO.\n";
    return $result['status'] === 'FLAG' ? 1 : 0;
}
try { exit(run($argv)); }
catch (Throwable $e) { fwrite(STDERR, '[STOP] '.$e->getMessage()."\n"); exit(2); }
