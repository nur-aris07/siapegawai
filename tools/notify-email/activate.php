<?php
/** NOTIFY-EMAIL-01 C-06: explicitly authorized, local-only, backup-first activation. */
declare(strict_types=1);

use App\Services\AccountEmailService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__, 2);
chdir($root);
function abortActivation(string $reason): never { fwrite(STDERR, "[STOP] $reason\n"); exit(2); }
function okActivation(string $message): void { echo "[OK] $message\n"; }
function configCheck(bool $truth, string $reason): void { if (! $truth) abortActivation($reason); }
function privateDir(string $root): string {
    $path = $root.'/.siapegawai-backup/notify-email-01-activation';
    if (! is_dir($path) && ! @mkdir($path, 0700, true) && ! is_dir($path)) abortActivation('Tidak bisa membuat folder backup privat');
    if (is_link($path)) abortActivation('Folder backup tidak boleh symlink');
    return $path;
}
/**
 * Resolve the installed server's real base directory, not a guessed client path.
 * If MariaDB is remote / its filesystem is unavailable, the operator must
 * provide an installed client path via SIAPEGAWAI_DB_DUMP_BINARY.
 */
function serverInstallRoots(): array {
    try {
        $row = DB::selectOne('SELECT @@GLOBAL.basedir AS basedir, @@GLOBAL.datadir AS datadir');
        if (! is_object($row)) return [];
        $base = trim((string) ($row->basedir ?? ''));
        $data = trim((string) ($row->datadir ?? ''));
        $dirs = [];
        if ($base !== '') $dirs[] = str_replace('\\', '/', $base);
        if ($data !== '') $dirs[] = str_replace('\\', '/', dirname($data));
        return array_values(array_unique($dirs));
    } catch (Throwable $ignored) {
        // Lack of permission to inspect server variables is not permission to skip backup.
        return [];
    }
}
/**
 * Search PATH, MariaDB server @@basedir, and bounded, conventional Windows locations.
 * Explicit operator-specified path takes precedence and is never silently bypassed.
 */
function findDumpCandidates(string $projectRoot, array $serverRoots = []): array {
    $specified = trim((string) env('SIAPEGAWAI_DB_DUMP_BINARY', ''));
    if ($specified !== '') return [$specified];
    $candidates = ['mariadb-dump', 'mysqldump'];
    $runtime = str_replace('\\', '/', dirname(PHP_BINARY, 3));
    $projectRoot = str_replace('\\', '/', $projectRoot);
    $roots = array_merge($serverRoots, [$runtime.'/MariaDB', $runtime.'/Database/MariaDB', $runtime.'/MySQL']);
    foreach (['MARIADB_HOME', 'MYSQL_HOME'] as $key) {
        $location = getenv($key);
        if (is_string($location) && $location !== '') $roots[] = str_replace('\\', '/', $location);
    }
    $drives = [];
    foreach ([$projectRoot, $runtime] as $location) {
        if (preg_match('~^([A-Za-z]:)~', $location, $match)) $drives[] = $match[1];
    }
    foreach (array_unique($drives) as $drive) {
        foreach (['MariaDB','Database/MariaDB','MySQL','Database/MySQL','Services/MariaDB'] as $suffix) {
            $roots[] = $drive.'/Development/Runtime/'.$suffix;
        }
    }
    foreach (['ProgramFiles','ProgramFiles(x86)','ProgramW6432'] as $var) {
        $programFiles = getenv($var);
        if (is_string($programFiles) && $programFiles !== '') {
            $programFiles = str_replace('\\', '/', $programFiles);
            $roots[] = $programFiles.'/MariaDB*';
            $roots[] = $programFiles.'/MySQL*';
        }
    }
    foreach (array_unique($roots) as $folder) {
        foreach (['', 'bin/', '*/', '*/bin/', '*/*/', '*/*/bin/'] as $level) {
            foreach (['mariadb-dump.exe','mysqldump.exe','mariadb-dump','mysqldump'] as $name) {
                foreach (glob(rtrim($folder, '/').'/'.$level.$name) ?: [] as $candidate) {
                    if (is_file($candidate) && ! is_link($candidate)) $candidates[] = $candidate;
                }
            }
        }
    }
    return array_values(array_unique($candidates));
}
function availableDumpBinary(): ?string {
    // Use the installation path reported by this very MariaDB instance first.
    $candidates = findDumpCandidates($GLOBALS['root'], serverInstallRoots());
    foreach ($candidates as $candidate) {
        try {
            $p = @proc_open([$candidate, '--version'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $GLOBALS['root']);
            if (! is_resource($p)) continue;
            fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]);
            stream_get_contents($pipes[2]); fclose($pipes[2]);
            if (proc_close($p) === 0) return $candidate;
        } catch (Throwable $ignored) { /* Not executable; test next candidate. */ }
    }
    return null;
}
function missingDumpTool(): never {
    $roots = serverInstallRoots();
    $base = $roots ? $roots[0] : '(tidak dapat dibaca dari DB)';
    fwrite(STDERR, '[DIAG] MariaDB server basedir: '.$base."\n");
    fwrite(STDERR, "[ACTION] Pastikan file mariadb-dump.exe atau mysqldump.exe memang ada. Jika letaknya berbeda, isi SIAPEGAWAI_DB_DUMP_BINARY=\"C:\\path\\to\\mariadb-dump.exe\" di .env lalu ulangi. Jika tidak ada di komputer, instal MariaDB command-line client resmi terlebih dahulu.\n");
    abortActivation('Program backup tidak ditemukan atau tidak dapat dieksekusi. Tidak ada migration/flag yang diubah.');
}
function backupDatabase(string $folder, array $conn, string $name): string {
    $binary = availableDumpBinary();
    if ($binary === null) missingDumpTool();
    $filename = $folder.'/siapegawai-before-email-outbox-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql';
    $host = (string) ($conn['host'] ?? '127.0.0.1');
    $port = (string) ($conn['port'] ?? 3306);
    $username = (string) ($conn['username'] ?? '');
    $password = (string) ($conn['password'] ?? '');
    $envVars = $_ENV;
    foreach (['PATH','SystemRoot','WINDIR','TEMP','TMP','HOME','USERPROFILE'] as $key) {
        $value = getenv($key); if ($value !== false) $envVars[$key]=$value;
    }
    $envVars['MYSQL_PWD'] = $password; // Never place passwords in process arguments, backup output, or logs.
    $argv = [$binary, '--no-defaults', '--protocol=tcp', '--host='.$host, '--port='.$port,
        '--user='.$username, '--default-character-set=utf8mb4', '--single-transaction', '--quick',
        '--triggers', '--routines', '--events', '--hex-blob', '--result-file='.$filename, $name];
    try {
        $proc = @proc_open($argv, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $GLOBALS['root'], $envVars);
        if (! is_resource($proc)) abortActivation('Gagal menjalankan dump database');
        fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]);
        // Never print raw CLI errors: some drivers/providers include connection details.
        stream_get_contents($pipes[2]); fclose($pipes[2]); $status = proc_close($proc);
    } catch (Throwable $e) { $status = -1; }
    if ($status !== 0 || ! is_file($filename) || filesize($filename) < 1024) {
        @unlink($filename);
        abortActivation('Backup SQL gagal atau terlalu kecil. Tidak menjalankan migration. Periksa izin database dan konfigurasi dump.');
    }
    @chmod($filename, 0600);
    $h = fopen($filename,'rb');
    $structureFound = false; $usersFound = false;
    if ($h !== false) {
        while (($line=fgets($h)) !== false) {
            if (str_contains($line,'CREATE TABLE')) $structureFound=true;
            if (preg_match('/CREATE TABLE(?: IF NOT EXISTS)? [`\"]?users[`\"]?/i', $line)) $usersFound=true;
            if ($structureFound && $usersFound) break;
        }
        fclose($h);
    }
    if (! $structureFound || ! $usersFound) {
        @unlink($filename);
        abortActivation('Backup SQL tidak berisi struktur tabel users. Migration tidak dijalankan.');
    }
    $digest = hash_file('sha256', $filename);
    file_put_contents($filename.'.sha256', $digest."  ".basename($filename)."\n", LOCK_EX);
    @chmod($filename.'.sha256', 0600);
    okActivation('Backup database penuh dibuat dan diperiksa (SHA-256: '.$digest.').');
    echo '[BACKUP] '.str_replace('\\','/',$filename)."\n";
    return $filename;
}
function updateEnvFlag(string $root, string $backupFolder): void {
    $path=$root.'/.env';
    configCheck(is_file($path) && ! is_link($path), '.env tidak ada / bukan file reguler');
    $bytes=file_get_contents($path);
    configCheck(is_string($bytes), 'Gagal membaca .env');
    $pattern='/^SIAPEGAWAI_ACCOUNT_EMAIL_ENABLED\s*=.*$/m';
    configCheck(preg_match_all($pattern,$bytes) <= 1, 'Konfigurasi SIAPEGAWAI_ACCOUNT_EMAIL_ENABLED duplikat');
    $end = str_contains($bytes,"\r\n") ? "\r\n" : "\n";
    $new=preg_match($pattern,$bytes) ? preg_replace($pattern,'SIAPEGAWAI_ACCOUNT_EMAIL_ENABLED=true',$bytes)
        : rtrim($bytes,"\r\n").$end.'SIAPEGAWAI_ACCOUNT_EMAIL_ENABLED=true'.$end;
    configCheck(is_string($new), 'Gagal memproses konfigurasi .env');
    if ($new === $bytes) return;
    $envBackup=$backupFolder.'/env-before-enable-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.env';
    configCheck(file_put_contents($envBackup,$bytes,LOCK_EX) === strlen($bytes), 'Gagal menyimpan backup .env');
    @chmod($envBackup,0600);
    $temp=tempnam($root,'.sgp-email-env-');
    configCheck($temp !== false, 'Gagal membuat file sementara .env');
    try {
        if (file_put_contents($temp,$new,LOCK_EX) !== strlen($new)) throw new RuntimeException('write');
        @chmod($temp,0600);
        if (! @rename($temp,$path)) throw new RuntimeException('rename');
    } catch (Throwable $e) { @unlink($temp); abortActivation('Gagal mengaktifkan flag .env; backup tetap disimpan'); }
    okActivation('Flag email otomatis ON. Kredensial tetap tidak ditampilkan atau disimpan ke Git.');
}

$action = $argv[1] ?? '--check';
configCheck(in_array($action, ['--check','--activate','--status'], true), 'Gunakan --check, --activate --db=siapegawai, atau --status');
if ($action === '--activate') configCheck(count($argv) === 3 && ($argv[2] ?? '') === '--db=siapegawai', 'Aktivasi harus menyatakan --db=siapegawai secara eksplisit');
else configCheck(count($argv) <= 2 || ($action === '--status' && count($argv) === 3 && ($argv[2] ?? '') === '--require-worker'), 'Argumen tambahan tidak valid');
configCheck(is_file($root.'/vendor/autoload.php'), 'Composer dependencies belum tersedia');
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$actualDb = DB::connection()->getDatabaseName();
$table = Schema::hasTable('account_email_outbox');
$flag = config('siapegawai.account_email_enabled') === true;
$heartbeat = $root.'/.siapegawai-backup/notify-email-01-activation/worker-heartbeat.json';
$worker = is_file($heartbeat) && (time()-filemtime($heartbeat) <= 120);
if ($action === '--status') {
    echo 'DB='.$actualDb.' OUTBOX='.($table?'YES':'NO').' FEATURE_FLAG='.($flag?'ON':'OFF').' WORKER_HEARTBEAT='.($worker?'RECENT':'NOT_DETECTED')."\n";
    echo 'SMTP acceptance != mailbox Inbox delivery. Worker heartbeat is a local signal, not a delivery guarantee.' . "\n";
    if (($argv[2] ?? '') === '--require-worker' && (! $table || ! $flag || ! $worker)) abortActivation('Aktivasi/worker belum siap. Periksa window worker dan log privat.');
    exit(0);
}
// Refuse all non-local environments, wrong database, missing SMTP or unapproved untested provider.
configCheck(config('app.env') === 'local', 'Aktivasi hanya diperbolehkan pada APP_ENV=local');
configCheck(in_array(rtrim((string)config('app.url'),'/'), ['http://127.0.0.1:8000','http://localhost:8000'], true), 'APP_URL bukan URL UAT lokal yang diizinkan');
configCheck($actualDb === 'siapegawai', 'Database tidak cocok. Tidak menyentuh '.$actualDb);
configCheck(in_array(config('database.default'), ['mysql','mariadb'], true), 'Driver database harus MariaDB/MySQL');
configCheck(config('mail.default') === 'smtp', 'MAIL_MAILER harus smtp');
configCheck(config('queue.default') === 'database', 'QUEUE_CONNECTION harus database');
configCheck(Schema::hasTable('jobs') && Schema::hasTable('failed_jobs'), 'Tabel jobs/failed_jobs belum tersedia');
configCheck(filled(config('mail.mailers.smtp.host')) && filled(config('mail.mailers.smtp.username')), 'Host/akun SMTP belum terkonfigurasi');
configCheck(filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false, 'From email tidak valid');
configCheck(filter_var(config('siapegawai.smtp_test_recipient'), FILTER_VALIDATE_EMAIL) !== false, 'Mailbox UAT belum terkonfigurasi');
configCheck(is_file($root.'/database/migrations/2026_11_15_000019_create_account_email_outbox.php') && hash_file('sha256', $root.'/database/migrations/2026_11_15_000019_create_account_email_outbox.php') === '0d89c707b736657b6751f08c735e78fe58d4641f500735c8e15bf7715e4d562b', 'Migration outbox berubah dari versi C-01');
$hasMigration=DB::table('migrations')->where('migration','2026_11_15_000019_create_account_email_outbox')->exists();
configCheck($table === $hasMigration, 'Skema dan catatan migration tidak sinkron. Perlu audit, tidak melakukan perubahan');
if ($action === '--check') {
    echo "[READY] Preflight: DB=siapegawai; SMTP/queue configured; outbox ".($table?'present':'needs migration')."; flag ".($flag?'ON':'OFF')."; worker ".($worker?'seen':'not detected').".\n";
    if (! ($table && $flag)) {
        $binary = availableDumpBinary();
        if ($binary === null) missingDumpTool();
        okActivation('MariaDB backup executable terdeteksi dan dapat dijalankan: '.basename($binary));
    }
    echo "[NEXT] --activate --db=siapegawai akan backup seluruh DB, migrate outbox bila perlu, dan mengaktifkan flag.\n";
    exit(0);
}
if ($table && $flag) { okActivation('Migration dan flag sudah aktif. Tidak mengubah database maupun .env.'); exit(0); }
$folder=privateDir($root);
$engines=DB::select('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?', [$actualDb,'BASE TABLE']);
foreach ($engines as $row) configCheck(strtoupper((string)$row->ENGINE) === 'INNODB', 'Tabel '.$row->TABLE_NAME.' bukan InnoDB: snapshot --single-transaction tidak dijamin konsisten');
backupDatabase($folder,config('database.connections.'.config('database.default')),$actualDb);
if (!$table) {
    $code = Artisan::call('migrate', ['--path'=>'database/migrations/2026_11_15_000019_create_account_email_outbox.php','--force'=>true,'--no-interaction'=>true]);
    configCheck($code === 0 && Schema::hasTable('account_email_outbox') && DB::table('migrations')->where('migration','2026_11_15_000019_create_account_email_outbox')->exists(), 'Migration gagal. Flag masih OFF; backup tersimpan. Periksa laravel.log dan migration tanpa menjalankan migrate:fresh.');
    okActivation('Migration outbox telah dijalankan pada database yang benar.');
}
updateEnvFlag($root,$folder);
Artisan::call('config:clear');
config()->set('siapegawai.account_email_enabled', true);
configCheck(app(AccountEmailService::class)->ready(), 'Flag telah diaktifkan tetapi serviceReady false. Periksa konfigurasi; jangan mengirim notifikasi dulu.');
okActivation('Service email otomatis siap menurut konfigurasi. Jalankan worker C-03 dan periksa heartbeat /admin/email.');
