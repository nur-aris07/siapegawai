<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\User;
use App\Services\ClassEnrollmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClassManagementController extends Controller
{
    public function index(): View
    {
        $classes=LearningClass::query()->withCount(['materials','enrollments'=>fn($q)=>$q->where('status','active')])
            ->latest('id')->paginate(15);
        return view('admin.classes.index', compact('classes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$request->validate([
            'title'=>['required','string','min:5','max:160'],
            'description'=>['required','string','min:15','max:3000'],
            'syllabus'=>['nullable','string','max:6000'],
            'capacity'=>['nullable','integer','min:1','max:100000'],
        ]);
        $class=DB::transaction(function () use ($data,$request): LearningClass {
            $class=LearningClass::query()->create([
                ...$data,
                'slug'=>Str::limit(Str::slug($data['title']),130,'').'-'.Str::lower(Str::random(8)),
                'status'=>'draft','created_by'=>$request->user()->id,
            ]);
            $this->audit($request,'class.created','class',$class->id,null,['status'=>'draft']);
            return $class;
        },3);
        return redirect()->route('admin.classes.edit',$class)->with('success','Kelas draft dibuat. Tambahkan materi sebelum membukanya.');
    }

    public function edit(LearningClass $class): View
    {
        return view('admin.classes.edit', [
            'learningClass'=>$class,
            'materials'=>$class->materials()->orderBy('sort_order')->orderBy('id')->get(),
            'enrollments'=>$class->enrollments()->with('user:id,name,email,status')->where('status','active')->orderByDesc('id')->limit(100)->get(),
        ]);
    }

    public function update(Request $request, LearningClass $class): RedirectResponse
    {
        $data=$request->validate([
            'title'=>['required','string','min:5','max:160'],
            'description'=>['required','string','min:15','max:3000'],
            'syllabus'=>['nullable','string','max:6000'],
            'capacity'=>['nullable','integer','min:1','max:100000'],
            'status'=>['required',Rule::in(['draft','open','closed'])],
        ]);
        DB::transaction(function () use ($class,$data,$request): void {
            $locked=LearningClass::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();
            if ($data['status']==='open' && !$locked->materials()->where('status','published')->exists()) {
                throw ValidationException::withMessages(['status'=>'Terbitkan minimal satu materi sebelum kelas dibuka.']);
            }
            if ($data['status']==='draft' && $locked->enrollments()->where('status','active')->exists()) {
                throw ValidationException::withMessages(['status'=>'Kelas yang sudah memiliki peserta aktif tidak bisa dikembalikan ke draft.']);
            }
            if (isset($data['capacity']) && $data['capacity'] < $locked->enrollments()->where('status','active')->count()) {
                throw ValidationException::withMessages(['capacity'=>'Kapasitas tidak boleh lebih kecil dari jumlah peserta aktif.']);
            }
            $before=['status'=>$locked->status];
            $locked->fill($data);
            if (!$locked->isDirty()) return;
            $locked->save();
            $this->audit($request,'class.updated','class',$locked->id,$before,['status'=>$locked->status]);
        },3);
        return back()->with('success','Perubahan kelas disimpan.');
    }

    public function addMaterial(Request $request, LearningClass $class): RedirectResponse
    {
        $data=$request->validate([
            'title'=>['required','string','min:3','max:160'],
            'content'=>['required','string','min:20','max:20000'],
            'sort_order'=>['nullable','integer','min:0','max:65535'],
        ]);
        DB::transaction(function () use ($request,$class,$data): void {
            $material=$class->materials()->create([
                ...$data,'sort_order'=>$data['sort_order']??0,'status'=>'draft',
            ]);
            $this->audit($request,'class.material_created','learning_material',$material->id,null,['class_id'=>$class->id,'status'=>'draft']);
        },3);
        return back()->with('success','Materi draft dibuat. Tinjau sebelum diterbitkan.');
    }

    public function updateDraftMaterial(Request $request, LearningClass $class, LearningMaterial $material): RedirectResponse
    {
        abort_unless((int) $material->class_id === (int) $class->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'content' => ['required', 'string', 'min:20', 'max:20000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        DB::transaction(function () use ($request, $class, $material, $data): void {
            $locked = LearningMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->class_id === (int) $class->id, 404);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['material' => 'Materi yang sudah diterbitkan tidak dapat diubah.']);
            }
            $before = ['status' => 'draft', 'title' => $locked->title, 'sort_order' => $locked->sort_order];
            $locked->fill($data);
            if (!$locked->isDirty()) {
                return;
            }
            $changed = array_keys($locked->getDirty());
            $locked->save();
            // No raw lesson body is included in audit data.
            $this->audit($request, 'class.material_draft_updated', 'learning_material', $locked->id, $before,
                ['class_id' => $class->id, 'status' => 'draft', 'title' => $locked->title,
                    'sort_order' => $locked->sort_order, 'changed_fields' => $changed]);
        }, 3);

        return back()->with('success', 'Draft materi diperbarui. Terbitkan setelah ditinjau.');
    }

    public function publishMaterial(Request $request, LearningClass $class, LearningMaterial $material): RedirectResponse
    {
        abort_unless((int)$material->class_id===(int)$class->id,404);
        DB::transaction(function () use ($request,$class,$material): void {
            $locked=LearningMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();
            abort_unless((int)$locked->class_id===(int)$class->id,404);
            if ($locked->status!=='draft') {
                throw ValidationException::withMessages(['material'=>'Materi ini sudah diterbitkan.']);
            }
            $locked->update(['status'=>'published','published_at'=>now()]);
            $this->audit($request,'class.material_published','learning_material',$locked->id,['status'=>'draft'],['class_id'=>$class->id,'status'=>'published']);
        },3);
        return back()->with('success','Materi diterbitkan untuk peserta yang memiliki enrollment aktif.');
    }

    public function enroll(Request $request, LearningClass $class, ClassEnrollmentService $service): RedirectResponse
    {
        abort_unless($request->user()?->role==='superadmin',403);
        $data=$request->validate([
            'user_id'=>['required','integer','exists:users,id'],
            'reason'=>['required','string','min:12','max:500'],
            'confirmed'=>['required','accepted'],
        ]);
        $service->grant($class,User::query()->findOrFail($data['user_id']),$request->user(),$data['reason']);
        return back()->with('success','Akses kelas diberikan kepada peserta.');
    }

    public function revoke(Request $request, LearningClass $class, User $user, ClassEnrollmentService $service): RedirectResponse
    {
        abort_unless($request->user()?->role==='superadmin',403);
        $data=$request->validate([
            'reason'=>['required','string','min:12','max:500'],
            'confirmed'=>['required','accepted'],
        ]);
        $service->revoke($class,$user,$request->user(),$data['reason']);
        return back()->with('success','Akses kelas peserta dicabut. Riwayat tidak dihapus.');
    }

    private function audit(Request $request,string $action,string $subjectType,int $subjectId,?array $before, array $after): void
    {
        AuditLog::query()->create([
            'actor_id'=>$request->user()->id,'action'=>$action,
            'subject_type'=>$subjectType,'subject_id'=>$subjectId,
            'before_summary'=>$before,'after_summary'=>$after,'created_at'=>now(),
        ]);
    }
}
