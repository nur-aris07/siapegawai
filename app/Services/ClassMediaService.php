<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClassChapter;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialMedia;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ClassMediaService
{
    private const DISK = 'class_media_private';

    public function createChapter(Request $request, LearningClass $class, array $data): ClassChapter
    {
        return DB::transaction(function () use ($request, $class, $data): ClassChapter {
            $chapter = $class->chapters()->create($data);
            $this->audit($request, 'class.chapter_created', 'class_chapter', $chapter->id, ['class_id' => $class->id]);
            return $chapter;
        }, 3);
    }

    public function addPdf(Request $request, LearningClass $class, LearningMaterial $material, UploadedFile $file, string $title): void
    {
        $this->assertDraft($class, $material);
        $path = $file->getRealPath();
        $header = is_string($path) ? file_get_contents($path, false, null, 0, 5) : false;
        if ($header !== '%PDF-') {
            throw ValidationException::withMessages(['pdf' => 'File tidak memiliki header PDF yang valid.']);
        }
        // Name never comes from user input. Private disk is outside the public storage symlink.
        $key = 'classes/'.$class->id.'/materials/'.$material->id.'/'.Str::uuid().'.pdf';
        $stored = Storage::disk(self::DISK)->putFileAs(dirname($key), $file, basename($key));
        if ($stored !== $key) {
            throw new RuntimeException('Gagal menyimpan berkas privat.');
        }
        try {
            DB::transaction(function () use ($request, $class, $material, $file, $title, $key, $path): void {
                $this->lockDraft($class, $material);
                $media = LearningMaterialMedia::query()->create([
                    'material_id' => $material->id, 'kind' => 'pdf', 'title' => $title,
                    'private_path' => $key, 'original_name' => Str::limit(basename($file->getClientOriginalName()), 160, ''),
                    'size_bytes' => (int) $file->getSize(), 'sha256' => hash_file('sha256', $path),
                ]);
                $this->audit($request, 'class.media_added', 'learning_material_media', $media->id,
                    ['class_id' => $class->id, 'material_id' => $material->id, 'kind' => 'pdf', 'size_bytes' => $media->size_bytes]);
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk(self::DISK)->delete($key);
            throw $exception;
        }
    }

    public function addVideoLink(Request $request, LearningClass $class, LearningMaterial $material, string $title, string $url): void
    {
        $url = trim($url);
        if (!self::allowedVideoUrl($url)) {
            throw ValidationException::withMessages(['external_url' => 'Gunakan tautan HTTPS YouTube atau Vimeo yang valid.']);
        }
        DB::transaction(function () use ($request, $class, $material, $title, $url): void {
            $this->lockDraft($class, $material);
            $media = LearningMaterialMedia::query()->create([
                'material_id' => $material->id, 'kind' => 'video_link', 'title' => $title, 'external_url' => $url,
            ]);
            $this->audit($request, 'class.media_added', 'learning_material_media', $media->id,
                ['class_id' => $class->id, 'material_id' => $material->id, 'kind' => 'video_link']);
        }, 3);
    }

    public function deleteDraftMedia(Request $request, LearningClass $class, LearningMaterial $material, LearningMaterialMedia $media): void
    {
        abort_unless((int) $media->material_id === (int) $material->id, 404);
        $path = DB::transaction(function () use ($request, $class, $material, $media): ?string {
            $this->lockDraft($class, $material);
            $locked = LearningMaterialMedia::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->material_id === (int) $material->id, 404);
            $path = $locked->kind === 'pdf' ? $locked->private_path : null;
            $locked->delete();
            $this->audit($request, 'class.media_removed', 'learning_material_media', $media->id,
                ['class_id' => $class->id, 'material_id' => $material->id, 'kind' => $locked->kind]);
            return $path;
        }, 3);
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function allowedVideoUrl(string $url): bool
    {
        if (strlen($url) > 500 || !filter_var($url, FILTER_VALIDATE_URL)) return false;
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) return false;
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if ($path !== '/watch') return false;
            parse_str($parts['query'] ?? '', $q);
            return is_string($q['v'] ?? null) && (bool) preg_match('/^[A-Za-z0-9_-]{11}$/D', $q['v']);
        }
        if ($host === 'youtu.be' || $host === 'www.youtu.be') {
            return (bool) preg_match('~^/[A-Za-z0-9_-]{11}$~D', $path);
        }
        if ($host === 'vimeo.com' || $host === 'www.vimeo.com') {
            return (bool) preg_match('~^/[0-9]{5,15}$~D', $path);
        }
        return false;
    }

    private function assertDraft(LearningClass $class, LearningMaterial $material): void
    {
        abort_unless((int) $material->class_id === (int) $class->id, 404);
        if ($material->status !== 'draft') {
            throw ValidationException::withMessages(['material' => 'Lampiran materi published tidak dapat diubah.']);
        }
    }

    private function lockDraft(LearningClass $class, LearningMaterial $material): void
    {
        $locked = LearningMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();
        $this->assertDraft($class, $locked);
    }

    private function audit(Request $request, string $action, string $subjectType, int $subjectId, array $summary): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->id, 'action' => $action,
            'subject_type' => $subjectType, 'subject_id' => $subjectId,
            'before_summary' => null, 'after_summary' => $summary, 'created_at' => now(),
        ]);
    }
}
