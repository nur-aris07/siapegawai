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
        return $disk->download($media->private_path, 'materi-'.$material->id.'-'.$media->id.'.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
