<?php
/**
 * Siapegawai REL-VERIFY-01 — read-only, non-authenticated evidence collector.
 * Usage: php tools/rel-verify/SGP-REL-VERIFY-01-READONLY-AUDIT.php --run [--base-url=http://127.0.0.1:8000]
 *        [--route-json=PATH] [--out-dir=PATH]
 * Without --run this script is a dry-run: no network and no filesystem writes.
 * Never opens .env, accesses the database, or sends authenticated requests.
 */
declare(strict_types=1);

const EXPECTED_BASELINE = '3cce12ade6d7ff21bb7e349eb2ec5085c14f40e1';
const AUDIT_VERSION = 'REL-VERIFY-01 / 1.0';

function printLine(string $line): void { fwrite(STDOUT, $line . PHP_EOL); }
function stop(string $reason, int $code = 2): never { fwrite(STDERR, '[STOP] ' . $reason . PHP_EOL); exit($code); }
function opt(array $args, string $name): ?string {
    foreach ($args as $argument) {
        if (str_starts_with($argument, '--' . $name . '=')) return substr($argument, strlen($name) + 3);
    }
    return null;
}
function statusCheck(array &$checks, string $id, string $group, string $status, string $detail): void {
    $checks[] = ['id'=>$id,'group'=>$group,'status'=>$status,'detail'=>$detail];
}
function hasFile(string $root, string $rel): bool { return is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel)); }
function readSource(string $root, string $rel): string {
    if (!hasFile($root, $rel)) return '';
    return (string) file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
}
function headInfo(string $url): array {
    $ctx = stream_context_create(['http'=>['method'=>'HEAD','timeout'=>5,'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0,'header'=>"User-Agent: SGP-REL-VERIFY-01/1.0\r\nAccept: text/html\r\n"]]);
    $raw = @get_headers($url, true, $ctx);
    if ($raw === false || !isset($raw[0])) return ['status'=>null,'headers'=>[],'error'=>'unreachable'];
    $code = preg_match('/\s(\d{3})(?:\s|$)/', (string) $raw[0], $m) ? (int) $m[1] : null;
    $headers = [];
    foreach ($raw as $key=>$value) if (!is_int($key)) $headers[strtolower((string) $key)] = is_array($value) ? (string) end($value) : (string) $value;
    return ['status'=>$code,'headers'=>$headers,'error'=>null];
}
function canonicalLocalUrl(?string $arg): ?string {
    if ($arg === null) return null;
    $arg = rtrim(trim($arg), '/');
    $parsed = parse_url($arg);
    if (!is_array($parsed) || !in_array($parsed['scheme'] ?? '', ['http'], true) ||
        !in_array(strtolower((string)($parsed['host'] ?? '')), ['127.0.0.1','localhost','[::1]','::1'], true) ||
        isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['query']) || isset($parsed['fragment']) ||
        !in_array($parsed['path'] ?? '', ['', '/'], true)) {
        stop('--base-url hanya menerima http://127.0.0.1:PORT atau localhost tanpa kredensial/path/query.');
    }
    return $arg;
}
function gitLooseHead(string $root): ?string {
    $dotgit = $root . DIRECTORY_SEPARATOR . '.git';
    if (!is_dir($dotgit)) return null;
    $head = @file_get_contents($dotgit . DIRECTORY_SEPARATOR . 'HEAD');
    if (!is_string($head)) return null;
    $head = trim($head);
    if (preg_match('/^[0-9a-f]{40}$/i', $head)) return strtolower($head);
    if (preg_match('/^ref: (refs\/[a-zA-Z0-9_\/.-]+)$/', $head, $m) && !str_contains($m[1], '..')) {
        $ref = @file_get_contents($dotgit . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $m[1]));
        if (is_string($ref) && preg_match('/^[0-9a-f]{40}$/i', trim($ref))) return strtolower(trim($ref));
        $packed = @file_get_contents($dotgit . DIRECTORY_SEPARATOR . 'packed-refs');
        if (is_string($packed) && preg_match('/^([0-9a-f]{40}) ' . preg_quote($m[1], '/') . '$/mi', $packed, $pm)) return strtolower($pm[1]);
    }
    return null;
}
function outputPath(string $root, ?string $argument): string {
    if ($argument === null) return $root . DIRECTORY_SEPARATOR . '.siapegawai-backup' . DIRECTORY_SEPARATOR . 'rel-verify-01-' . date('Ymd-His');
    if ($argument === '' || str_contains($argument, "\0")) stop('--out-dir harus berupa path yang valid.');
    $abs = str_starts_with($argument, '/') || str_starts_with($argument, '\\') || (strlen($argument) > 2 && ctype_alpha($argument[0]) && $argument[1] === ':' && in_array($argument[2], ['/', '\\'], true));
    return $abs ? $argument : $root . DIRECTORY_SEPARATOR . $argument;
}
function writeReport(string $folder, array $metadata, array $checks): void {
    if (!is_dir($folder) && !@mkdir($folder, 0700, true)) stop('Tidak dapat membuat folder bukti: '.$folder);
    $json = json_encode(['metadata'=>$metadata,'checks'=>$checks], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || file_put_contents($folder . DIRECTORY_SEPARATOR . 'REL-VERIFY-01-RESULTS.json', $json . "\n") === false) stop('Gagal menulis JSON evidence.');
    $statusCount = [];
    foreach ($checks as $c) $statusCount[$c['status']] = ($statusCount[$c['status']] ?? 0) + 1;
    $md = "# REL-VERIFY-01 — Automated Read-only Evidence\n\n";
    $md .= "> Generated by tool; **not a production release sign-off**. All checks are observational or unauthenticated. No database restoration or authenticated browser/role review was performed.\n\n";
    $md .= "- Baseline expected: `" . EXPECTED_BASELINE . "`\n- Source HEAD (loose Git ref, if readable): `" . ($metadata['observed_head'] ?? 'unavailable') . "`\n";
    $md .= "- Created (Asia/Jakarta): `" . $metadata['created_wib'] . "`\n- HTTP probe enabled: **" . ($metadata['http_probe'] ? 'yes (loopback only)' : 'no') . "**\n";
    $md .= "- Route JSON provided: **" . ($metadata['route_json'] ? 'yes' : 'no') . "**\n- Check status counts: `" . json_encode($statusCount) . "`\n\n";
    $md .= "| ID | Domain | Status | Observation / needed evidence |\n|---|---|---|---|\n";
    foreach ($checks as $c) {
        $detail = str_replace(['|',"\r","\n"], ['/', ' ', ' '], $c['detail']);
        $md .= '| ' . $c['id'] . ' | ' . $c['group'] . ' | **' . $c['status'] . '** | ' . $detail . " |\n";
    }
    $md .= "\n## Interpretation\n\n- `OBSERVED`: source/header characteristic exists; **not** proof of its full security or end-to-end behavior.\n- `PASS`: a specific, scoped read-only check passed.\n- `FLAG`: check indicates potential defect or deviation requiring investigation.\n- `BLOCKED`: environment/evidence absent or requires authenticated/manual testing.\n\n";
    $md .= "**Production decision is always NO-GO/NOT YET VERIFIED until P0 A-21, A-25, V-09 and outstanding acceptance gates receive staging evidence and explicit approval.**\n";
    if (file_put_contents($folder . DIRECTORY_SEPARATOR . 'REL-VERIFY-01-RESULTS.md', $md) === false) stop('Gagal menulis Markdown evidence.');
}

$args = array_slice($argv,1);
$run = in_array('--run',$args,true);
if (in_array('--help',$args,true) || in_array('-h',$args,true)) {
    printLine('Usage: php tools/rel-verify/SGP-REL-VERIFY-01-READONLY-AUDIT.php [--run] [--base-url=http://127.0.0.1:8000] [--route-json=PATH] [--out-dir=PATH]');
    printLine('Default dry-run; --run writes redacted evidence only, HEAD requests limited to local HTTP, no DB writes or authenticated requests.');
    exit(0);
}
$root = realpath(dirname(__DIR__, 2));
if ($root === false || !hasFile($root,'artisan') || !hasFile($root,'routes/web.php') || !hasFile($root,'composer.json')) stop('Letakkan script dalam root Laravel: tools/rel-verify/SGP-REL-VERIFY-01-READONLY-AUDIT.php.');
$baseUrl = canonicalLocalUrl(opt($args,'base-url'));
$routeJson = opt($args,'route-json');
$folder = outputPath($root, opt($args,'out-dir'));
$observedHead = gitLooseHead($root);
printLine('Siapegawai ' . AUDIT_VERSION . ' — READ-ONLY / no user credentials / no DB queries');
printLine('[BASELINE] Expected '.EXPECTED_BASELINE.'; observed '.($observedHead??'unavailable (Git packed/worktree)'));
printLine('[SCOPED] security source, route metadata (optional), localhost HTTP HEAD (optional), manual gate register unchanged');
printLine('[OUT] '.$folder);
if (!$run) { printLine('[DRY RUN] 0 files written, 0 network requests. Add --run to collect evidence.'); exit(0); }
$checks = [];
if ($observedHead !== null) {
    statusCheck($checks,'SRC-GIT','baseline',$observedHead===EXPECTED_BASELINE?'PASS':'FLAG','HEAD '.$observedHead.'; expected '.EXPECTED_BASELINE.' (do not run on unintended commit).');
} else {
    statusCheck($checks,'SRC-GIT','baseline','BLOCKED','Git SHA cannot be read via loose/packed ref; verify separately: git rev-parse HEAD.');
}
$securityFile = readSource($root,'app/Http/Middleware/AddSecurityHeaders.php');
$webRoutes = readSource($root,'routes/web.php');
$securityTokens = ['X-Content-Type-Options','X-Frame-Options','Referrer-Policy','Permissions-Policy'];
foreach ($securityTokens as $i=>$token) {
    statusCheck($checks,'SRC-SEC-0'.($i+1),'headers',str_contains($securityFile,$token)?'OBSERVED':'FLAG',$token.': '.(str_contains($securityFile,$token)?'defined in middleware; not end-to-end validated':'not found in expected middleware'));
}
statusCheck($checks,'SRC-SEC-05','CSP',str_contains($securityFile,'Content-Security-Policy-Report-Only')?'OBSERVED':'FLAG','Report-only CSP present in source? '.(str_contains($securityFile,'Content-Security-Policy-Report-Only')?'yes; staging production-mode violations still OPEN':'no; investigate')); 
statusCheck($checks,'SRC-SEC-06','private-cache',str_contains($securityFile,'private, no-store')?'OBSERVED':'FLAG','Source-level private/no-store marker; direct authenticated response testing still OPEN.');
statusCheck($checks,'SRC-SEC-07','route-guards',(str_contains($webRoutes,'EnsureRole') && str_contains($webRoutes,'EnsureActiveAccount'))?'OBSERVED':'FLAG','Role and active-account guard marker(s) in web routes; authorize per-route using exported route metadata and test users.');
statusCheck($checks,'SRC-SEC-08','route-guards',str_contains($webRoutes,'EnsureAdminSessionVersion')?'OBSERVED':'FLAG','Admin session version guard marker in routes; role-change browser revocation remains manual.');
statusCheck($checks,'SRC-STORAGE-01','private-storage',str_contains(readSource($root,'config/filesystems.php'),"storage_path('app/private')")?'OBSERVED':'FLAG','Private disk root observed; PDF direct-URL and revoke/regrant checks still OPEN.');
statusCheck($checks,'SRC-QQ-01','quiz-ajax',hasFile($root,'resources/js/member-quick-quizzes.js')&&hasFile($root,'resources/views/member/quick-quizzes/partials/exam.blade.php')?'OBSERVED':'FLAG','AJAX JS and exam partial present; live browser no-reload and feedback timing must be checked independently.');
statusCheck($checks,'SRC-QQ-02','quiz-decision',str_contains(readSource($root,'docs/specs/QQ-SPEC-ADR-v1.2.md'),'5')?'OBSERVED':'FLAG','5-second feedback decision file present; this is not proof of runtime enforcement.');
statusCheck($checks,'SRC-VITE-01','assets',hasFile($root,'public/build/manifest.json')?'OBSERVED':'BLOCKED','Vite production manifest '.(hasFile($root,'public/build/manifest.json')?'present; verify its build freshness with npm run build':'missing; run npm run build for QA'));
statusCheck($checks,'QA-REG-01','manual-gates',hasFile($root,'docs/qa/REL-VISUAL-UAT-REGISTER-2026-11-10.md')?'OBSERVED':'BLOCKED','REL-VIS 01–09 register exists? '.(hasFile($root,'docs/qa/REL-VISUAL-UAT-REGISTER-2026-11-10.md')?'yes':'no; retrieve plan and register'));

if ($routeJson !== null) {
    $path = $routeJson;
    if (!is_file($path)) $path = $root . DIRECTORY_SEPARATOR . $routeJson;
    $loaded = is_file($path) ? json_decode((string) file_get_contents($path),true) : null;
    if (!is_array($loaded)) {
        statusCheck($checks,'ROUTE-JSON','route-auth','BLOCKED','Route list export missing or not valid JSON. Use php artisan route:list --json > PATH.');
    } else {
        $count=0; $missing=[];
        foreach ($loaded as $route) {
            if (!is_array($route)) continue;
            $uri = trim((string)($route['uri']??''),'/');
            $middleware = $route['middleware']??[];
            $joined = is_array($middleware) ? implode(' ',array_map('strval',$middleware)) : (string) $middleware;
            $needsGuard = str_starts_with($uri,'admin/') || $uri==='admin' || str_starts_with($uri,'quiz-kilat') || str_starts_with($uri,'kelas-saya');
            if (!$needsGuard) continue;
            $count++;
            $auth = preg_match('/(?:^|[^a-z])auth(?:[^a-z]|$)|Authenticate/i',$joined)===1;
            $role = str_contains($joined,'EnsureRole') || str_contains($joined,'ensure.role') || str_contains($joined,'role:');
            if (!$auth || !$role) $missing[]=$uri.' ('.($auth?'auth':'NO-AUTH').'/'.($role?'role':'NO-ROLE').')';
        }
        statusCheck($checks,'ROUTE-GUARD','route-auth',$count===0?'BLOCKED':(count($missing)===0?'PASS':'FLAG'),
            $count.' scoped routes inspected; '.count($missing).' with missing explicit auth/role marker'.($missing?', examples: '.implode('; ',array_slice($missing,0,5)):'').'. Review aliases and implicit group middleware manually.');
    }
} else statusCheck($checks,'ROUTE-JSON','route-auth','BLOCKED','No route JSON supplied; route-level checks not run.');

if ($baseUrl !== null) {
    foreach (['/' => 'HTTP-HOME', '/bank-soal' => 'HTTP-BANK', '/quiz-kilat' => 'HTTP-QUIZ-GUEST', '/kelas-saya' => 'HTTP-CLASS-GUEST', '/admin/soal' => 'HTTP-ADMIN-GUEST'] as $path => $id) {
        $data = headInfo($baseUrl.$path);
        $code = $data['status'];
        if ($code === null) { statusCheck($checks,$id,'http','BLOCKED',$path.' unavailable; application may not be running.'); continue; }
        $private = str_contains($id,'GUEST');
        $safe = in_array($code,[301,302,303,307,308,401,403,404],true);
        $status = $private ? ($safe ? 'OBSERVED':'FLAG') : ($code===200 ? 'PASS':'FLAG');
        statusCheck($checks,$id,'http',$status,$path.' HEAD status '.$code.'; '.($private?'unauthenticated access only; verify redirect Location to login and owned-resource response manually':'public availability only'));
        if ($path === '/') {
            $headers=$data['headers'];
            foreach (['x-content-type-options'=>'nosniff','x-frame-options'=>'DENY','referrer-policy'=>null] as $name=>$value) {
                $got=$headers[$name]??null;
                statusCheck($checks,'HTTP-HEADER-'.strtoupper(str_replace('-','_',$name)),'headers',
                    $got!==null && ($value===null || strtolower($got)===strtolower($value))?'PASS':'FLAG',
                    $name.': '.($got??'missing'));
            }
            $csp = $headers['content-security-policy-report-only'] ?? null;
            statusCheck($checks,'HTTP-CSP','CSP',$csp===null?'BLOCKED':'OBSERVED',
                $csp===null?'Report-only CSP not present on local response; may be expected with APP_ENV != production. Staging production-mode audit remains OPEN.':'Report-only CSP header present; inspect browser violation reports before enforce (still OPEN).');
        }
    }
} else statusCheck($checks,'HTTP-GATE','http','BLOCKED','HTTP probes not requested. Supply --base-url=http://127.0.0.1:8000 to run anonymous HEAD requests.');
foreach ([
    'A-21'=>'Production-mode CSP report and logged violations across all routes',
    'A-23'=>'Visual viewport/zoom/accessibility across representative routes',
    'A-24'=>'Keyboard, focus, labels and screen-reader checks',
    'A-25'=>'Backup + independent restore test (MySQL and private PDFs)',
    'V-07'=>'Mobile and 200% zoom evidence',
    'V-09'=>'CSP report-only and enforce decision',
    'REL-VIS'=>'Nine manual class/media visual gates and reviewer sign-off',
    'OPS-LEGAL'=>'Privacy/terms, WhatsApp operations, score disclaimer review',
] as $id=>$detail) statusCheck($checks,'GATE-'.$id,'manual-release','BLOCKED',$detail.'; never automatically marked PASS by this tool.');
$tz=new DateTimeZone('Asia/Jakarta');
$metadata=['tool_version'=>AUDIT_VERSION,'baseline_expected'=>EXPECTED_BASELINE,'observed_head'=>$observedHead,
    'created_wib'=>(new DateTimeImmutable('now',$tz))->format(DateTimeInterface::ATOM),
    'http_probe'=>$baseUrl!==null,'route_json'=>$routeJson!==null,'redaction'=>'No .env or body/cookies/tokens read','database_writes'=>0];
writeReport($folder,$metadata,$checks);
$flags=count(array_filter($checks,fn($c)=>$c['status']==='FLAG'));
printLine('[DONE] '.count($checks).' checks; '.$flags.' FLAG; artifacts: '.$folder);
printLine('[GATE] RELEASE NOT SIGNED OFF. Review FAIL/FLAG and complete manual P0 gates.');
exit($flags > 0 ? 1 : 0);
