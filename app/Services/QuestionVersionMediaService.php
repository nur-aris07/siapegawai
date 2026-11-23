<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\QuestionVersionMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Internal-only FIG-P1 API; P2 must add a separately reviewed editorial adapter.
 * All mutations require an active operator, draft status and locked version.
 */
final class QuestionVersionMediaService
{
    private const SLOTS = ['stem', 'option_A', 'option_B', 'option_C', 'option_D', 'option_E', 'explanation'];

    public function __construct(private readonly QuestionMediaStorageService $storage) {}

    public function addToDraft(
        User $actor,
        QuestionVersion $version,
        string $slot,
        int $position,
        UploadedFile $file,
        ?string $altText = null,
        ?string $caption = null,
    ): QuestionVersionMedia {
        $this->validateSlot($slot, $position);
        $this->assertDraftAuthorized($actor, $version);
        $altText = $this->metadata($altText);
        $caption = $this->metadata($caption);
        $inspected = $this->storage->inspect($file);
        // Early check avoids generating an orphan on ordinary exact retry.
        $existing = $version->media()->where('slot', $slot)->where('position', $position)->first();
        if ($existing && $existing->sha256 === $inspected['sha256']
            && $existing->alt_text === $altText && $existing->caption === $caption) {
            $this->assertDraftAuthorized($actor, $version);
            if (!$this->storage->verify($existing->object_key, $existing->sha256)) {
                throw ValidationException::withMessages(['media' => 'Referensi media tidak ditemukan atau checksum tidak cocok.']);
            }
            return $existing;
        }
        if ($existing) {
            throw ValidationException::withMessages(['slot' => 'Slot sudah terpakai. Gunakan detach draft yang eksplisit sebelum mengunggah ulang.']);
        }
        $this->assertDraftAuthorized($actor, $version);
        $key = $this->storage->storeImmutable($inspected);
        // If the DB transaction fails or concurrent writers win, leave an
        // unreferenced immutable object for safe manual orphan inventory.
        return DB::transaction(function () use ($actor, $version, $slot, $position, $key, $inspected, $altText, $caption): QuestionVersionMedia {
            $locked = $this->lockDraftAuthorized($actor, $version->id);
            if ($locked->media()->where('slot', $slot)->where('position', $position)->exists()) {
                throw ValidationException::withMessages(['slot' => 'Slot sudah diisi oleh penyunting lain.']);
            }
            if (!$this->storage->verify($key, $inspected['sha256'])) {
                throw ValidationException::withMessages(['media' => 'Objek media tidak lolos verifikasi checksum.']);
            }
            $m = new QuestionVersionMedia();
            $m->question_version_id = $locked->id;
            $m->slot = $slot;
            $m->position = $position;
            $m->object_key = $key;
            $m->sha256 = $inspected['sha256'];
            $m->mime_type = $inspected['mime_type'];
            $m->byte_size = $inspected['byte_size'];
            $m->width_px = $inspected['width_px'];
            $m->height_px = $inspected['height_px'];
            $m->alt_text = $altText;
            $m->caption = $caption;
            $m->save();
            $locked->touch();
            $this->audit($actor, 'question.media_added', $locked, $m->id, $slot, $position);
            return $m;
        }, 1);
    }

    /**
     * Detach draft reference ONLY; underlying immutable object remains and may
     * be inventoried for later, explicitly authorized orphan cleanup.
     */
    public function detachFromDraft(User $actor, QuestionVersionMedia $media): void
    {
        DB::transaction(function () use ($actor, $media): void {
            $lockedMedia = QuestionVersionMedia::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
            $locked = $this->lockDraftAuthorized($actor, $lockedMedia->question_version_id);
            $slot = $lockedMedia->slot;
            $position = (int) $lockedMedia->position;
            $id = $lockedMedia->id;
            $lockedMedia->delete();
            $locked->touch();
            $this->audit($actor, 'question.media_detached', $locked, $id, $slot, $position);
        }, 1);
    }

    /** @return array<int,array<string,mixed>> */
    public function safeMetadataForVersion(QuestionVersion $version): array
    {
        // No bytes, URLs or keys. Caller must separately authorize context.
        return $version->media()->orderBy('slot')->orderBy('position')->get()
            ->map(fn (QuestionVersionMedia $m) => $m->safeMetadata())->all();
    }

    /**
     * Read-only operations inventory. It NEVER deletes anything or changes DB state.
     * No HTTP route exists; caller must be an active superadmin.
     * @return array{orphan_keys:array<int,string>,missing_or_corrupt_keys:array<int,string>}
     */
    public function inventoryStorage(User $actor): array
    {
        abort_unless($actor->status === 'active' && $actor->role === 'superadmin', 403);
        $disk = Storage::disk(QuestionMediaStorageService::DISK);
        $references = QuestionVersionMedia::query()->get(['object_key', 'sha256'])->keyBy('object_key');
        $orphanKeys = [];
        foreach ($disk->allFiles('questions') as $key) {
            if (!$references->has($key)) $orphanKeys[] = $key;
        }
        $invalid = [];
        foreach ($references as $key => $reference) {
            if (!$this->storage->verify((string) $key, (string) $reference->sha256)) $invalid[] = (string) $key;
        }
        sort($orphanKeys);
        sort($invalid);
        return ['orphan_keys' => $orphanKeys, 'missing_or_corrupt_keys' => $invalid];
    }

    private function validateSlot(string $slot, int $position): void
    {
        $maxPosition = (int) config('question_media.max_position', 8);
        if (!in_array($slot, self::SLOTS, true) || $maxPosition <= 0 || $position < 1 || $position > $maxPosition) {
            throw ValidationException::withMessages(['slot' => 'Slot atau urutan media tidak valid.']);
        }
    }

    private function metadata(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        if ($value !== null && (mb_strlen($value, 'UTF-8') > 300 || preg_match('/[\x00-\x08\x0B\x0E-\x1F]/', $value))) {
            throw ValidationException::withMessages(['media' => 'Metadata media tidak valid.']);
        }
        return $value === '' ? null : $value;
    }

    private function assertDraftAuthorized(User $actor, QuestionVersion $version): void
    {
        $version->loadMissing('question');
        $question = $version->question;
        abort_unless($question instanceof Question && $actor->can('update', $question), 403);
        if ($question->status === 'archived' || $version->review_status !== 'draft'
            || $version->published_at !== null || (int) $question->current_version_id === (int) $version->id) {
            throw ValidationException::withMessages(['media' => 'Media hanya dapat diubah pada versi draft yang eligible.']);
        }
    }

    private function lockDraftAuthorized(User $actor, int $versionId): QuestionVersion
    {
        $locked = QuestionVersion::query()->whereKey($versionId)->lockForUpdate()->firstOrFail();
        $question = Question::query()->whereKey($locked->question_id)->lockForUpdate()->firstOrFail();
        $locked->setRelation('question', $question);
        $this->assertDraftAuthorized($actor, $locked);
        return $locked;
    }

    private function audit(User $actor, string $action, QuestionVersion $version, int $mediaId, string $slot, int $position): void
    {
        AuditLog::query()->create([
            'actor_id' => $actor->id, 'action' => $action,
            'subject_type' => QuestionVersionMedia::class, 'subject_id' => $mediaId,
            'before_summary' => null,
            'after_summary' => ['question_version_id' => $version->id, 'slot' => $slot, 'position' => $position],
            'created_at' => now(),
        ]);
    }
}
