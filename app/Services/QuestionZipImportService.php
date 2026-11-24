<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuestionImportBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/** F1: ZIP+CSV opt-in. Never extracts a ZIP tree, never persists a question during preview. */
final class QuestionZipImportService
{
    public const MEDIA_HEADER = ['code','slot','position','file_path','alt_text','caption','rights_source','rights_note'];
    private const SLOTS = ['stem','option_A','option_B','option_C','option_D','option_E','explanation'];
    private const MIME = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
    private const MAX_COMPRESSED = 26214400;
    private const MAX_TOTAL = 83886080;
    private const MAX_ENTRIES = 256;
    private const MAX_IMAGES_PER_QUESTION = 12;

    public function __construct(
        private readonly QuestionCsvImportService $csv,
        private readonly QuestionEditorialService $editor,
        private readonly QuestionMediaStorageService $storage,
        private readonly QuestionMediaBatchService $media,
    ) {}

    private function error(string $reason): never
    {
        throw ValidationException::withMessages(['file'=>$reason]);
    }

    private function securePath(string $name): void
    {
        if (strlen($name) > 220 || preg_match('~[\\\\\x00-\x1f\x7f]|(^/)|(^[A-Za-z]:)|(^//)|(^\\\\)|(^|/)\.\.?(/|$)~', $name))
            $this->error('ZIP berisi jalur tidak aman (absolut, traversal, Windows path atau karakter kontrol).');
    }

    private function open(string $absolute): ZipArchive
    {
        if (!class_exists(ZipArchive::class)) $this->error('Ekstensi PHP zip diperlukan.');
        $zip = new ZipArchive();
        if ($zip->open($absolute, ZipArchive::RDONLY) !== true) $this->error('ZIP tidak valid atau tidak dapat dibuka.');
        return $zip;
    }

    /** Read an entry with a strict streaming ceiling, including when decompressor lies about size. */
    private function read(ZipArchive $zip, string $name, int $max): string
    {
        $stream = $zip->getStream($name);
        if (!is_resource($stream)) $this->error('ZIP entry tidak bisa dibaca: '.$name);
        $raw = '';
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, min(65536, $max + 1 - strlen($raw)));
                if ($chunk === false || $chunk === '') { if (!feof($stream)) $this->error('ZIP stream gagal.'); break; }
                $raw .= $chunk;
                if (strlen($raw) > $max) $this->error('ZIP entry melewati batas alokasi.');
            }
        } finally { fclose($stream); }
        return $raw;
    }

    /** Strict allowlist, bounded central directory + streams, reject symlink & encrypted entries. */
    public function examineArchive(string $absolute): array
    {
        $compressed = filesize($absolute);
        if ($compressed === false || $compressed < 16 || $compressed > self::MAX_COMPRESSED) $this->error('ZIP harus berukuran 1–25 MiB.');
        $zip = $this->open($absolute);
        try {
            if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_ENTRIES) $this->error('ZIP berisi terlalu banyak atau terlalu sedikit entry.');
            $names = []; $sizes = []; $total = 0;
            for ($i=0; $i<$zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!$stat || !is_string($stat['name'] ?? null)) $this->error('Metadata ZIP rusak.');
                $name = $stat['name']; $this->securePath($name);
                $lower = strtolower($name);
                if (isset($names[$lower])) $this->error('Nama entry ZIP duplikat/tidak unik case-insensitive.');
                $names[$lower] = $name;
                $directory = str_ends_with($name,'/');
                if (!$directory && !in_array($name, ['questions.csv','media.csv'], true)
                    && !preg_match('~^media/[A-Za-z0-9][A-Za-z0-9._-]{0,149}\.(?:jpg|jpeg|png|webp)$~Di', $name))
                    $this->error('ZIP hanya boleh memuat questions.csv, media.csv, dan media/*.jpg/png/webp.');
                if ($directory && $name !== 'media/') $this->error('Direktori di luar media/ tidak diizinkan.');
                $opsys=0;$attr=0;
                if ($zip->getExternalAttributesIndex($i,$opsys,$attr) && $opsys===ZipArchive::OPSYS_UNIX) {
                    $kind=($attr>>16)&0170000;
                    if ($kind===0120000 || ($directory && $kind!==0 && $kind!==0040000))
                        $this->error('ZIP symlink atau tipe file khusus ditolak.');
                }
                if (($stat['encryption_method'] ?? 0) !== 0) $this->error('ZIP terenkripsi ditolak.');
                $size = (int)($stat['size']??-1); $csize=(int)($stat['comp_size']??-1);
                if ($size < 0 || $csize < 0) $this->error('Ukuran entry ZIP tidak valid.');
                if (!$directory) {
                    $limit=in_array($name,['questions.csv','media.csv'],true) ? 5242880 : (int)config('question_media.max_bytes',5242880);
                    if ($size<1 || $size>$limit || ($csize===0&&$size>0) || ($csize>0 && $size/$csize>400)) $this->error('ZIP entry terlalu besar atau rasio kompresi mencurigakan.');
                    $total+=$size;
                    if ($total>self::MAX_TOTAL) $this->error('ZIP melewati batas 80 MiB uncompressed.');
                    $sizes[$name]=$size;
                }
            }
            if (!isset($sizes['questions.csv'],$sizes['media.csv'])) $this->error('questions.csv dan media.csv wajib tersedia di root ZIP.');
            $entries=[];
            foreach ($sizes as $name=>$size) {
                $bytes=$this->read($zip,$name,$size);
                if (strlen($bytes)!==$size) $this->error('Ukuran/decompression ZIP tidak konsisten.');
                $entries[$name]=$bytes;
            }
            return $entries;
        } finally { $zip->close(); }
    }

    private function readManifest(array $entries): array
    {
        $raw=$entries['media.csv'];
        // Reuse existing strict CSV parser by translating ONLY the header; do not alter old CSV validation.
        if (str_starts_with($raw,"\xEF\xBB\xBF")) $raw=substr($raw,3);
        if (!preg_match('//u',$raw) || str_contains($raw,"\0")) $this->error('media.csv wajib UTF-8 valid.');
        $lineEnd=strpos($raw,"\n");
        if ($lineEnd===false) $this->error('Header media.csv tidak ditemukan.');
        $first=rtrim(substr($raw,0,$lineEnd),"\r");
        if ($first!==implode(',',self::MEDIA_HEADER)) $this->error('Header media.csv wajib tepat delapan kolom.');
        // The parser validates CSV quoting and row boundaries while honoring the exact eight-column manifest.
        $records = $this->parseMediaCsv($raw);
        return $records;
    }

    private function parseMediaCsv(string $raw): array
    {
        // The same strict no-implicit-backslash RFC CSV FSM as the legacy importer,
        // but eight manifest fields and up to 2,400 media refs (200 * 12).
        $rows=[];$fields=[];$field='';$mode='start';$line=1;$start=1;$length=strlen($raw);
        for($i=0;$i<$length;$i++){
            $ch=$raw[$i];
            if($mode==='quoted'){
                if($ch==='"'){
                    if(($raw[$i+1]??null)==='"'){$field.='"';$i++;}
                    else $mode='after';
                } else {
                    if($ch==="\r"&&($raw[$i+1]??null)==="\n"){$field.="\n";$i++;$line++;}
                    elseif($ch==="\r"||$ch==="\n"){$field.="\n";$line++;}
                    else $field.=$ch;
                }
                continue;
            }
            if($ch==='"'){
                if($mode==='start'){$mode='quoted';continue;}
                $this->error('media.csv: tanda kutip tidak sah.');
            }
            if($ch===','||$ch==="\r"||$ch==="\n"){
                $fields[]=$field;$field='';$mode='start';
                if($ch==="\r"||$ch==="\n"){
                    if($ch==="\r"&&($raw[$i+1]??null)==="\n")$i++;
                    if(!(count($fields)===1&&trim($fields[0])==='')){$rows[]=['fields'=>$fields,'line'=>$start];}
                    if(count($rows)>2401)$this->error('media.csv: maksimal 2.400 media.');
                    $fields=[];$line++;$start=$line;
                }
                continue;
            }
            if($mode==='after')$this->error('media.csv: karakter setelah tanda kutip.');
            $field.=$ch;$mode='unquoted';
        }
        if($mode==='quoted')$this->error('media.csv: kutipan tidak ditutup.');
        if($mode!=='start'||$fields!==[]){$fields[]=$field;$rows[]=['fields'=>$fields,'line'=>$start];}
        if($rows===[]||$rows[0]['fields']!==self::MEDIA_HEADER)$this->error('media.csv: header tidak tepat.');
        array_shift($rows);
        foreach($rows as $r)if(count($r['fields'])!==8)$this->error('media.csv: delapan kolom wajib per record.');
        return array_map(fn($r)=>array_combine(self::MEDIA_HEADER,$r['fields']),$rows);
    }

    public function inspectEntries(array $entries): array
    {
        $records=$this->csv->parse($entries['questions.csv']);
        $mediaRows=$this->readManifest($entries);
        $known=[];foreach ($records as $r) $known[strtoupper(trim($r['values']['code']))]=true;
        $slots=[];$images=[];$mediaErrors=[];$seen=[];$usedPaths=[];
        foreach ($mediaRows as $n=>$m) {
            $code=strtoupper(trim($m['code']));$slot=trim($m['slot']);$path=trim($m['file_path']);
            $position=filter_var($m['position'],FILTER_VALIDATE_INT);
            $issues=[];
            if (!isset($known[$code])) $issues[]='Media mengacu ke kode soal yang tidak tersedia.';
            if (!in_array($slot,self::SLOTS,true) || $position===false || $position<1 || $position>8) $issues[]='Slot atau posisi invalid.';
            if (!isset($entries[$path]) || !str_starts_with($path,'media/')) $issues[]='Berkas media tidak ditemukan.';
            $key=$code.'|'.$slot.'|'.$position;
            if (isset($seen[$key])) $issues[]='Duplikasi slot/posisi.';
            if (isset($usedPaths[$path])) $issues[]='Berkas media tidak boleh digunakan dua kali.';
            $seen[$key]=true;$usedPaths[$path]=true;
            if (preg_match('/[\x00-\x08\x0B\x0E-\x1F]/',implode(' ',[$m['alt_text'],$m['caption'],$m['rights_source'],$m['rights_note']]))) $issues[]='Metadata media berisi karakter kontrol.';
            if (mb_strlen($m['alt_text'])>300 || mb_strlen($m['caption'])>300 || mb_strlen($m['rights_source'])>300 || mb_strlen($m['rights_note'])>300) $issues[]='Metadata media terlalu panjang.';
            if ($issues===[]) {
                try {
                    $temp=tempnam(sys_get_temp_dir(),'sgzi'); file_put_contents($temp,$entries[$path]);
                    try {
                        $file=new UploadedFile($temp,basename($path),null,null,true);
                        $inspected=$this->storage->inspect($file);
                    } finally { @unlink($temp); }
                    $extension=strtolower(pathinfo($path,PATHINFO_EXTENSION));
                    if (self::MIME[$extension]!==$inspected['mime_type']) $issues[]='Ekstensi dan MIME gambar tidak cocok.';
                } catch (\Throwable $e) { $issues[]='Gambar rusak, dimensi tidak sah, atau tidak dapat didekode.'; }
            }
            if ($issues!==[]) { $mediaErrors[]=['record'=>$n+1,'line'=>$n+2,'code'=>$code,'messages'=>$issues];continue; }
            $slots[$code][]=$slot;
            $images[$code][]=['slot'=>$slot,'position'=>$position,'path'=>$path,'alt_text'=>trim($m['alt_text']),
                'caption'=>trim($m['caption']),'rights_source'=>trim($m['rights_source']), 'rights_note'=>trim($m['rights_note']),
                'mime_type'=>$inspected['mime_type'],'sha256'=>$inspected['sha256']];
        }
        $text=$this->csv->inspect($records,$slots);
        foreach ($images as $code=>$items) if (count($items)>self::MAX_IMAGES_PER_QUESTION)
            $mediaErrors[]=['record'=>0,'line'=>0,'code'=>$code,'messages'=>['Maksimal 12 gambar per soal.']];
        $referenced=array_keys($usedPaths);
        foreach (array_keys($entries) as $path) if (str_starts_with($path,'media/') && !in_array($path,$referenced,true))
            $mediaErrors[]=['record'=>0,'line'=>0,'code'=>'','messages'=>['Ada gambar tanpa referensi pada media.csv.']];
        $flat=[];foreach($images as $group)foreach($group as $image)$flat[]=$image;
        foreach ($text['items'] as &$item) {
            $item['media']=array_map(fn($m)=>['slot'=>$m['slot'],'position'=>$m['position'],'alt_text'=>$m['alt_text'],
                'caption'=>$m['caption'],'rights_source'=>$m['rights_source'],'rights_status'=>'UNREVIEWED','index'=>array_search($m,$flat,true)],$images[$item['code']]??[]);
            if ($mediaErrors!==[] && count(array_filter($mediaErrors,fn($e)=>$e['code']===$item['code']))>0) $item['valid']=false;
        } unset($item);
        $errors=array_merge($text['errors'],$mediaErrors);
        $invalid=count(array_filter($text['items'],fn($i)=>!$i['valid'])) + count(array_filter($mediaErrors,fn($e)=>$e['code']===''||$e['record']===0));
        if ($errors!==[] && $invalid===0) $invalid=1;
        return ['total'=>$text['total'],'valid'=>max(0,$text['total']-$invalid),'invalid'=>$invalid,
            'errors'=>$errors,'items'=>$text['items'],'images'=>$images];
    }

    private function contents(QuestionImportBatch $batch): array
    {
        $disk=Storage::disk('local');
        if (!preg_match('~^question-imports/[0-9a-f-]{36}\.zip$~D',(string)$batch->private_path) || !$disk->exists($batch->private_path))
            $this->error('Batch ZIP privat hilang.');
        $size=$disk->size($batch->private_path);
        if ($size!==$batch->byte_count || $size>self::MAX_COMPRESSED || !hash_equals($batch->sha256,(string)hash_file('sha256',$disk->path($batch->private_path))))
            $this->error('Checksum atau ukuran ZIP berubah.');
        return $this->examineArchive($disk->path($batch->private_path));
    }

    public function isZip(QuestionImportBatch $batch): bool { return str_ends_with((string)$batch->private_path,'.zip'); }

    public function upload(UploadedFile $file, User $actor): QuestionImportBatch
    {
        if (!$file->isValid() || strtolower($file->getClientOriginalExtension())!=='zip' || ($file->getSize()??0)>self::MAX_COMPRESSED)
            $this->error('Pilih ZIP valid (maksimum 25 MiB); periksa batas PHP upload.');
        $entries=$this->examineArchive($file->getRealPath());
        $report=$this->inspectEntries($entries);
        $id=(string)Str::uuid();$path='question-imports/'.$id.'.zip';
        $bytes=file_get_contents($file->getRealPath());
        if (!Storage::disk('local')->put($path,$bytes)) $this->error('Gagal menyimpan ZIP privat.');
        try {
            return DB::transaction(function()use($id,$actor,$path,$bytes,$report){
                $batch=QuestionImportBatch::query()->create([
                    'id'=>$id,'actor_id'=>$actor->id,'private_path'=>$path,'sha256'=>hash('sha256',$bytes),'byte_count'=>strlen($bytes),
                    'row_count'=>$report['total'],'valid_count'=>$report['valid'],'invalid_count'=>$report['invalid'],
                    'status'=>$report['invalid']?'invalid':'ready','errors'=>$report['errors'],'expires_at'=>now()->addHours($this->csv->ttlHours()),
                ]);
                AuditLog::query()->create(['actor_id'=>$actor->id,'action'=>'question_import.zip_upload','subject_type'=>User::class,
                    'subject_id'=>$actor->id,'before_summary'=>[],'after_summary'=>['batch_id'=>$batch->id,'count'=>$report['total'],'invalid'=>$report['invalid'],'checksum'=>$batch->sha256],
                    'created_at'=>now()]);
                return $batch;
            });
        } catch(\Throwable $e) {Storage::disk('local')->delete($path);throw $e;}
    }

    public function preview(QuestionImportBatch $batch): array
    {
        if ($batch->status==='committed')return ['total'=>$batch->row_count,'valid'=>$batch->valid_count,'invalid'=>0,'errors'=>[],'items'=>[]];
        if ($batch->expires_at->isPast())return ['total'=>$batch->row_count,'valid'=>$batch->valid_count,'invalid'=>$batch->invalid_count,'errors'=>$batch->errors??[],'items'=>[]];
        return $this->inspectEntries($this->contents($batch));
    }

    /** Return private preview bytes after checking ownership outside this service, expiry, integrity and manifest. */
    public function previewImage(QuestionImportBatch $batch, int $index): array
    {
        abort_if($batch->status==='discarded'||$batch->expires_at->isPast()||$batch->status==='committed',404);
        $entries=$this->contents($batch);$report=$this->inspectEntries($entries);
        $flat=[];foreach($report['images'] as $items)foreach($items as $item)$flat[]=$item;
        abort_unless(isset($flat[$index]),404);
        $item=$flat[$index];$bytes=$entries[$item['path']];
        abort_unless(hash_equals($item['sha256'],hash('sha256',$bytes)),404);
        return ['bytes'=>$bytes,'mime'=>$item['mime_type']];
    }

    public function confirm(QuestionImportBatch $batch, User $actor, ?\Closure $beforeCreate=null): array
    {
        try {
            return DB::transaction(function()use($batch,$actor,$beforeCreate){
                $locked=QuestionImportBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
                abort_unless((int)$locked->actor_id===(int)$actor->id,404);
                if ($locked->status==='committed')return $locked->result??[];
                if ($locked->expires_at->isPast() || !in_array($locked->status,['ready','failed'],true))
                    throw ValidationException::withMessages(['batch'=>'ZIP tidak siap atau kedaluwarsa.']);
                $report=$this->inspectEntries($this->contents($locked));
                if ($report['invalid']!==0 || $report['total']!==$locked->row_count)
                    throw ValidationException::withMessages(['batch'=>'ZIP berubah/tidak valid; tidak ada draft yang dibuat.']);
                $entries=$this->contents($locked);$ids=[];$codes=[];$counter=0;
                foreach($report['items'] as $item){
                    $counter++; if($beforeCreate)$beforeCreate($counter);
                    $q=$this->editor->create($item['input'],$actor);
                    $media=$report['images'][$item['code']]??[];
                    if ($media!==[]) {
                        $files=[];$slots=[];$positions=[];$alts=[];$captions=[];$temps=[];
                        try {
                            foreach($media as $m) {
                                $temp=tempnam(sys_get_temp_dir(),'sgmi');$temps[]=$temp;file_put_contents($temp,$entries[$m['path']]);
                                $files[]=new UploadedFile($temp,basename($m['path']),null,null,true);
                                $slots[]=$m['slot'];$positions[]=$m['position'];$alts[]=$m['alt_text'];$captions[]=$m['caption'];
                            }
                            $this->media->attach($q,$actor,$files,$slots,$positions,$alts,$captions);
                        } finally { foreach($temps as $tmp)@unlink($tmp); }
                    }
                    $this->media->cleanupEmptyOptionalE($q);
                    if($media!==[]){
                        $version=$q->versions()->orderByDesc('version_number')->firstOrFail();
                        AuditLog::query()->create(['actor_id'=>$actor->id,'action'=>'question_import.media_rights_declared',
                            'subject_type'=>\App\Models\QuestionVersion::class,'subject_id'=>$version->id,
                            'before_summary'=>[],'after_summary'=>['scope'=>'DECLARATIONS_ONLY_NOT_RIGHTS_CLEARED',
                                'media'=>array_map(fn($m)=>['slot'=>$m['slot'],'position'=>$m['position'],
                                    'rights_source'=>$m['rights_source'],'rights_note'=>$m['rights_note'],
                                    'sha256'=>$m['sha256']],$media)],'created_at'=>now()]);
                    }
                    $ids[]=$q->id;$codes[]=$q->code;
                }
                $result=['count'=>count($ids),'question_ids'=>$ids,'codes'=>$codes,'mode'=>'zip_media'];
                $locked->update(['status'=>'committed','committed_at'=>now(),'result'=>$result,'failure_reason'=>null]);
                AuditLog::query()->create(['actor_id'=>$actor->id,'action'=>'question_import.zip_commit','subject_type'=>User::class,
                    'subject_id'=>$actor->id,'before_summary'=>[],'after_summary'=>['batch_id'=>$locked->id,'count'=>$result['count']],
                    'created_at'=>now()]);
                return $result;
            },1);
        }catch(\Throwable $e){
            if ($e instanceof \Illuminate\Database\QueryException) throw ValidationException::withMessages(['batch'=>'Konflik kode saat commit; seluruh DB draft dibatalkan.']);
            throw $e;
        }
    }
}
