<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Services\LearningMaterialRevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LearningMaterialRevisionController extends Controller
{
    public function start(Request $request, LearningClass $class, LearningMaterial $material,
        LearningMaterialRevisionService $service): RedirectResponse
    {
        abort_unless((int) $material->class_id === (int) $class->id, 404);
        $service->start($request, $class, $material);
        return back()->with('success', 'Draft revisi tersedia. Materi peserta belum berubah.');
    }

    public function update(Request $request, LearningClass $class, LearningMaterial $material,
        LearningMaterialRevisionService $service): RedirectResponse
    {
        abort_unless((int) $material->class_id === (int) $class->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'content' => ['required', 'string', 'min:20', 'max:20000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'chapter_id' => ['nullable', 'integer', Rule::exists('class_chapters', 'id')->where('class_id', $class->id)],
        ]);
        $service->saveDraft($request, $class, $material, $data);
        return back()->with('success', 'Draft revisi disimpan. Edisi terbit tidak berubah.');
    }

    public function publish(Request $request, LearningClass $class, LearningMaterial $material,
        LearningMaterialRevisionService $service): RedirectResponse
    {
        abort_unless((int) $material->class_id === (int) $class->id, 404);
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'],
            'reason' => ['required', 'string', 'min:12', 'max:500'],
        ]);
        $service->publish($request, $class, $material, $data['reason']);
        return back()->with('success', 'Revisi diterbitkan. Riwayat penyelesaian peserta tetap tersimpan.');
    }
}
