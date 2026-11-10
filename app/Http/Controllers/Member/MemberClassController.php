<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Services\MemberLearningProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberClassController extends Controller
{
    public function index(Request $request, MemberLearningProgressService $service): View
    {
        $enrollments = ClassEnrollment::query()
            ->with('learningClass')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->orderByDesc('approved_at')
            ->paginate(10);

        $classIds = $enrollments->getCollection()->pluck('class_id')->map(fn ($id) => (int) $id)->all();
        $progress = $service->summaries($classIds, (int) $request->user()->id);

        return view('member.classes.index', compact('enrollments', 'progress'));
    }

    public function show(Request $request, LearningClass $class, MemberLearningProgressService $service): View
    {
        $this->assertEnrolled($class, (int) $request->user()->id);

        $materials = $class->materials()->where('status', 'published')
            ->with(['chapter', 'mediaAssets'])
            ->withCount(['completions as completed_by_me_count' => fn ($query) =>
                $query->where('user_id', $request->user()->id)])
            ->orderBy('sort_order')->orderBy('id')->get();
        $progress = $service->summaries([(int) $class->id], (int) $request->user()->id)[$class->id];

        return view('member.classes.show', ['learningClass' => $class, 'materials' => $materials, 'progress' => $progress]);
    }

    public function complete(Request $request, LearningClass $class, LearningMaterial $material): RedirectResponse
    {
        // URL identifiers cannot grant access; every write requires a live enrollment.
        abort_unless((int) $material->class_id === (int) $class->id, 404);
        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($class, $material, $userId): void {
            $enrollment = ClassEnrollment::query()->where('class_id', $class->id)
                ->where('user_id', $userId)->lockForUpdate()->first();
            abort_unless($enrollment?->status === 'active', 404);

            $published = LearningMaterial::query()->whereKey($material->id)
                ->where('class_id', $class->id)->where('status', 'published')->exists();
            abort_unless($published, 404);

            $now = now();
            // Unique (user_id, material_id) makes double clicks and retries idempotent.
            DB::table('learning_material_progress')->insertOrIgnore([
                'user_id' => $userId,
                'material_id' => $material->id,
                'completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, 3);

        return redirect()->route('member.classes.show', $class)->with('success', 'Materi ditandai selesai. Progres belajar tersimpan.');
    }

    private function assertEnrolled(LearningClass $class, int $userId): void
    {
        abort_unless(ClassEnrollment::query()
            ->where('class_id', $class->id)
            ->where('user_id', $userId)
            ->where('status', 'active')->exists(), 404);
    }
}
