<?php
/** Bounded, isolated UAT worker for only the account-emails queue. Restart loop is local session only. */
declare(strict_types=1);
$root = dirname(__DIR__, 2);
chdir($root);
$bin=PHP_BINARY;
$private=$root.'/.siapegawai-backup/notify-email-01-activation';
if (! is_dir($private)) { fwrite(STDERR,"[STOP] Activation folder missing. Run activate.php first.\n"); exit(2); }
$lock=fopen($private.'/worker.lock','c+');
if ($lock===false || ! flock($lock,LOCK_EX|LOCK_NB)) { fwrite(STDERR,"[STOP] Another account-email worker is running.\n"); exit(2); }
$heartbeat=$private.'/worker-heartbeat.json';
$log=$private.'/worker-output.log';
$running=true;
register_shutdown_function(function() use ($heartbeat) { @unlink($heartbeat); });
$counter=0;
while (true) {
    $counter++;
    $heartbeatData=json_encode(['pid'=>getmypid(),'at'=>gmdate('c'),'cycle'=>$counter],JSON_UNESCAPED_SLASHES);
    file_put_contents($heartbeat,$heartbeatData."\n",LOCK_EX);
    @chmod($heartbeat,0600);
    // Each short worker cycle reloads application config; --stop-when-empty prevents idle processes.
    $command=[$bin,$root.'/artisan','queue:work','database','--queue=account-emails','--tries=3','--timeout=25','--sleep=2','--max-time=45','--max-jobs=2','--stop-when-empty'];
    $proc=@proc_open($command,[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$root);
    if (! is_resource($proc)) { fwrite(STDERR,"[WORKER] launch failed; retrying.\n"); sleep(10); continue; }
    $exit=proc_close($proc);
    if ($exit !== 0) fwrite(STDERR,"[WORKER] cycle error; see private worker log.\n");
    sleep($exit===0?3:10);
}
