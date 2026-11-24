<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberClassMediaController extends Controller
{
    public function pdf(Request $request, LearningClass $class, LearningMaterial $material, LearningMaterialMedia $media): StreamedResponse
    {
        abort_unless((int) $material->class_id === (int) $class->id && (int) $media->material_id === (int) $material->id, 404);
        abort_unless($material->status === 'published' && $media->kind === 'pdf' && $media->private_path, 404);
        abort_unless(ClassEnrollment::query()->where('class_id', $class->id)
            ->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 404);
        $disk = Storage::disk('class_media_private');
        abort_unless($disk->exists($media->private_path), 404);
        // FIG-P4 E02/V02: never serve corrupted/restored bytes merely because
        // their object key exists. This GET is strictly read-only.
        $root = realpath($disk->path(''));
        $real = realpath($disk->path($media->private_path));
        abort_unless($root && $real && !is_link($disk->path($media->private_path))
            && str_starts_with($real, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            && is_file($real) && is_readable($real)
            && is_int($media->size_bytes) && $media->size_bytes > 0
            && filesize($real) === $media->size_bytes
            && is_string($media->sha256) && preg_match('/^[a-f0-9]{64}$/D', $media->sha256)
            && hash_equals($media->sha256, (string) hash_file('sha256', $real))
            && file_get_contents($real, false, null, 0, 5) === '%PDF-', 404);
        return $disk->download($media->private_path, 'materi-'.$material->id.'-'.$media->id.'.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
