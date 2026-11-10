<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialMedia;
use App\Services\ClassMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassMediaManagementController extends Controller
{
    public function chapter(Request $request, LearningClass $class, ClassMediaService $service): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);
        $service->createChapter($request, $class, ['title' => $data['title'], 'sort_order' => $data['sort_order'] ?? 0]);
        return back()->with('success', 'Bab baru disimpan. Materi draft dapat dihubungkan ke bab tersebut.');
    }

    public function uploadPdf(Request $request, LearningClass $class, LearningMaterial $material, ClassMediaService $service): RedirectResponse
    {
        abort_unless((int) $material->class_id === (int) $class->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:20480'],
        ]);
        $service->addPdf($request, $class, $material, $data['pdf'], $data['title']);
        return back()->with('success', 'PDF disimpan secara privat pada materi draft.');
    }

    public function video(Request $request, LearningClass $class, LearningMaterial $material, ClassMediaService $service): RedirectResponse
    {
        abort_unless((int) $material->class_id === (int) $class->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'external_url' => ['required', 'url:https', 'max:500'],
        ]);
        $service->addVideoLink($request, $class, $material, $data['title'], $data['external_url']);
        return back()->with('success', 'Tautan video disimpan pada materi draft. Video tetap dihosting pihak ketiga.');
    }

    public function remove(Request $request, LearningClass $class, LearningMaterial $material, LearningMaterialMedia $media, ClassMediaService $service): RedirectResponse
    {
        $request->validate(['confirmed' => ['required', 'accepted']]);
        $service->deleteDraftMedia($request, $class, $material, $media);
        return back()->with('success', 'Lampiran draft dihapus.');
    }

    public function download(LearningClass $class, LearningMaterial $material, LearningMaterialMedia $media): StreamedResponse
    {
        abort_unless((int) $material->class_id === (int) $class->id && (int) $media->material_id === (int) $material->id, 404);
        abort_unless($media->kind === 'pdf' && $media->private_path, 404);
        abort_unless(Storage::disk('class_media_private')->exists($media->private_path), 404);
        return Storage::disk('class_media_private')->download($media->private_path, 'materi-'.$material->id.'-'.$media->id.'.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
