<?php
namespace App\Services;

use App\Models\Question;
use App\Models\QuestionVersion;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

/** QQ-only content contract. Picker checks metadata; mutation also verifies private bytes. */
final class QuickQuizPinEligibility
{
    public function __construct(private QuestionMediaStorageService $storage) {}

    public function reason(QuestionVersion $v, bool $verifyBytes): ?string
    {
        $v->loadMissing(['options','media']);
        $options=$v->options;
        $labels=$options->pluck('label')->all();
        if (!in_array($labels, [['A','B','C','D'],['A','B','C','D','E']], true)
            || $options->where('is_correct',true)->count()!==1
            || $options->contains(fn($o)=>$o->score_weight!==null)) return 'BINARY_OPTIONS_REQUIRED';
        $slots=array_merge(['stem','explanation'],array_map(fn($label)=>'option_'.$label,$labels));
        $seen=[];$covered=[];
        foreach ($v->media as $m) {
            $position=(int)$m->position;
            $key=(string)$m->object_key;
            $mime=['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
            $extension=pathinfo($key,PATHINFO_EXTENSION);
            $w=(int)$m->width_px;$h=(int)$m->height_px;$size=(int)$m->byte_size;
            if ((int)$m->question_version_id!==(int)$v->id || !in_array($m->slot,$slots,true)
                || $position<1 || $position>8 || isset($seen[$m->slot.':'.$position])
                || !preg_match('~^questions/[a-f0-9]{2}/[a-f0-9]{48}\.(?:jpg|png|webp)$~D',$key)
                || !preg_match('/^[a-f0-9]{64}$/D',(string)$m->sha256)
                || ($mime[$extension]??null)!==$m->mime_type
                || $size<1 || $size>5*1024*1024 || $w<1 || $h<1 || $w>4096 || $h>4096 || $w*$h>12000000) {
                return 'INVALID_MEDIA_REFERENCE';
            }
            $seen[$m->slot.':'.$position]=true;
            if ($verifyBytes) {
                try {
                    $disk=Storage::disk(QuestionMediaStorageService::DISK);
                    if (!$disk->exists($key) || $disk->size($key)!==$size || !$this->storage->verify($key,(string)$m->sha256)) return 'MEDIA_INTEGRITY_FAILED';
                    $image=@getimagesize($disk->path($key));
                    if (!$image || $image[0]!==$w || $image[1]!==$h || ($image['mime']??null)!==$m->mime_type) return 'MEDIA_INTEGRITY_FAILED';
                } catch (\Throwable) { return 'MEDIA_INTEGRITY_FAILED'; }
            }
            $covered[$m->slot]=true;
        }
        if (trim((string)$v->body)==='' && !isset($covered['stem'])) return 'STEM_REQUIRED';
        if (trim((string)$v->explanation)==='' && !isset($covered['explanation'])) return 'EXPLANATION_REQUIRED';
        foreach ($options as $o) {
            if (trim((string)$o->body)==='' && !isset($covered['option_'.$o->label])) return 'OPTION_CONTENT_REQUIRED';
        }
        return null;
    }

    public static function message(string $code): string
    {
        return match($code) {
            'BINARY_OPTIONS_REQUIRED'=>'Quiz Kilat membutuhkan 4–5 opsi A–D/E, satu jawaban benar, tanpa skor berbobot.',
            'STEM_REQUIRED'=>'Pertanyaan membutuhkan teks atau gambar pada bagian soal.',
            'EXPLANATION_REQUIRED'=>'Pembahasan membutuhkan teks atau gambar pada bagian pembahasan.',
            'OPTION_CONTENT_REQUIRED'=>'Setiap opsi membutuhkan teks atau gambar pada opsi tersebut.',
            'INVALID_MEDIA_REFERENCE','MEDIA_INTEGRITY_FAILED'=>'Referensi gambar soal tidak valid atau tidak utuh.',
            default=>'Versi soal tidak memenuhi syarat Quiz Kilat.',
        };
    }

    public static function summary(?QuestionVersion $v): string
    {
        return trim((string)$v?->body)==='' ? 'Soal bergambar (periksa kelayakan versi)' : Str::limit((string)$v->body,95);
    }

    public function candidate(Question $q): array
    {
        $v=$q->currentVersion;
        $reason=(!$v || (int)$v->question_id!==(int)$q->id || $v->review_status!=='published' || !$v->published_at)
            ? 'INVALID_CURRENT_VERSION' : $this->reason($v,false);
        return ['id'=>(int)$q->current_version_id,'version_id'=>(int)$q->current_version_id,'code'=>$q->code,
            'subject'=>$q->topic?->subject?->code,'version'=>$v?->version_number,'summary'=>self::summary($v),
            'eligible'=>$reason===null,'eligibility_reason'=>$reason===null?null:self::message($reason)];
    }
}
