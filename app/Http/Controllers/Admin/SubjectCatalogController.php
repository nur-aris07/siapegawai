<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\SubjectCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SubjectCatalogController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny',Subject::class);
        return view('admin.subjects.index',[
            'subjects'=>Subject::query()->withCount('topics')->orderBy('sort_order')->orderBy('code')->get(),
        ]);
    }

    public function show(Subject $subject): View
    {
        Gate::authorize('view',$subject);
        return view('admin.subjects.show',[
            'subject'=>$subject,
            'topics'=>$subject->topics()->withCount('questions')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, SubjectCatalogService $service): RedirectResponse
    {
        Gate::authorize('create',Subject::class);
        $input=$request->validate([
            'code'=>['required','string','max:12','regex:/^[A-Za-z][A-Za-z0-9-]*$/',Rule::unique('subjects','code')],
            'name'=>['required','string','min:3','max:120'],
            'sort_order'=>['nullable','integer','between:0,9999'],
        ]);
        $subject=$service->createSubject($input,$request->user());
        return redirect()->route('subjects.admin.show',$subject)->with('success','Kategori baru ditambahkan.');
    }

    public function update(Request $request, Subject $subject, SubjectCatalogService $service): RedirectResponse
    {
        Gate::authorize('update',$subject);
        $input=$request->validate([
            'name'=>['required','string','min:3','max:120'],
            'status'=>['required',Rule::in(['active','inactive'])],
            'sort_order'=>['required','integer','between:0,9999'],
        ]);
        $service->updateSubject($subject,$input,$request->user());
        return back()->with('success','Kategori disimpan. Kode tetap tidak berubah.');
    }

    public function destroy(Request $request, Subject $subject, SubjectCatalogService $service): RedirectResponse
    {
        Gate::authorize('delete',$subject);
        $request->validate(['confirmed_delete'=>['required','accepted']]);
        $service->deleteSubject($subject,$request->user());
        return redirect()->route('subjects.admin.index')->with('success','Kategori kosong dihapus.');
    }

    public function storeTopic(Request $request, Subject $subject, SubjectCatalogService $service): RedirectResponse
    {
        Gate::authorize('update',$subject);
        $input=$request->validate(['name'=>['required','string','min:2','max:150'],'sort_order'=>['nullable','integer','between:0,9999']]);
        $service->createTopic($subject,$input,$request->user());
        return back()->with('success','Topik berhasil ditambahkan.');
    }

    public function updateTopic(Request $request, Topic $topic, SubjectCatalogService $service): RedirectResponse
    {
        Gate::authorize('update',$topic->subject);
        $input=$request->validate([
            'name'=>['required','string','min:2','max:150'],
            'status'=>['required',Rule::in(['active','inactive'])],
            'sort_order'=>['required','integer','between:0,9999'],
        ]);
        $service->updateTopic($topic,$input,$request->user());
        return redirect()->route('subjects.admin.show',$topic->subject_id)->with('success','Topik diperbarui.');
    }

    public function destroyTopic(Request $request, Topic $topic, SubjectCatalogService $service): RedirectResponse
    {
        Gate::authorize('delete',$topic->subject);
        $request->validate(['confirmed_delete'=>['required','accepted']]);
        $service->deleteTopic($topic,$request->user());
        return redirect()->route('subjects.admin.show',$topic->subject_id)->with('success','Topik tanpa soal dihapus.');
    }
}
