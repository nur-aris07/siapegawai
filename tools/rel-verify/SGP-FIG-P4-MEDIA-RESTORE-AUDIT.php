<?php
/**
 * FIG-P4 / V-02 -- standalone read-only physical backup parity inspector.
 * This tool NEVER connects to MySQL, executes restoration, or modifies files.
 * It is one necessary check, not an independent DB restore certificate.
 */
declare(strict_types=1);

function figP4Help(): void
{
    echo <<<'HELP'
FIG-P4 V-02 two-private-disk restore parity (READ-ONLY)
Default: DRY RUN; no disk reads until --run.
--run requires ALL options:
  --dump=ABS_PATH.sql[.gz]                 consistent synthetic QA DB dump
  --expected-dump-sha256=HEX64            independent baseline hash
  --source-question=ABS_PATH             question private root at backup snapshot
  --restored-question=ABS_PATH           physically separate restored question root
  --source-class=ABS_PATH                class private PDF root at backup snapshot
  --restored-class=ABS_PATH              physically separate restored class root
Checks bounded regular files, SHA-256, sizes, no symlinks and root independence.
Exit 0: PASS_FILE_PARITY_ONLY; exit 2: BLOCKED/FAIL.
Does NOT restore or compare actual MySQL rows, run auth smoke, or close V-02.
Do not share private paths, dump files, private filenames or secrets in Git.
HELP;
}

/** @return array<string,string|bool> */
function figP4Options(array $argv): array
{
    $opts = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '--run') {
            $key = substr($arg, 2);
            if (isset($opts[$key])) throw new RuntimeException('Duplicate option.');
            $opts[$key] = true;
        } elseif (preg_match('/^--([a-z0-9-]+)=(.*)$/D', $arg, $m)) {
            if (array_key_exists($m[1], $opts)) throw new RuntimeException('Duplicate option.');
            $opts[$m[1]] = $m[2];
        } else throw new RuntimeException('Unknown argument.');
    }
    $allowed = ['help','run','dump','expected-dump-sha256','source-question','restored-question','source-class','restored-class'];
    foreach (array_keys($opts) as $key) {
        if (!in_array($key, $allowed, true)) throw new RuntimeException('Unknown option.');
    }
    return $opts;
}
function figP4Root(string $arg): string
{
    if (is_link($arg)) throw new RuntimeException('Symlink private root rejected.');
    $root = realpath($arg);
    if ($root === false || !is_dir($root) || !is_readable($root)) {
        throw new RuntimeException('Private root not found or unreadable.');
    }
    // A symlink in any ancestor makes a supposedly independent copy ambiguous.
    $cursor = $arg;
    while ($cursor !== dirname($cursor)) {
        if (is_link($cursor)) throw new RuntimeException('Symlinked ancestor rejected.');
        $cursor = dirname($cursor);
    }
    return rtrim($root, DIRECTORY_SEPARATOR);
}
function figP4Overlap(string $a, string $b): bool
{
    return $a === $b || str_starts_with($a, $b.DIRECTORY_SEPARATOR) || str_starts_with($b, $a.DIRECTORY_SEPARATOR);
}
/** @return array{files:array<string,array{0:string,1:int}>,size:int} */
function figP4Inventory(string $root): array
{
    $list = [];
    $total = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isLink()) throw new RuntimeException('Symlink in private tree rejected.');
        if ($entry->isDir()) continue;
        if (!$entry->isFile() || !$entry->isReadable()) {
            throw new RuntimeException('Non-regular or unreadable private object.');
        }
        if (count($list) >= 20000) throw new RuntimeException('Private tree exceeds bounded audit size.');
        $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '../')) {
            throw new RuntimeException('Private relative path invalid.');
        }
        $bytes = $entry->getSize();
        $hash = hash_file('sha256', $entry->getPathname());
        if ($bytes < 0 || $hash === false) throw new RuntimeException('Private file stat/hash failed.');
        $list[$relative] = [$hash, $bytes];
        $total += $bytes;
    }
    ksort($list);
    return ['files'=>$list, 'size'=>$total];
}
/** @return array{missing:int,extra:int,changed:int,source:int,restored:int,sourceBytes:int,restoredBytes:int} */
function figP4Compare(string $a, string $b): array
{
    $src = figP4Inventory($a);
    $dst = figP4Inventory($b);
    $changed = 0;
    foreach ($src['files'] as $name=>$data) {
        if (array_key_exists($name, $dst['files']) && $data !== $dst['files'][$name]) $changed++;
    }
    return [
        'missing'=>count(array_diff_key($src['files'], $dst['files'])),
        'extra'=>count(array_diff_key($dst['files'], $src['files'])),
        'changed'=>$changed,
        'source'=>count($src['files']), 'restored'=>count($dst['files']),
        'sourceBytes'=>$src['size'], 'restoredBytes'=>$dst['size'],
    ];
}
function figP4Main(array $argv): int
{
    try {
        $opt = figP4Options($argv);
        if (isset($opt['help'])) { figP4Help(); return 0; }
        if (!isset($opt['run'])) {
            echo "DRY RUN | No disk or DB inspected. Use --help to see requirements. V-02 OPEN.\n";
            return 0;
        }
        $required = ['dump','expected-dump-sha256','source-question','restored-question','source-class','restored-class'];
        foreach ($required as $key) {
            if (!isset($opt[$key]) || !is_string($opt[$key]) || trim($opt[$key]) === '') {
                throw new RuntimeException('A required independent-restore parameter is missing.');
            }
        }
        if (!preg_match('/^[0-9a-f]{64}$/iD', $opt['expected-dump-sha256'])) {
            throw new RuntimeException('Independent dump SHA-256 is invalid.');
        }
        $paths = [];
        foreach (['source-question','restored-question','source-class','restored-class'] as $key) {
            $paths[$key] = figP4Root($opt[$key]);
        }
        $names = array_keys($paths);
        for ($i=0;$i<count($names);$i++) for ($j=$i+1;$j<count($names);$j++) {
            if (figP4Overlap($paths[$names[$i]], $paths[$names[$j]])) {
                throw new RuntimeException('Private storage roots overlap; independent restore not demonstrated.');
            }
        }
        if (is_link($opt['dump'])) throw new RuntimeException('Dump symlink rejected.');
        $dump = realpath($opt['dump']);
        if ($dump === false || !is_file($dump) || !is_readable($dump) || filesize($dump) <= 0) {
            throw new RuntimeException('Dump not found or empty.');
        }
        foreach ($paths as $root) {
            if (figP4Overlap($dump, $root)) throw new RuntimeException('Dump overlaps a media root.');
        }
        $dumpHash = hash_file('sha256', $dump);
        if ($dumpHash === false || !hash_equals(strtolower($opt['expected-dump-sha256']), strtolower($dumpHash))) {
            throw new RuntimeException('Dump SHA-256 does not match independently recorded hash.');
        }
        $question = figP4Compare($paths['source-question'], $paths['restored-question']);
        $class = figP4Compare($paths['source-class'], $paths['restored-class']);
        // Zero references may mean the QA fixture did not cover this disk.
        $ok = $question['source'] > 0 && $class['source'] > 0;
        foreach ([$question, $class] as $section) {
            if ($section['missing'] || $section['extra'] || $section['changed']) $ok = false;
        }
        echo 'QUESTION | source '.$question['source'].' / restored '.$question['restored']
            .' / missing '.$question['missing'].' / extra '.$question['extra'].' / changed '.$question['changed']."\n";
        echo 'CLASS    | source '.$class['source'].' / restored '.$class['restored']
            .' / missing '.$class['missing'].' / extra '.$class['extra'].' / changed '.$class['changed']."\n";
        echo 'DUMP     | independently recorded SHA-256 MATCH; bytes '.filesize($dump)."\n";
        if (!$ok) {
            echo "BLOCKED_OR_FAIL | Empty tree or mismatched private files. V-02 OPEN.\n";
            return 2;
        }
        echo "PASS_FILE_PARITY_ONLY | No writes. V-02 OPEN until independent DB compare + restored app smoke + RTO/RPO signoff.\n";
        return 0;
    } catch (Throwable $error) {
        // Do not leak paths/object keys or filesystem details in operator logs.
        fwrite(STDERR, "STOP | Independent private backup parity cannot be certified. Verify root separation, dump hash, and object consistency.\n");
        return 2;
    }
}
exit(figP4Main($argv));
