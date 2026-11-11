<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\QuickQuiz;
use App\Models\QuickQuizVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** QQ-CORE-01: editorial only. No member attempt or quota paths are registered. */
class QuickQuizEditorialService
{
    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function audit(User $actor, string $action, QuickQuiz $quiz, array $before, array $after): void
    {
        AuditLog::query()->create([
            'actor_id'=>$actor->id, 'action'=>$action,
            'subject_type'=>QuickQuiz::class, 'subject_id'=>$quiz->id,
            'before_summary'=>$before, 'after_summary'=>$after, 'created_at'=>now(),
        ]);
    }

    /** Existing pins may persist if the bank later publishes a new version. New pins must be current. */
    private function validatePins(QuickQuizVersion $draft, array $ids): array
    {
        $ids = array_map('intval', $ids);
        if (count($ids) > 40 || count($ids) !== count(array_unique($ids))) {
            $this->reject('version_ids','Maksimal 40 versi soal berbeda dalam satu quiz.');
        }
        $oldIds = $draft->items()->pluck('question_version_id')->map(fn ($id)=>(int)$id)->all();
        $seenQuestions=[];
        foreach ($ids as $id) {
            $v=QuestionVersion::query()->with(['question.topic.subject','options'])->find($id);
            if (!$v || $v->review_status !== 'published' || !$v->published_at || !$v->question) {
                $this->reject('version_ids','Semua soal harus menggunakan versi terbit.');
            }
            if (isset($seenQuestions[$v->question_id])) {
                $this->reject('version_ids','Satu soal tidak boleh dipilih melalui dua versi berbeda.');
            }
            $seenQuestions[$v->question_id]=true;
            if (!in_array($id,$oldIds,true)) {
                $q=Question::query()->with('topic.subject')->lockForUpdate()->findOrFail($v->question_id);
                if ($q->status !== 'published' || (int)$q->current_version_id !== $id ||
                    $q->topic?->status !== 'active' || $q->topic?->subject?->status !== 'active') {
                    $this->reject('version_ids','Soal baru harus berasal dari versi aktif dan kategori/topik aktif.');
                }
            }
            // Quiz Kilat v1 has binary scoring only; weighted options belong to another assessment domain.
            if ($v->options->count() < 4 || $v->options->count() > 5 ||
                $v->options->where('is_correct',true)->count() !== 1 ||
                $v->options->contains(fn ($option) => $option->score_weight !== null) ||
                trim($v->body) === '' || trim((string)$v->explanation) === '') {
                $this->reject('version_ids','Quiz Kilat membutuhkan 4–5 pilihan, satu jawaban benar, dan satu pembahasan tanpa skor berbobot.');
            }
        }
        return $ids;
    }

    private function replaceItems(QuickQuizVersion $draft, array $ids): void
    {
        $validated=$this->validatePins($draft, $ids);
        $draft->items()->delete();
        foreach ($validated as $index=>$id) {
            $draft->items()->create(['question_version_id'=>$id,'sort_order'=>$index+1]);
        }
    }

    public function create(array $data, User $actor): QuickQuiz
    {
        return DB::transaction(function () use ($data,$actor) {
            $quiz=QuickQuiz::query()->create(['code'=>strtoupper(trim($data['code'])),'status'=>'draft','created_by'=>$actor->id]);
            $draft=$quiz->versions()->create([
                'version_number'=>1,'status'=>'draft','title'=>trim($data['title']),
                'description'=>trim((string)($data['description']??'')) ?: null,
                'seconds_per_question'=>$data['seconds_per_question'],
            ]);
            $this->replaceItems($draft,$data['version_ids']??[]);
            $this->audit($actor,'qq.create',$quiz,[],['draft_version'=>1,'items'=>$draft->items()->count()]);
            return $quiz;
        },3);
    }

    public function update(QuickQuiz $quiz, array $data, User $actor): void
    {
        DB::transaction(function () use($quiz,$data,$actor) {
            $locked=QuickQuiz::query()->lockForUpdate()->findOrFail($quiz->id);
            if ($locked->status === 'archived') $this->reject('title','Quiz arsip tidak dapat diperbarui.');
            $draft=$locked->draftVersion();
            if (!$draft) $this->reject('title','Buat draft revisi sebelum mengubah quiz terbit.');
            $before=['title'=>$draft->title,'description'=>$draft->description,'seconds_per_question'=>$draft->seconds_per_question,
                'version_ids'=>$draft->items()->pluck('question_version_id')->all()];
            $draft->update(['title'=>trim($data['title']),
                'description'=>trim((string)($data['description']??'')) ?: null,
                'seconds_per_question'=>$data['seconds_per_question']]);
            $this->replaceItems($draft,$data['version_ids']??[]);
            $after=['title'=>$draft->title,'description'=>$draft->description,'seconds_per_question'=>$draft->seconds_per_question,
                'version_ids'=>$draft->items()->pluck('question_version_id')->all()];
            if ($before!==$after) $this->audit($actor,'qq.draft_update',$locked,$before,$after);
        },3);
    }

    public function startRevision(QuickQuiz $quiz, User $actor): QuickQuizVersion
    {
        return DB::transaction(function () use($quiz,$actor) {
            $locked=QuickQuiz::query()->lockForUpdate()->findOrFail($quiz->id);
            if ($locked->status !== 'published' || !$locked->current_version_id) {
                $this->reject('revision','Hanya quiz terbit yang dapat direvisi.');
            }
            $existing=$locked->draftVersion();
            if ($existing) return $existing; // repeat-click is idempotent
            $current=$locked->currentVersion()->firstOrFail();
            $next=$locked->versions()->max('version_number') + 1;
            $draft=$locked->versions()->create([
                'version_number'=>$next,'status'=>'draft','title'=>$current->title,
                'description'=>$current->description,'seconds_per_question'=>$current->seconds_per_question,
            ]);
            foreach ($current->items()->get() as $item) {
                $draft->items()->create(['question_version_id'=>$item->question_version_id,'sort_order'=>$item->sort_order]);
            }
            $this->audit($actor,'qq.revision_start',$locked,['version'=>$current->version_number],['draft_version'=>$next]);
            return $draft;
        },3);
    }

    public function publish(QuickQuiz $quiz, User $actor): void
    {
        DB::transaction(function () use($quiz,$actor) {
            $locked=QuickQuiz::query()->lockForUpdate()->findOrFail($quiz->id);
            if ($locked->status === 'archived') $this->reject('publish','Quiz arsip tidak dapat diterbitkan.');
            $draft=$locked->draftVersion();
            if (!$draft) $this->reject('publish','Tidak ada draft untuk diterbitkan.');
            $items=$draft->items()->with('version.question.topic.subject','version.options')->get();
            if ($items->count() < 30 || $items->count() > 40) {
                $this->reject('publish','Quiz Kilat harus berisi 30–40 soal sebelum diterbitkan.');
            }
            // Recheck pins at publish time; this does not mutate previous published editions.
            $ids=$this->validatePins($draft,$items->pluck('question_version_id')->all());
            foreach ($items as $item) {
                $q=$item->version?->question;
                if (!$q || $q->status !== 'published' || !$q->topic?->subject) {
                    $this->reject('publish','Terdapat soal yang tidak lagi layak terbit.');
                }
                $item->update([
                    'subject_code_snapshot'=>$q->topic->subject->code,
                    'subject_name_snapshot'=>$q->topic->subject->name,
                    'topic_name_snapshot'=>$q->topic->name,
                ]);
            }
            $draft->update(['status'=>'published','published_at'=>now(),'published_by'=>$actor->id]);
            $oldId=$locked->current_version_id;
            $locked->update(['status'=>'published','current_version_id'=>$draft->id]);
            $this->audit($actor,'qq.publish',$locked,['version_id'=>$oldId],
                ['version_id'=>$draft->id,'version'=>$draft->version_number,'count'=>count($ids)]);
        },3);
    }

    public function archive(QuickQuiz $quiz, User $actor, string $reason): void
    {
        DB::transaction(function () use($quiz,$actor,$reason) {
            $locked=QuickQuiz::query()->lockForUpdate()->findOrFail($quiz->id);
            if ($locked->status!=='published' || $locked->draftVersion()) {
                $this->reject('archive','Arsipkan hanya quiz terbit tanpa draft revisi yang terbuka.');
            }
            $locked->update(['status'=>'archived','archived_at'=>now()]);
            $this->audit($actor,'qq.archive',$locked,['status'=>'published'],['status'=>'archived','reason'=>trim($reason)]);
        },3);
    }
}
