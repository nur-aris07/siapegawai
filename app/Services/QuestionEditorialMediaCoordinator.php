<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\QuestionVersionMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** FIG-P2: draft-only media orchestration. Never updates or destroys immutable bytes. */
final class QuestionEditorialMediaCoordinator
{
    public function __construct(private readonly QuestionMediaStorageService $storage) {}

    public function editToken(Question $question): string
    {
        $fresh = Question::query()->findOrFail($question->id);
        $last = $fresh->versions()->with(['options', 'media'])->orderByDesc('version_number')->first();
        $data = [
            (int) $fresh->id, $fresh->code, $fresh->status, (int) $fresh->topic_id,
            (int) $fresh->current_version_id, (int) ($last?->id ?? 0),
            $last?->review_status, $last?->body, $last?->explanation,
            $last?->options->sortBy('sort_order')->values()->map(fn ($o) => [$o->label, $o->body, (bool) $o->is_correct])->all(),
            $last?->media->sortBy([['slot', 'asc'], ['position', 'asc']])->values()->map(
                fn ($m) => [(int) $m->id, $m->slot, (int) $m->position, $m->sha256, $m->alt_text, $m->caption]
            )->all(),
        ];
        return hash_hmac('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function checkToken(Question $question, ?string $token, bool $required = true): void
    {
        if (!$required && ($token === null || $token === '')) return; // legacy text-only HTTP compatibility
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)
            || !hash_equals($this->editToken($question), $token)) {
            throw ValidationException::withMessages(['edit_token' => 'Form sudah berubah. Muat ulang halaman sebelum menyimpan.']);
        }
    }

    public function draftFor(Question $question): QuestionVersion
    {
        $fresh = Question::query()->findOrFail($question->id);
        if ($fresh->status === 'archived') abort(403);
        $draft = $fresh->versions()->orderByDesc('version_number')->firstOrFail();
        if ($draft->review_status !== 'draft' || $draft->published_at !== null
            || (int) $fresh->current_version_id === (int) $draft->id) {
            throw ValidationException::withMessages(['media' => 'Simpan atau mulai draft revisi sebelum mengelola gambar.']);
        }
        return $draft;
    }

    public function withLockedDraft(Question $question, ?string $token, callable $action): mixed
    {
        return DB::transaction(function () use ($question, $token, $action) {
            $locked = Question::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
            $this->checkToken($locked, $token);
            $draft = $this->draftFor($locked);
            QuestionVersion::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            return $action($locked, $draft);
        }, 3);
    }

    /** Copies every byte under a new private immutable object key (P1 object_key UNIQUE). */
    public function cloneMedia(QuestionVersion $from, QuestionVersion $to): void
    {
        foreach ($from->media()->orderBy('slot')->orderBy('position')->get() as $source) {
            $this->cloneSingle($source, $to, (int) $source->position, $source->alt_text, $source->caption);
        }
    }

    private function cloneSingle(QuestionVersionMedia $source, QuestionVersion $target, int $position, ?string $alt, ?string $caption): QuestionVersionMedia
    {
        if ($target->review_status !== 'draft' || $target->published_at !== null) abort(403);
        if (!$this->storage->verify($source->object_key, $source->sha256)) {
            throw ValidationException::withMessages(['media' => 'Sumber gambar tidak ditemukan atau checksum berbeda.']);
        }
        $bytes = Storage::disk(QuestionMediaStorageService::DISK)->get($source->object_key);
        $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$source->mime_type] ?? null;
        if ($ext === null || hash('sha256', $bytes) !== $source->sha256) {
            throw ValidationException::withMessages(['media' => 'Konten gambar tidak sesuai metadata.']);
        }
        // Storage failure can leave orphan bytes, but never publish an incorrect DB reference.
        $key = $this->storage->storeImmutable(['bytes' => $bytes, 'sha256' => $source->sha256, 'extension' => $ext]);
        $new = new QuestionVersionMedia();
        $new->question_version_id = $target->id;
        $new->slot = $source->slot;
        $new->position = $position;
        $new->object_key = $key;
        $new->sha256 = $source->sha256;
        $new->mime_type = $source->mime_type;
        $new->byte_size = $source->byte_size;
        $new->width_px = $source->width_px;
        $new->height_px = $source->height_px;
        $new->alt_text = $alt;
        $new->caption = $caption;
        $new->save();
        return $new;
    }


    /**
     * Apply ONE editor-wide organization to existing draft references. A move,
     * order or metadata change creates new immutable objects/rows; no UPDATE of
     * existing media records and no deletion of physical bytes. An explicit
     * removal flag is required to detach a reference.
     *
     * @param array<int,mixed> $ids
     * @param array<int,mixed> $slots
     * @param array<int,mixed> $alts
     * @param array<int,mixed> $captions
     * @param array<int,mixed> $removes
     */
    public function organize(Question $question, User $actor, array $ids, array $slots,
        array $positions, array $alts, array $captions, array $removes, ?string $token): void
    {
        abort_unless($actor->can('update', $question), 403);
        $this->withLockedDraft($question, $token, function (Question $q, QuestionVersion $draft)
            use ($actor,$ids,$slots,$positions,$alts,$captions,$removes): void {
            $existing = $draft->media()->orderBy('slot')->orderBy('position')->get();
            $expected = $existing->pluck('id')->map(fn($id)=>(int)$id)->sort()->values()->all();
            $received = array_map('intval',$ids);
            $sorted = $received; sort($sorted);
            if (count($ids) !== count($slots) || count($ids) !== count($positions) || count($ids) !== count($alts)
                || count($ids) !== count($captions) || count($ids) !== count($removes)
                || count($received) !== count(array_unique($received)) || $sorted !== $expected) {
                throw ValidationException::withMessages(['media_existing_ids'=>'Daftar gambar berubah. Muat ulang editor sebelum menyimpan.']);
            }
            $byId = $existing->keyBy('id');
            $countPerSlot = array_fill_keys(['stem','option_A','option_B','option_C','option_D','option_E','explanation'],0);
            $usedPositions = array_fill_keys(array_keys($countPerSlot),[]);
            $plan = [];
            foreach ($received as $i=>$id) {
                $slot = $slots[$i] ?? null;
                $alt = trim((string) ($alts[$i] ?? ''));
                $caption = trim((string) ($captions[$i] ?? ''));
                $remove = (string) ($removes[$i] ?? '0');
                if (!array_key_exists((string) $slot, $countPerSlot) || !in_array($remove,['0','1'],true)
                    || mb_strlen($alt)>300 || mb_strlen($caption)>300) {
                    throw ValidationException::withMessages(['media_existing_ids'=>'Bagian, metadata, atau pilihan lepas gambar tidak valid.']);
                }
                if ($remove === '1') continue;
                $position = filter_var($positions[$i] ?? null, FILTER_VALIDATE_INT);
                if ($position===false || $position<1 || $position>8 || in_array($position,$usedPositions[$slot],true)) {
                    throw ValidationException::withMessages(['media_existing_ids'=>'Posisi gambar duplikat atau melebihi delapan gambar per bagian.']);
                }
                $usedPositions[$slot][] = $position;
                $countPerSlot[$slot]++;
                $original = $byId->get($id);
                $plan[] = [$original, $slot, $position, $alt !== '' ? $alt : null, $caption !== '' ? $caption : null];
            }
            $same = count($plan) === $existing->count();
            foreach ($plan as [$original,$slot,$position,$alt,$caption]) {
                if ($original->slot !== $slot || (int)$original->position !== $position
                    || $original->alt_text !== $alt || $original->caption !== $caption) $same=false;
            }
            if ($same) return;
            // Pre-verify EVERY source before reference changes. DB rollback protects the
            // whole reordering if any write/unique constraint fails.
            $replacements=[];
            foreach ($plan as [$original,$slot,$position,$alt,$caption]) {
                if (!$this->storage->verify($original->object_key,$original->sha256)) {
                    throw ValidationException::withMessages(['media_existing_ids'=>'Ada gambar yang hilang atau checksum berubah.']);
                }
                $changed=$original->slot!==$slot || (int)$original->position!==$position
                    || $original->alt_text!==$alt || $original->caption!==$caption;
                $key=$original->object_key;
                if ($changed) {
                    $bytes=Storage::disk(QuestionMediaStorageService::DISK)->get($original->object_key);
                    $ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$original->mime_type]??null;
                    if (!$ext || hash('sha256',$bytes)!==$original->sha256) abort(422);
                    $key=$this->storage->storeImmutable(['bytes'=>$bytes,'sha256'=>$original->sha256,'extension'=>$ext]);
                }
                $replacements[]=[$original,$slot,$position,$alt,$caption,$key];
            }
            // Rows are recreated, never UPDATEd; published versions and old bytes untouched.
            foreach ($existing as $media) $media->delete();
            foreach ($replacements as [$original,$slot,$position,$alt,$caption,$key]) {
                $copy=$original->replicate();
                $copy->question_version_id=$draft->id;
                $copy->slot=$slot;
                $copy->position=$position;
                $copy->alt_text=$alt;
                $copy->caption=$caption;
                $copy->object_key=$key;
                $copy->save();
            }
            if (($countPerSlot['option_E']??0)>0 && !$draft->options()->where('label','E')->exists()) {
                $draft->options()->create(['label'=>'E','body'=>'','is_correct'=>false,'sort_order'=>5]);
            }
            $draft->touch();
            $this->audit($actor,'question.media_organized',$draft,['before_count'=>$existing->count(),'after_count'=>count($replacements)]);
        });
    }

    public function reorder(Question $question, User $actor, string $slot, array $ids, ?string $token): void
    {
        $this->withLockedDraft($question, $token, function (Question $q, QuestionVersion $draft) use ($actor, $slot, $ids): void {
            if (!in_array($slot, ['stem','option_A','option_B','option_C','option_D','option_E','explanation'], true)) abort(422);
            $items = $draft->media()->where('slot', $slot)->orderBy('position')->get();
            $existing = $items->pluck('id')->map(fn ($n) => (int) $n)->sort()->values()->all();
            $requested = array_map('intval', $ids);
            $sorted = $requested; sort($sorted);
            if (count($requested) !== count(array_unique($requested)) || $sorted !== $existing || count($requested) < 2) {
                throw ValidationException::withMessages(['order' => 'Urutan harus memuat semua gambar di slot ini, masing-masing tepat sekali.']);
            }
            if ($items->pluck('id')->map(fn($n) => (int) $n)->values()->all() === $requested) return;
            $indexed = $items->keyBy('id');
            // Build all new physical keys while old rows remain intact; FK/DB rollback preserves originals.
            $replacement = [];
            foreach ($requested as $i => $id) {
                $original = $indexed->get($id);
                $bytes = Storage::disk(QuestionMediaStorageService::DISK)->get($original->object_key);
                if (!$this->storage->verify($original->object_key, $original->sha256)) {
                    throw ValidationException::withMessages(['media' => 'Gambar tidak tersedia.']);
                }
                $ext = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$original->mime_type] ?? null;
                if (!$ext || hash('sha256', $bytes) !== $original->sha256) abort(422);
                $replacement[] = [$original, $i + 1,
                    $this->storage->storeImmutable(['bytes'=>$bytes,'sha256'=>$original->sha256,'extension'=>$ext])];
            }
            foreach ($items as $item) $item->delete();
            foreach ($replacement as [$src, $pos, $key]) {
                $new = $src->replicate();
                $new->question_version_id = $draft->id;
                $new->position = $pos;
                $new->object_key = $key;
                $new->save();
            }
            $draft->touch();
            $this->audit($actor, 'question.media_reordered', $draft, ['slot'=>$slot,'count'=>count($replacement)]);
        });
    }

    public function changeMetadata(Question $question, User $actor, int $mediaId, ?string $alt, ?string $caption, ?string $token): void
    {
        $this->withLockedDraft($question, $token, function (Question $q, QuestionVersion $draft) use ($actor,$mediaId,$alt,$caption): void {
            $m = $draft->media()->whereKey($mediaId)->lockForUpdate()->firstOrFail();
            foreach (['alt' => $alt,'caption' => $caption] as $name => $value) {
                if ($value !== null && (mb_strlen($value, 'UTF-8') > 300 || preg_match('/[\x00-\x08\x0B\x0E-\x1F]/', $value))) {
                    throw ValidationException::withMessages([$name => 'Teks metadata tidak valid.']);
                }
            }
            $alt = trim((string) $alt) ?: null;
            $caption = trim((string) $caption) ?: null;
            if ($m->alt_text === $alt && $m->caption === $caption) return;
            if (!$this->storage->verify($m->object_key, $m->sha256)) {
                throw ValidationException::withMessages(['media'=>'Gambar tidak tersedia atau rusak.']);
            }
            $bytes = Storage::disk(QuestionMediaStorageService::DISK)->get($m->object_key);
            $ext = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$m->mime_type] ?? null;
            if (!$ext || hash('sha256', $bytes) !== $m->sha256) abort(422);
            $key = $this->storage->storeImmutable(['bytes'=>$bytes,'sha256'=>$m->sha256,'extension'=>$ext]);
            $new = $m->replicate();
            $m->delete();
            $new->object_key = $key;
            $new->alt_text = $alt;
            $new->caption = $caption;
            $new->save();
            $draft->touch();
            $this->audit($actor,'question.media_metadata_updated',$draft,['slot'=>$new->slot,'position'=>$new->position]);
        });
    }

    public function audit(User $actor, string $event, QuestionVersion $version, array $safe): void
    {
        AuditLog::query()->create([
            'actor_id'=>$actor->id, 'action'=>$event,
            'subject_type'=>Question::class, 'subject_id'=>$version->question_id,
            'before_summary'=>null, 'after_summary'=>['version_id'=>$version->id] + $safe,
            'created_at'=>now(),
        ]);
    }
}
