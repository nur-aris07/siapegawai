<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Published snapshots are append-only; the material row is the latest public projection. */
class LearningMaterialRevisionService
{
    public function snapshotFirstPublish(LearningMaterial $locked, ?int $adminId): void
    {
        if ($locked->status !== 'published') {
            throw ValidationException::withMessages(['material' => 'Materi belum diterbitkan.']);
        }
        LearningMaterialVersion::query()->create([
            ...$this->fields($locked), 'material_id' => $locked->id,
            'version_number' => 1, 'status' => 'published',
            'published_at' => $locked->published_at ?? now(), 'published_by' => $adminId,
        ]);
        $locked->update(['published_version_number' => 1]);
    }

    public function start(Request $request, LearningClass $class, LearningMaterial $material): void
    {
        DB::transaction(function () use ($request, $class, $material): void {
            $locked = $this->lockPublished($class, $material);
            $number = $this->ensureInitialSnapshot($locked);
            $draft = LearningMaterialVersion::query()->where('material_id', $locked->id)
                ->where('version_number', $number + 1)->first();
            if ($draft) {
                if ($draft->status !== 'draft') {
                    throw ValidationException::withMessages(['material' => 'Status versi materi tidak konsisten.']);
                }
                return; // repeat clicks do not create multiple drafts
            }
            LearningMaterialVersion::query()->create([
                ...$this->fields($locked), 'material_id' => $locked->id,
                'version_number' => $number + 1, 'status' => 'draft',
            ]);
            $this->audit($request, 'class.material_revision_started', $locked->id,
                ['class_id' => $class->id, 'draft_version' => $number + 1]);
        }, 3);
    }

    public function saveDraft(Request $request, LearningClass $class, LearningMaterial $material, array $data): void
    {
        DB::transaction(function () use ($request, $class, $material, $data): void {
            $locked = $this->lockPublished($class, $material);
            $draft = $this->lockDraft($locked);
            $draft->fill($data);
            if (!$draft->isDirty()) return;
            $fields = array_keys($draft->getDirty());
            $draft->save();
            $this->audit($request, 'class.material_revision_draft_saved', $locked->id,
                ['class_id' => $class->id, 'draft_version' => $draft->version_number,
                    'changed_fields' => $fields]);
        }, 3);
    }

    public function publish(Request $request, LearningClass $class, LearningMaterial $material, string $reason): void
    {
        DB::transaction(function () use ($request, $class, $material, $reason): void {
            $locked = $this->lockPublished($class, $material);
            $draft = $this->lockDraft($locked);
            $before = (int) $locked->published_version_number;
            $draft->update([
                'status' => 'published', 'published_at' => now(),
                'published_by' => $request->user()->id,
            ]);
            $locked->fill([
                ...$this->fields($draft), 'published_version_number' => $draft->version_number,
            ])->save();
            // Completion rows, timestamp and completed_version_number intentionally never change.
            $this->audit($request, 'class.material_revision_published', $locked->id,
                ['class_id' => $class->id, 'from_version' => $before,
                    'to_version' => $draft->version_number, 'reason' => $reason]);
        }, 3);
    }

    private function lockPublished(LearningClass $class, LearningMaterial $material): LearningMaterial
    {
        $locked = LearningMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();
        abort_unless((int) $locked->class_id === (int) $class->id, 404);
        if ($locked->status !== 'published') {
            throw ValidationException::withMessages(['material' => 'Revisi hanya untuk materi yang sudah terbit.']);
        }
        return $locked;
    }

    private function ensureInitialSnapshot(LearningMaterial $locked): int
    {
        $number = (int) $locked->published_version_number;
        if ($number > 0) return $number;
        // Defensive path for explicitly created published fixtures/legacy imported rows.
        $this->snapshotFirstPublish($locked, $locked->learningClass?->created_by);
        return 1;
    }

    private function lockDraft(LearningMaterial $locked): LearningMaterialVersion
    {
        $draft = LearningMaterialVersion::query()
            ->where('material_id', $locked->id)
            ->where('version_number', (int) $locked->published_version_number + 1)
            ->lockForUpdate()->first();
        if (!$draft || $draft->status !== 'draft') {
            throw ValidationException::withMessages(['material' => 'Buat draft revisi terlebih dahulu.']);
        }
        return $draft;
    }

    private function fields(LearningMaterial|LearningMaterialVersion $material): array
    {
        return ['title' => $material->title, 'content' => $material->content,
            'sort_order' => $material->sort_order, 'chapter_id' => $material->chapter_id];
    }

    private function audit(Request $request, string $action, int $materialId, array $summary): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->id, 'action' => $action,
            'subject_type' => 'learning_material', 'subject_id' => $materialId,
            'before_summary' => null, 'after_summary' => $summary, 'created_at' => now(),
        ]);
    }
}
