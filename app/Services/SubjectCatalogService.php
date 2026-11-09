<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubjectCatalogService
{
    private function audit(User $actor, string $action, string $type, int $id, array $before, array $after): void
    {
        AuditLog::query()->create(['actor_id'=>$actor->id,'action'=>$action,'subject_type'=>$type,
            'subject_id'=>$id,'before_summary'=>$before,'after_summary'=>$after,'created_at'=>now()]);
    }

    public function createSubject(array $data, User $actor): Subject
    {
        return DB::transaction(function () use ($data, $actor) {
            $subject = Subject::query()->create([
                'code'=>strtoupper($data['code']), 'name'=>trim($data['name']),
                'status'=>'active', 'sort_order'=>$data['sort_order'] ?? 0,
            ]);
            $this->audit($actor,'subject.create',Subject::class,$subject->id,[],['code'=>$subject->code,'name'=>$subject->name]);
            return $subject;
        }, 3);
    }

    public function updateSubject(Subject $subject, array $data, User $actor): void
    {
        DB::transaction(function () use ($subject,$data,$actor) {
            $locked=Subject::query()->lockForUpdate()->findOrFail($subject->id);
            $before=$locked->only('code','name','status','sort_order');
            // Kode kategori tidak diubah karena menjadi identitas stabil seluruh soal.
            $locked->update(['name'=>trim($data['name']),'status'=>$data['status'],'sort_order'=>$data['sort_order']]);
            $after=$locked->only('code','name','status','sort_order');
            if ($before !== $after) $this->audit($actor,'subject.update',Subject::class,$locked->id,$before,$after);
        }, 3);
    }

    public function deleteSubject(Subject $subject, User $actor): void
    {
        DB::transaction(function () use ($subject,$actor) {
            $locked=Subject::query()->lockForUpdate()->findOrFail($subject->id);
            if ($locked->topics()->exists()) {
                throw ValidationException::withMessages(['subject'=>'Kategori yang memiliki topik tidak boleh dihapus. Nonaktifkan saja jika tidak lagi digunakan.']);
            }
            $before=$locked->only('code','name','status');
            $locked->delete();
            $this->audit($actor,'subject.delete',Subject::class,$subject->id,$before,['deleted'=>true]);
        }, 3);
    }

    public function createTopic(Subject $subject, array $data, User $actor): Topic
    {
        return DB::transaction(function () use ($subject,$data,$actor) {
            $locked=Subject::query()->lockForUpdate()->findOrFail($subject->id);
            if ($locked->status !== 'active') throw ValidationException::withMessages(['subject'=>'Aktifkan kategori sebelum menambahkan topik.']);
            $slug=Str::slug($data['name']);
            if ($slug === '' || Topic::query()->where('subject_id',$locked->id)->where('slug',$slug)->exists()) {
                throw ValidationException::withMessages(['name'=>'Nama topik sudah digunakan dalam kategori ini atau tidak valid.']);
            }
            $topic=$locked->topics()->create(['name'=>trim($data['name']),'slug'=>$slug,'status'=>'active','sort_order'=>$data['sort_order'] ?? 0]);
            $this->audit($actor,'topic.create',Topic::class,$topic->id,[],['subject_id'=>$locked->id,'name'=>$topic->name]);
            return $topic;
        }, 3);
    }

    public function updateTopic(Topic $topic, array $data, User $actor): void
    {
        DB::transaction(function () use ($topic,$data,$actor) {
            $locked=Topic::query()->lockForUpdate()->findOrFail($topic->id);
            $before=$locked->only('subject_id','name','slug','status','sort_order');
            // Relasi kategori/slug tetap; soal terbit dapat menggunakan topik lama.
            $locked->update(['name'=>trim($data['name']),'status'=>$data['status'],'sort_order'=>$data['sort_order']]);
            $after=$locked->only('subject_id','name','slug','status','sort_order');
            if ($before !== $after) $this->audit($actor,'topic.update',Topic::class,$locked->id,$before,$after);
        }, 3);
    }

    public function deleteTopic(Topic $topic, User $actor): void
    {
        DB::transaction(function () use ($topic,$actor) {
            $locked=Topic::query()->lockForUpdate()->findOrFail($topic->id);
            if ($locked->questions()->exists()) {
                throw ValidationException::withMessages(['topic'=>'Topik yang memiliki soal tidak boleh dihapus. Nonaktifkan untuk menyimpan hubungan historis.']);
            }
            $before=$locked->only('subject_id','name','slug','status');
            $locked->delete();
            $this->audit($actor,'topic.delete',Topic::class,$topic->id,$before,['deleted'=>true]);
        }, 3);
    }
}
