<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\QuestionVersionMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionEditorialService
{
    public function __construct(
        private readonly QuestionEditorialMediaCoordinator $mediaCoordinator,
        private readonly QuestionVersionCanonicalizer $canonicalizer,
        private readonly QuestionMediaStorageService $mediaStorage,
    ) {}

    private function options(array $input): array
    {
        $rows = [];
        foreach (['A','B','C','D','E'] as $position => $label) {
            $body = trim((string) ($input['option_'.strtolower($label)] ?? ''));
            if ($body === '' && !($input['figural_draft'] ?? false) && !($input['draft_shell'] ?? false)) continue;
            if ($label === 'E' && $body === '' && ($input['correct_option'] ?? '') !== 'E'
                && !($input['option_e_media_exists'] ?? false)) continue;
            $rows[] = ['label'=>$label, 'body'=>$body, 'is_correct'=>$label === ($input['correct_option'] ?? ''), 'sort_order'=>$position + 1];
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
            'body' => trim((string) ($input['body'] ?? '')),
            'explanation' => trim((string) ($input['explanation'] ?? '')),
            'options' => array_map(
                fn (array $option) => [$option['label'], $option['body'], (bool) $option['is_correct']],
                $this->options($input),
            ),
            // Media references are handled separately; input never controls persisted version media.
            'media' => [],
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
            'media' => $version->media()->orderBy('slot')->orderBy('position')->get()
                ->map(fn ($m) => [$m->slot, (int) $m->position, $m->sha256, $m->mime_type,
                    $m->width_px, $m->height_px, $m->alt_text, $m->caption])->all(),
        ];
    }

    private function content(QuestionVersion $version, array $input): void
    {
        if ($version->review_status !== 'draft' || $version->published_at !== null) {
            throw ValidationException::withMessages(['body'=>'Versi yang sudah terbit tidak boleh diubah.']);
        }
        $version->body = trim((string) ($input['body'] ?? ''));
        $version->explanation = trim((string) ($input['explanation'] ?? ''));
        $version->save();
        $version->options()->delete();
        $input['option_e_media_exists'] = $version->media()->where('slot','option_E')->exists();
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

    public function saveDraft(Question $question, array $input, User $actor, ?string $token = null): string
    {
        return DB::transaction(function () use ($question, $input, $actor, $token): string {
            $q = Question::query()->lockForUpdate()->findOrFail($question->id);
            $latestBefore = $q->versions()->orderByDesc('version_number')->first();
            $this->mediaCoordinator->checkToken($q, $token, $latestBefore?->media()->exists() === true || !empty($input['figural_draft']));
            if ($q->status === 'archived') throw ValidationException::withMessages(['body'=>'Soal arsip tidak dapat diedit.']);
            if ($q->code !== $input['code']) throw ValidationException::withMessages(['code'=>'Kode tidak dapat diubah.']);
            if ($q->current_version_id && (int) $q->topic_id !== (int) $input['topic_id']) {
                throw ValidationException::withMessages(['topic_id'=>'Tidak bisa memindahkan kategori soal yang sudah terbit.']);
            }
            $previous = $q->versions()->orderByDesc('version_number')->first();
            $topicUnchanged = (int) $q->topic_id === (int) $input['topic_id'];
            $beforeSnapshot = $previous ? $this->snapshotFromVersion($previous) : null;
            $inputSnapshot = $this->snapshotFromInput($input);
            if ($previous) {
                // Keep attached media unchanged when the same form values are submitted.
                $inputSnapshot['media'] = $beforeSnapshot['media'];
                if ($previous->media()->where('slot','option_E')->exists()) {
                    $inputSnapshot['options'] = array_values(array_filter($inputSnapshot['options'],
                        fn ($row) => $row[0] !== 'E'));
                    $inputSnapshot['options'][] = ['E', trim((string)($input['option_e'] ?? '')), ($input['correct_option'] ?? '') === 'E'];
                }
            }
            if ($previous && $topicUnchanged && $inputSnapshot === $beforeSnapshot) {
                return 'unchanged';
            }
            if ($previous && $previous->review_status === 'draft') {
                $draft = $previous;
            } else {
                $draft = $q->versions()->create([
                    'version_number'=>(int) ($previous?->version_number ?? 0) + 1,
                    'body'=>'', 'review_status'=>'draft',
                ]);
                if ($previous && $previous->review_status === 'published') {
                    $this->mediaCoordinator->cloneMedia($previous, $draft);
                }
            }
            $q->update(['topic_id'=>$input['topic_id']]);
            $this->content($draft, $input);
            $this->audit($actor, 'question.draft_save', $q, ['version'=>$previous?->version_number, 'status'=>$q->status], ['draft_version'=>$draft->version_number]);
            return 'saved';
        }, 3);
    }

    public function publish(Question $question, User $actor, ?string $token = null): void
    {
        DB::transaction(function () use ($question, $actor, $token) {
            $q = Question::query()->lockForUpdate()->findOrFail($question->id);
            if ($q->status === 'archived') throw ValidationException::withMessages(['publish'=>'Soal arsip tidak dapat diterbitkan.']);
            $draft = $q->versions()->with(['options','media'])->orderByDesc('version_number')->first();
            if (!$draft || $draft->review_status !== 'draft') throw ValidationException::withMessages(['publish'=>'Tidak ada draft baru untuk diterbitkan.']);
            $hasMedia = $draft->media->isNotEmpty();
            $this->mediaCoordinator->checkToken($q, $token, $hasMedia);
            // No enabled-by-default toggle. Figural publishing is restricted to explicitly configured isolated QA.
            if ($hasMedia && !(app()->environment('testing')
                && DB::connection()->getDatabaseName() === 'siapegawai_testing'
                && config('question_media.figural_publish_qa') === true)) {
                throw ValidationException::withMessages(['publish' => 'Publikasi soal bergambar masih dikunci sampai FIG-P3/P4 diterima. Draft dan preview admin tetap tersedia.']);
            }
            $options = $draft->options->sortBy('sort_order')->values();
            $mediaSlots = $draft->media->groupBy('slot');
            $stemValid = mb_strlen(trim((string)$draft->body)) >= 12 || $mediaSlots->has('stem');
            $explanationValid = mb_strlen(trim((string)$draft->explanation)) >= 12 || $mediaSlots->has('explanation');
            $labels = $options->pluck('label')->values()->all();
            $validLabels = ($labels === ['A','B','C','D'] || $labels === ['A','B','C','D','E']);
            $validOptions = $options->every(fn ($o) => trim((string)$o->body) !== '' || $mediaSlots->has('option_'.$o->label));
            if ($mediaSlots->has('option_E') && !$options->contains('label', 'E')) {
                throw ValidationException::withMessages(['publish'=>'Media opsi E harus mempunyai baris opsi E yang terhubung.']);
            }
            if (!$stemValid || !$explanationValid || !$validLabels || !$validOptions
                || $options->where('is_correct', true)->count() !== 1) {
                throw ValidationException::withMessages(['publish'=>'Pertanyaan, opsi A–D (E bila ada), kunci tunggal dan pembahasan teks/gambar wajib lengkap.']);
            }
            foreach ($draft->media as $m) {
                if (!$this->mediaStorage->verify($m->object_key, $m->sha256)) {
                    throw ValidationException::withMessages(['publish'=>'Ada media yang hilang atau berubah. Periksa kembali gambar sebelum menerbitkan.']);
                }
            }
            if ($q->current_version_id !== null) {
                $active = $q->currentVersion()->with(['options','media'])->firstOrFail();
                if ($this->snapshotFromVersion($draft) === $this->snapshotFromVersion($active)) {
                    throw ValidationException::withMessages([
                        'publish' => 'Isi revisi sama dengan versi terbit. Batalkan draft revisi agar tidak menerbitkan versi duplikat.',
                    ]);
                }
            }
            $draft->update([
                'review_status'=>'published', 'reviewed_by'=>$actor->id, 'reviewed_at'=>now(),
                'published_at'=>now(), 'version_hash'=>$this->canonicalizer->hashWithMedia($draft),
            ]);
            $before = ['status'=>$q->status, 'current_version_id'=>$q->current_version_id];
            $q->update(['status'=>'published', 'current_version_id'=>$draft->id]);
            $this->audit($actor, 'question.publish', $q, $before, ['status'=>'published', 'published_version'=>$draft->version_number]);
        }, 3);
    }

    /** Explicitly start a revision before attaching media. Published versions and bytes stay immutable. */
    public function startRevision(Question $question, User $actor, string $token): void
    {
        DB::transaction(function () use ($question, $actor, $token) {
            $q = Question::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
            $this->mediaCoordinator->checkToken($q, $token);
            if ($q->status !== 'published' || !$q->current_version_id) {
                throw ValidationException::withMessages(['revision'=>'Revisi hanya dapat dimulai dari versi yang telah terbit.']);
            }
            $last = $q->versions()->orderByDesc('version_number')->firstOrFail();
            if ($last->review_status === 'draft') return;
            $draft = $q->versions()->create([
                'version_number'=>(int) $last->version_number + 1,
                'body'=>$last->body, 'explanation'=>$last->explanation,
                'review_status'=>'draft',
            ]);
            $draft->options()->createMany($last->options()->get()->map(fn ($option) => [
                'label'=>$option->label,'body'=>$option->body,
                'is_correct'=>$option->is_correct,'sort_order'=>$option->sort_order,
            ])->all());
            $this->mediaCoordinator->cloneMedia($last, $draft);
            $this->audit($actor, 'question.revision_started', $q, ['version'=>$last->version_number], ['draft_version'=>$draft->version_number]);
        }, 3);
    }

    /** Hapus hanya draft terakhir yang belum terbit; versi terbit dan attempt tidak disentuh. */
    public function discardDraft(Question $question, User $actor, string $reason, ?string $token = null): void
    {
        DB::transaction(function () use ($question, $actor, $reason, $token) {
            $q = Question::query()->lockForUpdate()->findOrFail($question->id);
            $lastDraft = $q->versions()->orderByDesc('version_number')->first();
            $this->mediaCoordinator->checkToken($q, $token, $lastDraft?->media()->exists() === true);
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
            foreach ($draft->media()->get() as $media) $media->delete();
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
