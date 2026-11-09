<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionEditorialService
{
    private function options(array $input): array
    {
        $rows = [];
        foreach (['A','B','C','D','E'] as $position => $label) {
            $body = trim((string) ($input['option_'.strtolower($label)] ?? ''));
            if ($body === '') continue;
            $rows[] = ['label'=>$label, 'body'=>$body, 'is_correct'=>$label === $input['correct_option'], 'sort_order'=>$position + 1];
        }
        return $rows;
    }

    /**
     * Bandingkan substansi yang ditampilkan kepada peserta, bukan ID atau timestamp.
     * Draft yang sama tidak perlu disimpan lagi. Nilai opsi dinormalisasi seperti saat disimpan.
     */
    private function snapshotFromInput(array $input): array
    {
        return [
            'body' => trim((string) $input['body']),
            'explanation' => trim((string) $input['explanation']),
            'options' => array_map(
                fn (array $option) => [$option['label'], $option['body'], (bool) $option['is_correct']],
                $this->options($input),
            ),
        ];
    }

    private function snapshotFromVersion(QuestionVersion $version): array
    {
        $version->loadMissing('options');
        return [
            'body' => trim((string) $version->body),
            'explanation' => trim((string) $version->explanation),
            'options' => $version->options->sortBy('sort_order')->values()
                ->map(fn ($option) => [$option->label, trim((string) $option->body), (bool) $option->is_correct])->all(),
        ];
    }

    private function content(QuestionVersion $version, array $input): void
    {
        if ($version->review_status !== 'draft' || $version->published_at !== null) {
            throw ValidationException::withMessages(['body'=>'Versi yang sudah terbit tidak boleh diubah.']);
        }
        $version->body = trim($input['body']);
        $version->explanation = trim($input['explanation']);
        $version->save();
        $version->options()->delete();
        $version->options()->createMany($this->options($input));
    }

    private function audit(User $actor, string $action, Question $question, array $before, array $after): void
    {
        AuditLog::query()->create([
            'actor_id'=>$actor->id, 'action'=>$action,
            'subject_type'=>Question::class, 'subject_id'=>$question->id,
            'before_summary'=>$before, 'after_summary'=>$after, 'created_at'=>now(),
        ]);
    }

    public function create(array $input, User $actor): Question
    {
        return DB::transaction(function () use ($input, $actor) {
            $question = Question::query()->create([
                'topic_id'=>$input['topic_id'], 'code'=>$input['code'], 'status'=>'draft', 'created_by'=>$actor->id,
            ]);
            $version = $question->versions()->create(['version_number'=>1, 'body'=>'', 'review_status'=>'draft']);
            $this->content($version, $input);
            $this->audit($actor, 'question.create', $question, [], ['code'=>$question->code, 'version'=>1, 'status'=>'draft']);
            return $question;
        }, 3);
    }

    public function saveDraft(Question $question, array $input, User $actor): string
    {
        return DB::transaction(function () use ($question, $input, $actor): string {
            $q = Question::query()->lockForUpdate()->findOrFail($question->id);
            if ($q->status === 'archived') throw ValidationException::withMessages(['body'=>'Soal arsip tidak dapat diedit.']);
            if ($q->code !== $input['code']) throw ValidationException::withMessages(['code'=>'Kode tidak dapat diubah.']);
            if ($q->current_version_id && (int) $q->topic_id !== (int) $input['topic_id']) {
                throw ValidationException::withMessages(['topic_id'=>'Tidak bisa memindahkan kategori soal yang sudah terbit.']);
            }
            $previous = $q->versions()->orderByDesc('version_number')->first();
            $topicUnchanged = (int) $q->topic_id === (int) $input['topic_id'];
            if ($previous && $topicUnchanged && $this->snapshotFromInput($input) === $this->snapshotFromVersion($previous)) {
                return 'unchanged';
            }
            if ($previous && $previous->review_status === 'draft') {
                $draft = $previous;
            } else {
                $draft = $q->versions()->create([
                    'version_number'=>(int) ($previous?->version_number ?? 0) + 1,
                    'body'=>'', 'review_status'=>'draft',
                ]);
            }
            $q->update(['topic_id'=>$input['topic_id']]);
            $this->content($draft, $input);
            $this->audit($actor, 'question.draft_save', $q, ['version'=>$previous?->version_number, 'status'=>$q->status], ['draft_version'=>$draft->version_number]);
            return 'saved';
        }, 3);
    }

    public function publish(Question $question, User $actor): void
    {
        DB::transaction(function () use ($question, $actor) {
            $q = Question::query()->lockForUpdate()->findOrFail($question->id);
            if ($q->status === 'archived') throw ValidationException::withMessages(['publish'=>'Soal arsip tidak dapat diterbitkan.']);
            $draft = $q->versions()->with('options')->orderByDesc('version_number')->first();
            if (!$draft || $draft->review_status !== 'draft') throw ValidationException::withMessages(['publish'=>'Tidak ada draft baru untuk diterbitkan.']);
            $options = $draft->options;
            if ($q->current_version_id !== null) {
                $active = $q->currentVersion()->with('options')->firstOrFail();
                if ($this->snapshotFromVersion($draft) === $this->snapshotFromVersion($active)) {
                    throw ValidationException::withMessages([
                        'publish' => 'Isi revisi sama dengan versi terbit. Batalkan draft revisi agar tidak menerbitkan versi duplikat.',
                    ]);
                }
            }
            if (trim($draft->body) === '' || trim((string)$draft->explanation) === '' || !in_array($options->count(), [4,5], true)
                || $options->where('is_correct', true)->count() !== 1) {
                throw ValidationException::withMessages(['publish'=>'Soal wajib memiliki pertanyaan, pembahasan, 4–5 opsi, dan tepat satu jawaban benar.']);
            }
            $hashInput = ['body'=>$draft->body, 'explanation'=>$draft->explanation,
                'options'=>$options->map(fn($o)=>[$o->label, $o->body, (bool)$o->is_correct])->values()->all()];
            $draft->update([
                'review_status'=>'published', 'reviewed_by'=>$actor->id, 'reviewed_at'=>now(),
                'published_at'=>now(), 'version_hash'=>hash('sha256', json_encode($hashInput, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            ]);
            $before = ['status'=>$q->status, 'current_version_id'=>$q->current_version_id];
            $q->update(['status'=>'published', 'current_version_id'=>$draft->id]);
            $this->audit($actor, 'question.publish', $q, $before, ['status'=>'published', 'published_version'=>$draft->version_number]);
        }, 3);
    }

    /** Hapus hanya draft terakhir yang belum terbit; versi terbit dan attempt tidak disentuh. */
    public function discardDraft(Question $question, User $actor, string $reason): void
    {
        DB::transaction(function () use ($question, $actor, $reason) {
            $q = Question::query()->lockForUpdate()->findOrFail($question->id);
            if ($q->status !== 'published' || $q->current_version_id === null) {
                throw ValidationException::withMessages(['discard' => 'Hanya draft revisi dari soal yang masih terbit yang dapat dibatalkan.']);
            }
            $draft = $q->versions()->orderByDesc('version_number')->first();
            if (!$draft || $draft->review_status !== 'draft' || $draft->published_at !== null
                || (int) $draft->id === (int) $q->current_version_id) {
                throw ValidationException::withMessages(['discard' => 'Tidak ada draft revisi yang dapat dibatalkan.']);
            }
            $before = [
                'status' => $q->status,
                'active_version_id' => $q->current_version_id,
                'discarded_draft_id' => $draft->id,
                'discarded_draft_version' => $draft->version_number,
            ];
            // Foreign key pada referensi versi lain akan menolak penghapusan dan rollback transaksi.
            $draft->options()->delete();
            $draft->delete();
            $this->audit($actor, 'question.draft_discard', $q, $before, [
                'status' => 'published', 'active_version_id' => $q->current_version_id,
                'reason' => trim($reason),
            ]);
        }, 3);
    }

    public function archive(Question $question, User $actor, string $reason): void
    {
        DB::transaction(function () use ($question, $actor, $reason) {
            $q = Question::query()->lockForUpdate()->findOrFail($question->id);
            if ($q->status === 'archived') throw ValidationException::withMessages(['archive'=>'Soal ini sudah diarsipkan.']);
            $before = ['status'=>$q->status];
            $q->update(['status'=>'archived']);
            $this->audit($actor, 'question.archive', $q, $before, ['status'=>'archived', 'reason'=>trim($reason)]);
        }, 3);
    }
}
