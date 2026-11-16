<?php
namespace App\Console\Commands;

use App\Models\QuestionImportBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CleanupQuestionImportBatches extends Command
{
    protected $signature = 'siapegawai:cleanup-question-imports {--apply : Mark expired/delete private CSV (default dry-run)}';
    protected $description = 'Cleanup expired private question import CSV; retain committed batch metadata.';
    public function handle(): int
    {
        $query = QuestionImportBatch::query()->where('expires_at','<',now())->where('private_path','<>','');
        $this->line('Expired private files: '.$query->count().'.');
        if (!$this->option('apply')) { $this->line('Dry-run only.'); return self::SUCCESS; }
        $query->orderBy('created_at')->chunk(100,function ($batches) {
            foreach ($batches as $batch) {
                $path = DB::transaction(function () use($batch) {
                    $locked = QuestionImportBatch::query()->lockForUpdate()->find($batch->id);
                    if (!$locked || $locked->expires_at->isFuture() || !$locked->private_path) return null;
                    $path = $locked->private_path;
                    $locked->update(['private_path'=>'', 'status'=>$locked->status === 'committed'?'committed':'expired']);
                    return $path;
                });
                if ($path) Storage::disk('local')->delete($path);
            }
        });
        $this->info('Expired file cleanup finished; metadata retained.');
        return self::SUCCESS;
    }
}
