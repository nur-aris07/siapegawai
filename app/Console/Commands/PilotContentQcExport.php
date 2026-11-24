<?php

namespace App\Console\Commands;

use App\Services\PilotContentQcService;
use App\Services\QuickQuizPinEligibility;
use Illuminate\Console\Command;

/** Generates a private, unsigned worksheet. No master records are modified. */
final class PilotContentQcExport extends Command
{
    protected $signature='pilot:content-qc {--template : Generate a blank 30-item worksheet; no database connection} {--edition= : Exact published edition version ID in run-owned QA DB} {--dry-run : Print safe metadata only, never write CSV}';
    protected $description='Pilot QQ content QC worksheet for independent human review; QA/synthetic only';

    public function handle(PilotContentQcService $qc,QuickQuizPinEligibility $eligibility): int
    {
        try {
            $template=(bool)$this->option('template');$edition=$this->option('edition');
            if((int)$template+(int)($edition!==null)!==1)throw new \InvalidArgumentException('CHOOSE_TEMPLATE_OR_EDITION');
            if($template)$rows=$qc->template();
            else {
                if(!is_string($edition)||!preg_match('/^[1-9][0-9]{0,9}$/D',$edition))throw new \InvalidArgumentException('INVALID_EDITION_ID');
                $rows=$qc->forEdition((int)$edition,$eligibility);
            }
            $summary=$qc->safeSummary($rows);
            if(!$this->option('dry-run')){
                $dir=storage_path('app/private/pilot-learning-ops');
                if(is_link($dir))throw new \RuntimeException('PRIVATE_DIRECTORY_LINK_DENIED');
                if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new \RuntimeException('PRIVATE_DIRECTORY_UNAVAILABLE');
                $path=$dir.'/qc-'.($template?'template':'edition-'.$edition).'.csv';
                if(is_link($path))throw new \RuntimeException('PRIVATE_OUTPUT_LINK_DENIED');
                $bytes=$qc->csv($rows);
                if(file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes))throw new \RuntimeException('QC_FILE_WRITE_FAILED');
                @chmod($path,0600);
                $this->line('PRIVATE WORKSHEET CREATED; NOT AN ACADEMIC SIGNOFF');
            }
            $this->line(json_encode($summary,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch(\Throwable $e){$this->error('BLOCKED: '.$e->getMessage());return self::FAILURE;}
    }
}
