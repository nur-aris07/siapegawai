<?php
/**
 * FIG-P4 V-02: SELECT-only MySQL/MariaDB schema/count comparison using two
 * independent local server ports, PHP PDO, and separate private credential files.
 * Does NOT restore databases, inspect row values or declare V-02 PASS.
 * Alternative when mysql CLI does not support --login-path; never accepts
 * credentials via command line, environment printouts, or live DB names.
 */
declare(strict_types=1);

function options(array $argv): array
{
    $o = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (in_array($arg, ['--run', '--help'], true)) {
            $key = substr($arg, 2);
            if (isset($o[$key])) throw new RuntimeException('Duplicate argument.');
            $o[$key] = true;
        } elseif (preg_match('/^--(source-config|restore-config)=(.+)$/D', $arg, $m)) {
            if (isset($o[$m[1]])) throw new RuntimeException('Duplicate argument.');
            $o[$m[1]] = $m[2];
        } else throw new RuntimeException('Unsupported argument.');
    }
    return $o;
}

function privateConfig(string $arg, string $root, string $expectedDb): array
{
    if (is_link($arg)) throw new RuntimeException('Credential symlink forbidden.');
    $resolved = realpath($arg);
    if ($resolved === false || !is_file($resolved) || !is_readable($resolved)
        || filesize($resolved) > 4096 || filesize($resolved) < 20
        || !str_starts_with(str_replace('\\', '/', $resolved), str_replace('\\', '/', $root).'/')) {
        throw new RuntimeException('Credentials must be small, regular, ignored private vault files.');
    }
    $parent = dirname($resolved);
    if (is_link($parent)) throw new RuntimeException('Credential parent symlink forbidden.');
    // On Linux reject credentials readable by group/other. Windows permissions
    // must be set via icacls by the operator before any real connection.
    if (PHP_OS_FAMILY !== 'Windows' && ((fileperms($resolved) & 0077) !== 0)) {
        throw new RuntimeException('Credential file must use chmod 600 on non-Windows hosts.');
    }
    $raw = file_get_contents($resolved);
    $json = $raw !== false ? json_decode($raw, true, 4, JSON_THROW_ON_ERROR) : null;
    if (!is_array($json) || array_diff(array_keys($json), ['host','port','database','username','password'])) {
        throw new RuntimeException('Credential format or fields invalid.');
    }
    foreach (['host','port','database','username','password'] as $field) {
        if (!isset($json[$field]) || (is_string($json[$field]) && $json[$field] === '')) {
            throw new RuntimeException('Credential config missing field.');
        }
    }
    if ($json['host'] !== '127.0.0.1' // Force TCP; 'localhost' can silently use one MySQL socket on Unix
        || !filter_var($json['port'], FILTER_VALIDATE_INT, ['options' => ['min_range'=>1024,'max_range'=>65535]])
        || $json['database'] !== $expectedDb
        || !is_string($json['username']) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $json['username'])
        || !is_string($json['password']) || strlen($json['password']) < 8 || strlen($json['password']) > 256) {
        throw new RuntimeException('Local endpoint, DB or principal identity rejected.');
    }
    return $json;
}

function rows(PDO $pdo, string $sql): array
{
    $statement = $pdo->query($sql);
    return $statement !== false ? $statement->fetchAll(PDO::FETCH_NUM) : [];
}

function hashRows(array $rows): string
{
    return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
}

function snapshot(array $cfg): array
{
    $dsn = 'mysql:host='.$cfg['host'].';port='.$cfg['port'].';dbname='.$cfg['database'].';charset=utf8mb4';
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM,
        PDO::ATTR_TIMEOUT => 8,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // This script issues SELECT only in a read-only transaction; the account
    // itself MUST additionally have SELECT-only grants (reviewed by operator).
    $pdo->exec('START TRANSACTION READ ONLY');
    try {
        $actual = rows($pdo, 'SELECT DATABASE()');
        if (count($actual) !== 1 || ($actual[0][0] ?? null) !== $cfg['database']) {
            throw new RuntimeException('Connected DB identity mismatch.');
        }
        $names = array_column(rows($pdo, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME"), 0);
        if (!$names || count($names) > 200 || !in_array('migrations', $names, true)) {
            throw new RuntimeException('Unexpected table inventory; fail closed.');
        }
        foreach ($names as $name) {
            if (!is_string($name) || !preg_match('/^[A-Za-z0-9_]{1,64}$/D', $name)) {
                throw new RuntimeException('Unexpected table name.');
            }
        }
        $columns = rows($pdo, "SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COALESCE(COLUMN_DEFAULT,'[SQL_NULL]'),EXTRA,COALESCE(COLLATION_NAME,'[SQL_NULL]') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION");
        $indexes = rows($pdo, "SELECT TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE,COALESCE(SUB_PART,'[SQL_NULL]') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX");
        $constraints = rows($pdo, "SELECT TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,COALESCE(REFERENCED_TABLE_NAME,'[SQL_NULL]'),COALESCE(REFERENCED_COLUMN_NAME,'[SQL_NULL]') FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION");
        $migrations = rows($pdo, 'SELECT migration,batch FROM `migrations` ORDER BY migration');
        $counts = [];
        foreach ($names as $name) {
            $answer = rows($pdo, 'SELECT COUNT(*) FROM `'.$name.'`');
            if (count($answer) !== 1 || !ctype_digit((string) $answer[0][0])) {
                throw new RuntimeException('Invalid exact row count.');
            }
            $counts[$name] = (int) $answer[0][0];
        }
        $pdo->exec('COMMIT');
        return [
            'table_names' => $names,
            'counts' => $counts,
            'columns_hash' => hashRows($columns),
            'indexes_hash' => hashRows($indexes),
            'constraints_hash' => hashRows($constraints),
            'migrations_hash' => hashRows($migrations),
            'migrations_count' => count($migrations),
        ];
    } catch (Throwable $error) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $ignore) { /* rollback best effort */ }
        throw $error;
    }
}

function runAudit(array $argv): int
{
    try {
        $opts = options($argv);
        if (isset($opts['help'])) {
            echo "FIG-P4 V02 PDO READ-ONLY DB comparison\nDefault DRY RUN. Requires --run --source-config=ABS_JSON --restore-config=ABS_JSON.\nCredential files must live inside ignored .siapegawai-backup/fig-p4-v02-private/, have different SELECT-only principals, and target separate local server ports.\nSource DB: siapegawai_figp4_source. Restore DB: siapegawai_figp4_restore_verify_testing.\nDoes NOT restore any DB or prove V-02 PASS.\n";
            return 0;
        }
        if (!isset($opts['run'])) {
            echo "DRY RUN | No DB connection or credential-file read. V-02 OPEN.\n";
            return 0;
        }
        if (!extension_loaded('pdo_mysql') || !isset($opts['source-config'], $opts['restore-config'])) {
            throw new RuntimeException('PDO MySQL module or credentials missing.');
        }
        $project = realpath(dirname(__DIR__, 2));
        if ($project === false) throw new RuntimeException('Project root missing.');
        $privateBase = $project.DIRECTORY_SEPARATOR.'.siapegawai-backup';
        $private = $privateBase.DIRECTORY_SEPARATOR.'fig-p4-v02-private';
        if (is_link($privateBase) || is_link($private) || !is_dir($private)) {
            throw new RuntimeException('Ignored private credential vault missing or symlinked.');
        }
        $realPrivate = realpath($private);
        if ($realPrivate === false) throw new RuntimeException('Private credential vault inaccessible.');
        $src = privateConfig($opts['source-config'], $realPrivate, 'siapegawai_figp4_source');
        $dst = privateConfig($opts['restore-config'], $realPrivate, 'siapegawai_figp4_restore_verify_testing');
        if (realpath($opts['source-config']) === realpath($opts['restore-config'])
            || $src['username'] === $dst['username']
            || (int) $src['port'] === (int) $dst['port']) { // localhost and 127.0.0.1 may alias; require different ports
            throw new RuntimeException('Distinct source/restore principals and endpoints required.');
        }
        $source = snapshot($src);
        $restore = snapshot($dst);
        $checks = [
            'table_names_equal' => $source['table_names'] === $restore['table_names'],
            'columns_equal' => hash_equals($source['columns_hash'], $restore['columns_hash']),
            'indexes_equal' => hash_equals($source['indexes_hash'], $restore['indexes_hash']),
            'foreign_keys_equal' => hash_equals($source['constraints_hash'], $restore['constraints_hash']),
            'migrations_equal' => hash_equals($source['migrations_hash'], $restore['migrations_hash']),
            'exact_row_counts_equal' => $source['counts'] === $restore['counts'],
        ];
        $match = !in_array(false, $checks, true);
        $publicReport = [
            'status' => $match ? 'PASS_DB_SCHEMA_COUNTS_ONLY' : 'FLAG_DB_COMPARISON',
            'checks' => $checks,
            'table_count' => count($source['table_names']),
            'migrations_count' => $source['migrations_count'],
            'row_contents_verified' => false,
            'independent_restore_verified' => false,
            'v02' => 'OPEN',
        ];
        echo json_encode($publicReport, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
        return $match ? 0 : 2;
    } catch (Throwable $error) {
        // No raw MySQL exception, usernames, paths, SQL statements or passwords.
        fwrite(STDERR, "STOP | V-02 PDO read-only comparison refused or failed; check isolated endpoints, SELECT grants, private credential vault and schema.\n");
        return 2;
    }
}

exit(runAudit($argv));
