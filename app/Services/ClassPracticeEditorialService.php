<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClassPractice;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Isolated class editorial workflow; never modifies CAT templates or questions. */
class ClassPracticeEditorialService
{
    private function audit(User $actor, string $action, ClassPractice $practice, array $before, array $after): void
    {
        AuditLog::query()->create([
            'actor_id'=>$actor->id, 'action'=>$action, 'subject_type'=>ClassPractice::class,
            'subject_id'=>$practice->id, 'before_summary'=>$before, 'after_summary'=>$after, 'created_at'=>now(),
        ]);
    }

    private function selectedVersions(ClassPractice $practice, array $ids): array
    {
        $ids=array_map('intval', $ids);
        if (count($ids)>100 || count($ids)!==count(array_unique($ids))) {
            throw ValidationException::withMessages(['version_ids'=>'Maksimal 100 soal, tanpa versi duplikat.']);
        }
        $existing=$practice->exists ? $practice->items()->pluck('question_version_id')->map(fn($v)=>(int)$v)->all() : [];
        $seenQuestions=[];
        foreach ($ids as $id) {
            $version=QuestionVersion::query()->with('question.topic.subject')->find($id);
            if (!$version || $version->review_status!=='published' || !$version->published_at || !$version->question) {
                throw ValidationException::withMessages(['version_ids'=>'Pilih versi soal yang sudah diterbitkan.']);
            }
            $question=$version->question;
            if (isset($seenQuestions[$question->id])) {
                throw ValidationException::withMessages(['version_ids'=>'Satu soal hanya dapat dipakai satu kali dalam latihan.']);
            }
            $seenQuestions[$question->id]=true;
            if (!in_array($id,$existing,true)) {
                $current=Question::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
                if ($current->status!=='published' || (int)$current->current_version_id!==$id ||
                    $question->topic?->status!=='active' || $question->topic?->subject?->status!=='active') {
                    throw ValidationException::withMessages(['version_ids'=>'Soal baru harus memakai versi aktif dan kategori/topik aktif.']);
                }
            }
        }
        return $ids;
    }

    private function syncItems(ClassPractice $practice, array $ids): void
    {
        $selected=$this->selectedVersions($practice,$ids);
        $practice->items()->delete();
        foreach ($selected as $position=>$id) {
            $practice->items()->create(['question_version_id'=>$id, 'sort_order'=>$position+1]);
        }
    }

    private function validateMaterial(LearningClass $class, ?int $id): void
    {
        if ($id!==null && !LearningMaterial::query()->whereKey($id)->where('class_id',$class->id)
            ->where('status','published')->exists()) {
            throw ValidationException::withMessages(['material_id'=>'Materi harus sudah terbit dan berada dalam kelas ini.']);
        }
    }

    public function create(LearningClass $class, array $data, User $actor): ClassPractice
    {
        return DB::transaction(function() use($class,$data,$actor): ClassPractice {
            $locked=LearningClass::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();
            $this->validateMaterial($locked,isset($data['material_id'])?(int)$data['material_id']:null);
            $practice=ClassPractice::query()->create([
                'class_id'=>$locked->id, 'material_id'=>$data['material_id']??null,
                'title'=>trim($data['title']), 'description'=>trim((string)($data['description']??''))?:null,
                'duration_minutes'=>$data['duration_minutes'], 'max_attempts'=>$data['max_attempts'],
                'sort_order'=>$data['sort_order']??0, 'status'=>'draft', 'created_by'=>$actor->id,
            ]);
            $this->syncItems($practice,$data['version_ids']??[]);
            $this->audit($actor,'class.practice_created',$practice,[],['class_id'=>$class->id,'status'=>'draft','items'=>$practice->items()->count()]);
            return $practice;
        },3);
    }

    public function update(ClassPractice $practice, array $data, User $actor): void
    {
        DB::transaction(function() use($practice,$data,$actor): void {
            $locked=ClassPractice::query()->whereKey($practice->id)->lockForUpdate()->firstOrFail();
            if ($locked->status!=='draft') throw ValidationException::withMessages(['practice'=>'Latihan terbit tidak dapat diubah.']);
            $class=LearningClass::query()->whereKey($locked->class_id)->firstOrFail();
            $this->validateMaterial($class,isset($data['material_id'])?(int)$data['material_id']:null);
            $before=['title'=>$locked->title,'version_ids'=>$locked->items()->pluck('question_version_id')->all()];
            $locked->update([
                'title'=>trim($data['title']),'description'=>trim((string)($data['description']??''))?:null,
                'material_id'=>$data['material_id']??null,'duration_minutes'=>$data['duration_minutes'],
                'max_attempts'=>$data['max_attempts'],'sort_order'=>$data['sort_order']??0,
            ]);
            $this->syncItems($locked,$data['version_ids']??[]);
            $this->audit($actor,'class.practice_updated',$locked,$before,
                ['title'=>$locked->title,'version_ids'=>$locked->items()->pluck('question_version_id')->all()]);
        },3);
    }

    public function publish(ClassPractice $practice, User $actor): void
    {
        DB::transaction(function() use($practice,$actor): void {
            $locked=ClassPractice::query()->whereKey($practice->id)->lockForUpdate()->firstOrFail();
            if ($locked->status!=='draft') throw ValidationException::withMessages(['practice'=>'Hanya draft yang dapat diterbitkan.']);
            if ($locked->material_id!==null) $this->validateMaterial($locked->learningClass,(int)$locked->material_id);
            $items=$locked->items()->with(['version.options','version.question.topic.subject'])->get();
            if ($items->isEmpty()) throw ValidationException::withMessages(['practice'=>'Tambahkan minimal satu soal terbit.']);
            foreach ($items as $item) {
                $version=$item->version;
                $question=$version?->question;
                $topic=$question?->topic;
                $subject=$topic?->subject;
                if (!$version || $version->review_status!=='published' || !$version->published_at ||
                    !$question || $question->status!=='published' || !$topic || !$subject ||
                    $topic->status!=='active' || $subject->status!=='active') {
                    throw ValidationException::withMessages(['practice'=>'Ada soal atau kategori yang tidak layak digunakan.']);
                }
                $options=$version->options;
                if ($options->count()<2 || $options->where('is_correct',true)->count()!==1 || !trim((string)$version->explanation)) {
                    throw ValidationException::withMessages(['practice'=>'Setiap soal perlu pilihan, tepat satu kunci, dan pembahasan.']);
                }
                $item->update([
                    'subject_id_snapshot'=>$subject->id, 'subject_code_snapshot'=>$subject->code,
                    'subject_name_snapshot'=>$subject->name, 'topic_id_snapshot'=>$topic->id,
                    'topic_name_snapshot'=>$topic->name,
                ]);
            }
            $locked->update(['status'=>'published','published_at'=>now()]);
            $this->audit($actor,'class.practice_published',$locked,['status'=>'draft'],
                ['status'=>'published','class_id'=>$locked->class_id,'version_ids'=>$items->pluck('question_version_id')->all()]);
        },3);
    }

    public function archive(ClassPractice $practice, User $actor, string $reason): void
    {
        DB::transaction(function() use($practice,$actor,$reason): void {
            $locked=ClassPractice::query()->whereKey($practice->id)->lockForUpdate()->firstOrFail();
            if ($locked->status!=='published') throw ValidationException::withMessages(['practice'=>'Hanya latihan terbit yang dapat diarsipkan.']);
            $locked->update(['status'=>'archived','archived_at'=>now()]);
            $this->audit($actor,'class.practice_archived',$locked,['status'=>'published'],['status'=>'archived','reason'=>$reason]);
        },3);
    }
}
