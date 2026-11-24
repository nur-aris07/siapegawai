<?php

namespace App\Services;

use App\Models\QuickQuizVersion;
use App\Support\PilotLearningQaDatabase;
use Illuminate\Support\Facades\DB;

/** PREPARATION ONLY: structural QC is not independent academic review. */
final class PilotContentQcService
{
    public const SCHEMA_VERSION = 'PILOT-QC-REGISTER-1';
    public const COLUMNS = [
        'edition_code','planned_ordinal','candidate_id','question_id','question_version_id','edition_version_id',
        'competency','topic','difficulty_proposal','content_type','answer_key_private','rationale_private',
        'media_ref_id','media_slot','media_sha256','media_dimensions','alt_text','source_origin',
        'license_or_permission','author','reviewer_identity','reviewer_independence','reviewed_version_sha256',
        'reviewed_at_utc','academic_result','rights_result','issue_severity','correction_reference',
        'final_signoff','review_invalidation_reason','source_version_digest',
    ];
    public const BLUEPRINT = ['pattern_sequence'=>8,'visual_analogy'=>6,'classification'=>6,'matrix_relationship'=>5,'text_reasoning'=>5];

    /** No database/real candidate access; deliberately leave all academic fields unapproved. */
    public function template(): array
    {
        $rows=[]; $ordinal=0;
        foreach (self::BLUEPRINT as $competency=>$count) {
            for($i=0;$i<$count;$i++) {
                $ordinal++;
                $rows[]=$this->emptyRow($ordinal,$competency);
            }
        }
        return $rows;
    }

    private function emptyRow(int $ordinal,string $competency): array
    {
        $r=array_fill_keys(self::COLUMNS,null);
        $r['planned_ordinal']=$ordinal;
        $r['competency']=$competency;
        $r['academic_result']='PENDING_INDEPENDENT_REVIEW';
        $r['rights_result']='UNVERIFIED';
        $r['reviewer_independence']='UNVERIFIED';
        $r['final_signoff']='NOT_GRANTED';
        return $r;
    }

    /** Only isolated run-owned testing DB. Never issues draft changes, publication or DB mutation. */
    public function forEdition(int $editionVersionId, QuickQuizPinEligibility $eligibility): array
    {
        PilotLearningQaDatabase::assertReadOnlyTarget();
        if($editionVersionId<=0)throw new \InvalidArgumentException('INVALID_EDITION_ID');
        $edition=QuickQuizVersion::query()->with(['quiz','items.version.options','items.version.media'])
            ->findOrFail($editionVersionId);
        if($edition->status!=='published' || $edition->quiz?->status!=='published'
            || (int)$edition->quiz->current_version_id!==$editionVersionId) {
            throw new \RuntimeException('EDITION_NOT_PUBLISHED_AND_PINNED');
        }
        $items=$edition->items->sortBy('sort_order')->values();
        if($items->count()!==30 || $items->pluck('sort_order')->map(fn($x)=>(int)$x)->all()!==range(1,30)) {
            throw new \RuntimeException('EXACT_30_ORDINALS_REQUIRED');
        }
        $rows=[];
        foreach($items as $item) {
            $v=$item->version;
            if(!$v||(int)$v->id!==(int)$item->question_version_id)throw new \RuntimeException('VERSION_PIN_MISSING');
            $r=$this->emptyRow((int)$item->sort_order,'UNCLASSIFIED_PENDING_REVIEW');
            $media=$v->media->sortBy(fn($m)=>$m->slot.':'.str_pad((string)$m->position,2,'0',STR_PAD_LEFT));
            $digest=hash('sha256',json_encode([
                'edition_version'=>$editionVersionId,'item'=>$item->id,'question_version'=>$v->id,
                'version_hash'=>$v->version_hash,'body'=>$v->body,'explanation'=>$v->explanation,
                'options'=>$v->options->sortBy('sort_order')->map(fn($o)=>[$o->label,$o->body,$o->is_correct,$o->score_weight])->values()->all(),
                'media'=>$media->map(fn($m)=>[$m->id,$m->slot,$m->position,$m->sha256,$m->byte_size,$m->alt_text])->values()->all(),
            ],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            $reason=$eligibility->reason($v,true);
            $r['edition_code']=$edition->quiz->code;$r['question_id']=(int)$v->question_id;
            $r['question_version_id']=(int)$v->id;$r['edition_version_id']=$editionVersionId;
            $r['topic']=$item->topic_name_snapshot;$r['content_type']=$media->isEmpty()?'text':'mixed_or_figural';
            $r['answer_key_private']=$v->options->firstWhere('is_correct',true)?->label;
            $r['rationale_private']=$v->explanation;
            $r['media_ref_id']=$media->pluck('id')->implode('|') ?: null;
            $r['media_slot']=$media->pluck('slot')->implode('|') ?: null;
            $r['media_sha256']=$media->pluck('sha256')->implode('|') ?: null;
            $r['media_dimensions']=$media->map(fn($m)=>$m->width_px.'x'.$m->height_px)->implode('|') ?: null;
            $r['alt_text']=$media->pluck('alt_text')->implode('|') ?: null;
            $r['source_version_digest']=$digest;
            $r['review_invalidation_reason']=$reason ?? 'REVIEW_NOT_SIGNED';
            if($reason!==null)$r['issue_severity']='TECHNICAL_BLOCKER:'.$reason;
            $rows[]=$r;
        }
        return $rows;
    }

    /** CSV is PRIVATE: may contain keys, rationales and media identifiers. Never a share-safe artifact. */
    public function csv(array $rows): string
    {
        $f=fopen('php://temp','w+');
        fputcsv($f,self::COLUMNS,',','"','');
        foreach($rows as $row){
            if(array_diff(array_keys($row),self::COLUMNS))throw new \InvalidArgumentException('QC_UNKNOWN_COLUMN');
            fputcsv($f,array_map(static fn($v)=>$v===null?'':(string)$v,array_map(static fn($key)=>$row[$key]??null,self::COLUMNS)),',','"','');
        }
        rewind($f);$bytes=stream_get_contents($f);fclose($f);
        return $bytes;
    }

    public function safeSummary(array $rows): array
    {
        return [
            'schema'=>self::SCHEMA_VERSION,'rows'=>count($rows),
            'structural_blockers'=>count(array_filter($rows,fn($r)=>str_starts_with((string)($r['issue_severity']??''),'TECHNICAL_BLOCKER:'))),
            'independent_academic_pass'=>0,'rights_verified'=>0,'gp02'=>'OPEN_REVIEW_REQUIRED',
            'candidate_pack_30_59_120'=>'UNVERIFIED_NO_SOURCE_BYTES',
        ];
    }
}
