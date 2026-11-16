<?php
namespace App\Services;

use App\Models\Question;
use App\Models\AuditLog;
use App\Models\QuestionImportBatch;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class QuestionCsvImportService
{
    public const HEADER = ['code','subject_code','topic_slug','body','explanation','option_a',
        'option_b','option_c','option_d','option_e','correct_option'];
    private const DISK = 'local';

    public function maxRecords(): int { return max(1, min(200, (int)config('siapegawai.question_import_max_records', 200))); }
    public function maxBytes(): int { return max(1, min(5242880, (int)config('siapegawai.question_import_max_bytes', 5242880))); }
    public function withinByteLimit(int $bytes): bool { return $bytes >= 0 && $bytes <= $this->maxBytes(); }
    public function ttlHours(): int { return max(1, min(24, (int)config('siapegawai.question_import_ttl_hours', 24))); }

    /** Strict RFC-style CSV: no implicit escape backslash, only doubled double-quotes. */
    public function parse(string $data): array
    {
        if (str_starts_with($data, "\xEF\xBB\xBF")) $data = substr($data, 3);
        if (!preg_match('//u', $data) || str_contains($data, "\0") || str_contains($data, "\xEF\xBB\xBF")) {
            throw ValidationException::withMessages(['file'=>'CSV wajib UTF-8 valid; BOM hanya boleh muncul di awal file.']);
        }
        $records = []; $fields = []; $field = ''; $mode = 'start'; $line = 1; $startLine = 1;
        $length = strlen($data);
        for ($i = 0; $i < $length; $i++) {
            $ch = $data[$i];
            if ($mode === 'quoted') {
                if ($ch === '"') {
                    if (($data[$i+1] ?? null) === '"') { $field .= '"'; $i++; }
                    else $mode = 'after';
                } else {
                    if ($ch === "\r" && ($data[$i+1] ?? null) === "\n") { $field .= "\n"; $i++; $line++; }
                    elseif ($ch === "\r" || $ch === "\n") { $field .= "\n"; $line++; }
                    else $field .= $ch;
                }
                continue;
            }
            if ($ch === '"') {
                if ($mode === 'start') { $mode = 'quoted'; continue; }
                throw ValidationException::withMessages(['file'=>"Struktur CSV rusak: tanda kutip pada baris {$line}."]);
            }
            if ($ch === ',' || $ch === "\n" || $ch === "\r") {
                $fields[] = $field; $field = ''; $mode = 'start';
                if ($ch === "\r" || $ch === "\n") {
                    if ($ch === "\r" && ($data[$i+1] ?? null) === "\n") $i++;
                    if (!(count($fields) === 1 && trim($fields[0]) === '')) {
                        $records[] = ['line'=>$startLine, 'fields'=>$fields];
                        if (count($records) > $this->maxRecords()+1) {
                            throw ValidationException::withMessages(['file'=>'Jumlah record melebihi batas '.$this->maxRecords().' soal.']);
                        }
                    }
                    $fields = []; $line++; $startLine = $line;
                }
                continue;
            }
            if ($mode === 'after') {
                throw ValidationException::withMessages(['file'=>"Struktur CSV rusak: teks setelah tanda kutip penutup pada baris {$line}."]);
            }
            $field .= $ch; $mode = 'unquoted';
        }
        if ($mode === 'quoted') throw ValidationException::withMessages(['file'=>'Struktur CSV rusak: tanda kutip belum ditutup.']);
        if ($mode !== 'start' || $fields !== []) {
            $fields[] = $field;
            if (!(count($fields) === 1 && trim($fields[0]) === '')) $records[] = ['line'=>$startLine,'fields'=>$fields];
        }
        if ($records === [] || $records[0]['fields'] !== self::HEADER) {
            throw ValidationException::withMessages(['file'=>'Header CSV harus persis 11 kolom berurutan sesuai template.']);
        }
        array_shift($records);
        if (count($records) === 0) throw ValidationException::withMessages(['file'=>'CSV kosong atau hanya berisi header.']);
        if (count($records) > $this->maxRecords()) {
            throw ValidationException::withMessages(['file'=>'Jumlah record '.count($records).' melebihi batas '.$this->maxRecords().' soal.']);
        }
        foreach ($records as $row) {
            if (count($row['fields']) !== count(self::HEADER)) {
                throw ValidationException::withMessages(['file'=>'Jumlah kolom tidak sesuai pada baris '.$row['line'].'.']);
            }
        }
        return array_map(fn($r) => ['line'=>$r['line'],'values'=>array_combine(self::HEADER, $r['fields'])], $records);
    }

    private function canonical(array $row): array
    {
        foreach ($row as $key=>$value) $row[$key] = trim((string)$value);
        $row['code'] = strtoupper($row['code']);
        $row['subject_code'] = strtoupper($row['subject_code']);
        $row['correct_option'] = strtoupper($row['correct_option']);
        return $row;
    }

    /** Shared field contract mirrors QuestionContentRequest without changing the existing editor. */
    public function inspect(array $records): array
    {
        $subjects = Subject::query()->with('topics')->get()->keyBy('code');
        $seen = []; $items = []; $errors = []; $valid = 0;
        $codes = array_map(fn($r)=>strtoupper(trim($r['values']['code'])), $records);
        $existing = Question::query()->whereIn('code', $codes)->pluck('code')->all();
        $existing = array_fill_keys($existing, true);
        foreach ($records as $index=>$record) {
            $row = $this->canonical($record['values']);
            $subject = $subjects->get($row['subject_code']);
            $topic = $subject?->topics->firstWhere('slug', $row['topic_slug']);
            $topicValid = $subject?->status === 'active' && $topic?->status === 'active';
            $input = array_intersect_key($row, array_flip(['code','body','explanation','option_a','option_b','option_c','option_d','option_e','correct_option']));
            $input['topic_id'] = $topicValid ? $topic->id : null;
            // Form request contract: max/min lengths, code regex, A-D and E rules.
            $validator = Validator::make($input, [
                'code'=>['required','string','max:48','regex:/^[A-Z0-9][A-Z0-9-]*$/'],
                'topic_id'=>['required','integer'],
                'body'=>['required','string','min:12','max:5000'],
                'explanation'=>['required','string','min:12','max:5000'],
                'option_a'=>['required','string','max:1200'],
                'option_b'=>['required','string','max:1200'],
                'option_c'=>['required','string','max:1200'],
                'option_d'=>['required','string','max:1200'],
                'option_e'=>['nullable','string','max:1200'],
                'correct_option'=>['required', Rule::in(['A','B','C','D','E'])],
            ]);
            $messages = $validator->errors()->all();
            if (!$topicValid) $messages[] = 'subject_code/topic_slug: pasangan materi-topik tidak aktif, tidak ditemukan, atau tidak cocok.';
            if ($row['correct_option'] === 'E' && $row['option_e'] === '') $messages[] = 'correct_option: kunci E memerlukan opsi E.';
            if (isset($existing[$row['code']])) $messages[] = 'code: kode sudah terdaftar pada bank soal.';
            if (isset($seen[$row['code']])) $messages[] = 'code: kode duplikat di dalam file (record '.$seen[$row['code']].').';
            if ($row['code'] !== '') $seen[$row['code']] ??= $index+1;
            if ($messages === []) $valid++;
            else $errors[] = ['record'=>$index+1,'line'=>$record['line'],'code'=>mb_substr($row['code'],0,48),'messages'=>$messages];
            $items[] = ['record'=>$index+1,'line'=>$record['line'],'code'=>mb_substr($row['code'],0,48),
                'body'=>mb_substr($row['body'],0,140),'valid'=>$messages === [] ,'input'=>$input];
        }
        return ['total'=>count($records),'valid'=>$valid,'invalid'=>count($records)-$valid,'errors'=>$errors,'items'=>$items];
    }

    private function bytesAndData(string $path): string
    {
        $disk = Storage::disk(self::DISK);
        if (!$disk->exists($path)) throw ValidationException::withMessages(['file'=>'Berkas batch tidak tersedia. Unggah ulang CSV.']);
        if (!$this->withinByteLimit($disk->size($path))) throw ValidationException::withMessages(['file'=>'Ukuran file melebihi '.$this->maxBytes().' byte (5 MiB maksimum).']);
        $data = $disk->get($path);
        if (!is_string($data) || !$this->withinByteLimit(strlen($data))) throw ValidationException::withMessages(['file'=>'Ukuran file melewati batas.']);
        return $data;
    }

    public function upload(UploadedFile $file, User $actor): QuestionImportBatch
    {
        if (!$file->isValid()) throw ValidationException::withMessages(['file'=>'Upload tidak berhasil; periksa batas PHP/web server.']);
        if (strtolower($file->getClientOriginalExtension()) !== 'csv') {
            throw ValidationException::withMessages(['file'=>'Hanya berkas .csv UTF-8 yang diterima.']);
        }
        if (!$this->withinByteLimit($file->getSize())) throw ValidationException::withMessages(['file'=>'File lebih besar dari '.$this->maxBytes().' byte.']);
        $id = (string) Str::uuid();
        $path = 'question-imports/'.$id.'.csv';
        $contents = file_get_contents($file->getRealPath());
        if (!is_string($contents) || !$this->withinByteLimit(strlen($contents))) throw ValidationException::withMessages(['file'=>'Ukuran file tidak valid.']);
        $records = $this->parse($contents);
        $report = $this->inspect($records);
        if (!Storage::disk(self::DISK)->put($path, $contents)) {
            throw ValidationException::withMessages(['file'=>'Gagal menyimpan berkas privat; periksa storage aplikasi.']);
        }
        try {
            return DB::transaction(function () use ($id,$actor,$path,$contents,$report) {
                $batch = QuestionImportBatch::query()->create([
                    'id'=>$id,'actor_id'=>$actor->id,'private_path'=>$path,'sha256'=>hash('sha256',$contents),
                    'byte_count'=>strlen($contents),'row_count'=>$report['total'],'valid_count'=>$report['valid'],
                    'invalid_count'=>$report['invalid'],'status'=>$report['invalid'] ? 'invalid':'ready',
                    'errors'=>$report['errors'],'expires_at'=>now()->addHours($this->ttlHours()),
                ]);
                $this->auditBatch($actor,'question_import.upload',$batch);
                return $batch;
            });
        } catch (\Throwable $exception) {
            Storage::disk(self::DISK)->delete($path);
            throw $exception;
        }
    }

    private function auditBatch(User $actor, string $action, QuestionImportBatch $batch): void
    {
        AuditLog::query()->create([
            'actor_id'=>$actor->id, 'action'=>$action, 'subject_type'=>User::class, 'subject_id'=>$actor->id,
            'before_summary'=>[], 'after_summary'=>[
                'batch_id'=>$batch->id,'status'=>$batch->status,'sha256'=>$batch->sha256,
                'records'=>$batch->row_count,'valid'=>$batch->valid_count,'invalid'=>$batch->invalid_count,
            ], 'created_at'=>now(),
        ]);
    }

    public function owned(string $id, User $actor): QuestionImportBatch
    {
        // Uniform 404 for non-owned batch.
        return QuestionImportBatch::query()->whereKey($id)->where('actor_id',$actor->id)->firstOrFail();
    }

    public function preview(QuestionImportBatch $batch): array
    {
        if ($batch->status === 'committed') return ['total'=>$batch->row_count,'valid'=>$batch->valid_count,'invalid'=>0,'errors'=>[], 'items'=>[]];
        if ($batch->expires_at->isPast()) return ['total'=>$batch->row_count,'valid'=>$batch->valid_count,
            'invalid'=>$batch->invalid_count,'errors'=>$batch->errors??[],'items'=>[]];
        $contents = $this->bytesAndData($batch->private_path);
        if (!hash_equals($batch->sha256, hash('sha256',$contents))) throw ValidationException::withMessages(['file'=>'Checksum file berubah.']);
        return $this->inspect($this->parse($contents));
    }

    public function confirm(QuestionImportBatch $batch, User $actor, QuestionEditorialService $editor, ?\Closure $beforeCreate = null): array
    {
        try {
            return DB::transaction(function () use ($batch,$actor,$editor,$beforeCreate) {
                $locked = QuestionImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
                if ($locked->actor_id !== $actor->id) abort(404);
                if ($locked->status === 'committed') return $locked->result ?? [];
                if ($locked->expires_at->isPast()) throw ValidationException::withMessages(['batch'=>'Batch kedaluwarsa. Upload ulang.']);
                if (!in_array($locked->status, ['ready','failed'], true)) throw ValidationException::withMessages(['batch'=>'Batch tidak siap; seluruh soal harus valid.']);
                $contents = $this->bytesAndData($locked->private_path);
                if (strlen($contents) !== $locked->byte_count || !hash_equals($locked->sha256, hash('sha256',$contents))) {
                    throw ValidationException::withMessages(['batch'=>'Berkas berubah dari preview; upload ulang.']);
                }
                $records = $this->parse($contents);
                $report = $this->inspect($records);
                if ($report['invalid'] || $report['total'] !== $locked->row_count) {
                    throw ValidationException::withMessages(['batch'=>'Data referensi/kode berubah atau ditemukan soal tidak valid; upload dan preview ulang.']);
                }
                $ids = []; $codes = [];
                foreach ($report['items'] as $index=>$item) {
                    if ($beforeCreate) $beforeCreate($index+1); // test-only injection; never an HTTP parameter.
                    $question = $editor->create($item['input'], $actor);
                    $ids[] = $question->id; $codes[] = $question->code;
                }
                $result = ['count'=>count($ids),'question_ids'=>$ids,'codes'=>$codes];
                $locked->update(['status'=>'committed','committed_at'=>now(),'result'=>$result,'failure_reason'=>null]);
                $this->auditBatch($actor,'question_import.commit',$locked);
                return $result;
            }, 1);
        } catch (\Throwable $exception) {
            // Record failure only after outer transaction rolls back; never overwrite a successful commit.
            if (!($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException)) {
                try {
                    QuestionImportBatch::query()->whereKey($batch->id)->whereIn('status',['ready','failed'])
                        ->update(['status'=>'failed', 'failure_reason'=>mb_substr(class_basename($exception),0,200)]);
                } catch (\Throwable $loggingException) {
                    Log::warning('question import batch failure metadata unavailable', ['batch'=>$batch->id]);
                }
            }
            if ($exception instanceof QueryException) {
                throw ValidationException::withMessages(['batch'=>'Konflik database/keunikan kode saat commit; seluruh batch dibatalkan.']);
            }
            throw $exception;
        }
    }

    /** Dismiss only uncommitted batches; retain minimal audit metadata and never delete questions. */
    public function discard(QuestionImportBatch $batch, User $actor): void
    {
        $path = DB::transaction(function () use ($batch, $actor): string {
            $locked = QuestionImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ((int)$locked->actor_id !== (int)$actor->id) abort(404);
            if ($locked->status === 'committed') {
                throw ValidationException::withMessages(['batch'=>'Batch yang sudah committed tidak boleh dibuang; histori soal harus tetap utuh.']);
            }
            if (!in_array($locked->status, ['ready','invalid','failed','expired','discarded'], true)) {
                throw ValidationException::withMessages(['batch'=>'Status batch tidak dapat dibuang.']);
            }
            if ($locked->status !== 'discarded') {
                $locked->update(['status'=>'discarded','errors'=>null,'result'=>null,'failure_reason'=>null]);
                $this->auditBatch($actor, 'question_import.discard', $locked);
            }
            return $locked->private_path ?: '';
        });
        // Storage is outside the transaction. Keep the path for retry/cleanup if deletion fails.
        if ($path !== '') {
            $disk = Storage::disk(self::DISK);
            if ($disk->exists($path) && (!$disk->delete($path) || $disk->exists($path))) {
                throw ValidationException::withMessages(['batch'=>'Metadata batch sudah ditandai dibuang, tetapi penghapusan berkas privat gagal. Jalankan cleanup atau periksa storage.']);
            }
            QuestionImportBatch::query()->whereKey($batch->id)->where('status','discarded')
                ->where('private_path',$path)->update(['private_path'=>'']);
        }
    }

    public static function csvCell(string $value): string
    {
        // Also handles Unicode whitespace and invisible prefixes before spreadsheet formula triggers.
        if (preg_match('/^[\s\x{FEFF}\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}]*[=+\-@]/u', $value)) return "'".$value;
        return $value;
    }
}
