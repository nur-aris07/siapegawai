<?php

namespace App\Console\Commands;

use App\Services\PilotMeasurementService;
use Illuminate\Console\Command;

/** No public route, no personal export, no arbitrary file path/SQL. */
final class PilotMeasurementReport extends Command
{
    protected $signature='pilot:measure {--edition= : Exact QA published version ID} {--cohort= : Exact cohort code} {--start= : Seven-day window start, UTC Z} {--end= : Seven-day window end, UTC Z} {--cutoff= : As-of cutoff, UTC Z} {--population=operational : operational or research_opt_in}';
    protected $description='Private, consistent-snapshot, SELECT-only cohort aggregate on a run-owned QA database';
    public function handle(PilotMeasurementService $service): int
    {
        try{
            $edition=$this->option('edition');
            if(!is_string($edition)||!preg_match('/^[1-9][0-9]{0,9}$/D',$edition))throw new \InvalidArgumentException('INVALID_EDITION_ID');
            foreach(['cohort','start','end','cutoff'] as $key)if(!is_string($this->option($key)))throw new \InvalidArgumentException('MISSING_'.$key);
            $scope=$service->input($this->option('cohort'),(int)$edition,$this->option('start'),$this->option('end'),
                $this->option('cutoff'),$this->option('population'));
            $report=$service->fromDatabase($scope);
            $dir=storage_path('app/private/pilot-learning-ops');
            if(is_link($dir))throw new \RuntimeException('OUTPUT_DIRECTORY_LINK_DENIED');
            if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new \RuntimeException('OUTPUT_DIRECTORY_UNAVAILABLE');
            $filename=$dir.'/aggregate-'.$scope['edition'].'-'.$scope['population'].'.json';
            if(is_link($filename))throw new \RuntimeException('OUTPUT_FILE_LINK_DENIED');
            $bytes=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            if(file_put_contents($filename,$bytes,LOCK_EX)!==strlen($bytes))throw new \RuntimeException('PRIVATE_REPORT_WRITE_FAILED');
            @chmod($filename,0600);
            $this->info('PRIVATE AGGREGATE CREATED; no personal data or academic GO');
            $this->line($bytes);
            return self::SUCCESS;
        }catch(\Throwable $e){$this->error('BLOCKED: '.$e->getMessage());return self::FAILURE;}
    }
}
