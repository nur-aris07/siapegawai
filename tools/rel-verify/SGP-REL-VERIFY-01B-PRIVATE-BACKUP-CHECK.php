<?php
/**
 * Siapegawai REL-VERIFY-01B — read-only private-file restore parity check.
 * Does NOT execute mysqldump, mysql, migrations, restores, or database queries.
 * Never include this tool's input files or private PDFs in Git.
 */

declare(strict_types=1);

function usage(): void
{
    echo <<<'TEXT'
REL-VERIFY-01B private-file backup/restore parity checker (read-only)
Default: DRY RUN. No filesystem content inspected until --run.
Required for --run:
  --dump=ABS_OR_REL_PATH.sql[.gz]
  --source-private=PATH_TO_ORIGINAL_PRIVATE_MEDIA
  --restored-private=PATH_TO_INDEPENDENT_RESTORED_MEDIA
Optional:
  --expected-dump-sha256=64_HEX_CHARS  (recorded independently at backup time)
  --out-dir=PATH                     (redacted JSON/MD evidence)

Example, Windows CMD (one line):
  php tools\rel-verify\SGP-REL-VERIFY-01B-PRIVATE-BACKUP-CHECK.php --run --dump=E:\secure-qa\dump.sql.gz --source-private=storage\app\siapegawai-class-media --restored-private=E:\isolated-restore\siapegawai-class-media --out-dir=.siapegawai-backup\rel-verify-01b\parity

RESULT SCOPE: dump existence/hash and byte-for-byte private-file parity ONLY.
A-25 stays OPEN until an independent MySQL restore and application/PDF checks pass.
No credentials, SQL rows, PDF bytes, private filenames or paths written to reports.
TEXT;
    echo PHP_EOL;
}

function optmap(array $argv): array
{
    $opts = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) throw new InvalidArgumentException('Unknown positional argument.');
        $pair = explode('=', substr($arg, 2), 2);
        if (isset($opts[$pair[0]])) throw new InvalidArgumentException('Duplicate option: '.$pair[0]);
        $opts[$pair[0]] = $pair[1] ?? true;
    }
    foreach (array_keys($opts) as $key) {
        if (!in_array($key, ['run', 'help', 'dump', 'source-private', 'restored-private', 'expected-dump-sha256', 'out-dir'], true)) {
            throw new InvalidArgumentException('Unsupported option: '.$key);
        }
    }
    return $opts;
}

function dirpath(string $raw, string $label): string
{
    if (is_link($raw)) throw new RuntimeException($label.' must not be a symlink.');
    $path = realpath($raw);
    if ($path === false || !is_dir($path)) throw new RuntimeException($label.' directory unavailable.');
    return $path;
}

function isUnder(string $parent, string $child): bool
{
    $parent = rtrim(str_replace('\\', '/', strtolower($parent)), '/').'/';
    $child = rtrim(str_replace('\\', '/', strtolower($child)), '/').'/';
    return str_starts_with($child, $parent);
}

function candidatePath(string $raw): string
{
    $tail = [];
    $cursor = $raw;
    while (!file_exists($cursor)) {
        $base = basename($cursor);
        if ($base === '.' || $base === '' || $base === DIRECTORY_SEPARATOR) throw new RuntimeException('Cannot resolve output parent.');
        array_unshift($tail, $base);
        $next = dirname($cursor);
        if ($next === $cursor) throw new RuntimeException('Cannot resolve output path.');
        $cursor = $next;
    }
    $base = realpath($cursor);
    if ($base === false || !is_dir($base)) throw new RuntimeException('Output parent invalid.');
    return rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $tail);
}

function inventory(string $root, int $maxFiles = 10000): array
{
    $records = [];
    $totalBytes = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isLink()) throw new RuntimeException('Symlink found within a private directory; refusing to follow.');
        if ($entry->isDir()) continue;
        if (!$entry->isFile() || !$entry->isReadable()) throw new RuntimeException('Unreadable or non-regular private file.');
        $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
        if ($relative === '' || str_contains($relative, '../')) throw new RuntimeException('Unsafe file path.');
        if (count($records) >= $maxFiles) throw new RuntimeException('Too many files for bounded audit; split into controlled batches.');
        $digest = hash_file('sha256', $entry->getPathname());
        if ($digest === false) throw new RuntimeException('Cannot hash private file.');
        $bytes = $entry->getSize();
        if ($bytes === false) throw new RuntimeException('Cannot stat private file.');
        $records[$relative] = [$digest, $bytes];
        $totalBytes += $bytes;
    }
    ksort($records);
    return ['files' => $records, 'count' => count($records), 'bytes' => $totalBytes];
}

function main(array $argv): int
{
    try {
        $opt = optmap($argv);
        if (isset($opt['help'])) { usage(); return 0; }
        if (!isset($opt['run'])) {
            echo "[DRY RUN] No private data, database, or dump inspected. Use --help for required parameters.\n";
            return 0;
        }
        foreach (['dump', 'source-private', 'restored-private'] as $key) {
            if (!isset($opt[$key]) || !is_string($opt[$key]) || trim($opt[$key]) === '') {
                throw new InvalidArgumentException('Missing --'.$key.' for --run.');
            }
        }
        $src = dirpath($opt['source-private'], 'source-private');
        $dst = dirpath($opt['restored-private'], 'restored-private');
        if (isUnder($src, $dst) || isUnder($dst, $src)) {
            throw new RuntimeException('Original and restored directories must be independent (not nested).');
        }
        if (is_link($opt['dump'])) throw new RuntimeException('Dump symlinks are not supported.');
        $dump = realpath($opt['dump']);
        if ($dump === false || !is_file($dump) || !is_readable($dump)) throw new RuntimeException('Dump missing or unreadable.');
        if (isUnder($src, $dump) || isUnder($dst, $dump)) {
            throw new RuntimeException('Dump must be outside private media roots.');
        }
        $dumpBytes = filesize($dump);
        if ($dumpBytes === false || $dumpBytes === 0) throw new RuntimeException('Dump is empty or cannot be stat-ed.');
        $dumpSha = hash_file('sha256', $dump);
        if ($dumpSha === false) throw new RuntimeException('Dump cannot be hashed.');
        $expect = $opt['expected-dump-sha256'] ?? null;
        if ($expect !== null && (!is_string($expect) || !preg_match('/^[a-f0-9]{64}$/i', $expect))) {
            throw new InvalidArgumentException('expected-dump-sha256 must be a 64-character hex digest.');
        }
        $source = inventory($src);
        $restored = inventory($dst);
        $missing = count(array_diff_key($source['files'], $restored['files']));
        $extra = count(array_diff_key($restored['files'], $source['files']));
        $changed = 0;
        foreach ($source['files'] as $name => $properties) {
            if (isset($restored['files'][$name]) && $restored['files'][$name] !== $properties) $changed++;
        }
        $dumpMatch = $expect === null ? null : hash_equals(strtolower($expect), strtolower($dumpSha));
        $pass = $source['count'] > 0 && $missing === 0 && $extra === 0 && $changed === 0 && $dumpMatch !== false;
        $status = $pass ? 'PASS_FILE_PARITY_ONLY' : 'FLAG';
        if ($source['count'] === 0 && $missing === 0 && $extra === 0) $status = 'BLOCKED_NO_PRIVATE_FIXTURE';
        $result = [
            'tool' => 'REL-VERIFY-01B / 1.0',
            'generated_wib' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format(DateTimeInterface::ATOM),
            'scope' => 'dump hash and private-file copy parity ONLY; NOT a database restore validation',
            'status' => $status,
            'dump' => ['bytes' => $dumpBytes, 'sha256' => $dumpSha,
                'expected_digest_provided' => $expect !== null, 'expected_digest_matches' => $dumpMatch],
            'source' => ['count' => $source['count'], 'bytes' => $source['bytes']],
            'restored' => ['count' => $restored['count'], 'bytes' => $restored['bytes']],
            'file_differences' => ['missing' => $missing, 'extra' => $extra, 'changed' => $changed],
            'release_gate_A25' => 'OPEN: independently restore MySQL, compare critical row counts, test authenticated/private PDF, sign off',
            'redaction' => 'No file paths, filenames, SQL content, credentials or PDF content emitted',
        ];
        $md = "# REL-VERIFY-01B — Private backup parity evidence\n\n"
            ."> Automated file checks only. **A-25 remains OPEN until independent MySQL restore and app-level verification.**\n\n"
            ."- Status: **{$status}**\n- Dump bytes: {$dumpBytes}\n- Dump SHA-256: `{$dumpSha}`\n"
            ."- Expected dump hash verified: ".($dumpMatch === null ? 'NOT SUPPLIED' : ($dumpMatch ? 'YES' : 'NO'))."\n"
            ."- Original private files: {$source['count']} ({$source['bytes']} bytes)\n"
            ."- Restored private files: {$restored['count']} ({$restored['bytes']} bytes)\n"
            ."- Missing/extra/changed: {$missing} / {$extra} / {$changed}\n"
            ."- Database restore result: **NOT TESTED BY THIS TOOL**\n\n"
            ."No private filenames, file paths, PDF contents, database rows, or credentials appear in this report.\n";
        if (isset($opt['out-dir'])) {
            if (!is_string($opt['out-dir']) || trim($opt['out-dir']) === '') throw new InvalidArgumentException('Empty out-dir.');
            $out = $opt['out-dir'];
            // Validate the future directory BEFORE any mkdir, including a non-existent nested path.
            $candidate = candidatePath($out);
            if (isUnder($src, $candidate) || isUnder($dst, $candidate) || isUnder($candidate, $src) || isUnder($candidate, $dst)) {
                throw new RuntimeException('Output must not overlap private-file directories.');
            }
            if (!is_dir($out) && !mkdir($out, 0700, true) && !is_dir($out)) throw new RuntimeException('Cannot create output directory.');
            $realOut = dirpath($out, 'out-dir');
            $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (file_put_contents($realOut.'/REL-VERIFY-01B-FILE-PARITY.json', $json."\n", LOCK_EX) === false ||
                file_put_contents($realOut.'/REL-VERIFY-01B-FILE-PARITY.md', $md, LOCK_EX) === false) {
                throw new RuntimeException('Cannot write output evidence.');
            }
            echo "[EVIDENCE] Redacted JSON and Markdown written to requested output directory.\n";
        }
        echo "[{$status}] Source files {$source['count']} / restored {$restored['count']}; missing {$missing}, extra {$extra}, changed {$changed}.\n";
        echo "[GATE] A-25 remains OPEN until separate MySQL restore is verified.\n";
        return $pass ? 0 : 2;
    } catch (Throwable $e) {
        fwrite(STDERR, '[STOP] '.$e->getMessage()."\n");
        return 2;
    }
}

exit(main($argv));
