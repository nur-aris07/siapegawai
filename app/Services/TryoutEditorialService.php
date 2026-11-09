<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\TryoutPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TryoutEditorialService
{
    private function audit(User $actor, string $action, TryoutPackage $package, array $before, array $after): void
    {
        AuditLog::query()->create(['actor_id'=>$actor->id,'action'=>$action,'subject_type'=>TryoutPackage::class,
            'subject_id'=>$package->id,'before_summary'=>$before,'after_summary'=>$after,'created_at'=>now()]);
    }

    /**
     * Versi yang sudah dipilih di draft boleh dipertahankan, termasuk saat master beralih ke v2.
     * Versi BARU wajib versi terbit yang sedang aktif dan bukan soal diarsipkan.
     * Selalu validasi ulang di server; jangan percaya ID pilihan dari browser.
     */
    private function selectedVersions(TryoutPackage $package, array $versionIds): array
    {
        $ids=array_map('intval',$versionIds);
        if (count($ids)>100 || count($ids)!==count(array_unique($ids))) {
            throw ValidationException::withMessages(['version_ids'=>'Maksimal 100 soal dan tidak boleh ada soal/versi ganda.']);
        }
        $allowedExisting=$package->exists ? $package->items()->pluck('question_version_id')->map(fn($id)=>(int)$id)->all() : [];
        $questions=[];
        foreach ($ids as $versionId) {
            $version=QuestionVersion::query()->with('question')->find($versionId);
            if (!$version || $version->review_status!=='published' || $version->published_at===null || !$version->question) {
                throw ValidationException::withMessages(['version_ids'=>'Semua soal pilihan harus merupakan versi yang sudah terbit.']);
            }
            $question=$version->question;
            if (isset($questions[$question->id])) {
                throw ValidationException::withMessages(['version_ids'=>'Satu soal tidak boleh dipakai lebih dari satu versi dalam satu tryout.']);
            }
            $questions[$question->id]=true;
            if (!in_array($versionId,$allowedExisting,true)) {
                $current=Question::query()->with('topic.subject')->lockForUpdate()->findOrFail($question->id);
                if ($current->status!=='published' || (int)$current->current_version_id!==$versionId ||
                    $current->topic?->status!=='active' || $current->topic?->subject?->status!=='active') {
                    throw ValidationException::withMessages(['version_ids'=>'Soal baru harus berasal dari versi aktif dan kategori/topik aktif.']);
                }
            }
        }
        return $ids;
    }

    public function create(array $data, User $actor): TryoutPackage
    {
        return DB::transaction(function () use ($data,$actor) {
            $package=TryoutPackage::query()->create([
                'code'=>strtoupper($data['code']),'name'=>trim($data['name']),
                'description'=>trim((string)($data['description']??'')) ?: null,
                'duration_minutes'=>$data['duration_minutes'],'max_attempts'=>$data['max_attempts'],
                'status'=>'draft','created_by'=>$actor->id,
            ]);
            $this->syncItems($package,$data['version_ids']??[]);
            $this->audit($actor,'tryout.create',$package,[],['code'=>$package->code,'items'=>$package->items()->count()]);
            return $package;
        }, 3);
    }

    private function syncItems(TryoutPackage $package, array $ids): void
    {
        $selected=$this->selectedVersions($package,$ids);
        $package->items()->delete();
        if ($selected) {
            $package->items()->createMany(array_map(
                fn(int $id,int $i)=>['question_version_id'=>$id,'sort_order'=>$i+1],
                $selected,array_keys($selected)
            ));
        }
    }

    public function update(TryoutPackage $package, array $data, User $actor): void
    {
        DB::transaction(function () use ($package,$data,$actor) {
            $locked=TryoutPackage::query()->lockForUpdate()->findOrFail($package->id);
            if ($locked->status!=='draft') throw ValidationException::withMessages(['name'=>'Tryout yang sudah terbit tidak dapat diedit.']);
            if ($locked->code!==strtoupper($data['code'])) throw ValidationException::withMessages(['code'=>'Kode tryout tidak boleh diganti.']);
            $before=['name'=>$locked->name,'duration_minutes'=>$locked->duration_minutes,'max_attempts'=>$locked->max_attempts,
                'version_ids'=>$locked->items()->orderBy('sort_order')->pluck('question_version_id')->all()];
            $locked->update(['name'=>trim($data['name']),'description'=>trim((string)($data['description']??'')) ?: null,
                'duration_minutes'=>$data['duration_minutes'],'max_attempts'=>$data['max_attempts']]);
            $this->syncItems($locked,$data['version_ids']??[]);
            $after=['name'=>$locked->name,'duration_minutes'=>$locked->duration_minutes,'max_attempts'=>$locked->max_attempts,
                'version_ids'=>$locked->items()->orderBy('sort_order')->pluck('question_version_id')->all()];
            if ($before!==$after) $this->audit($actor,'tryout.update',$locked,$before,$after);
        },3);
    }

    public function publish(TryoutPackage $package, User $actor): void
    {
        DB::transaction(function () use ($package,$actor) {
            $locked=TryoutPackage::query()->lockForUpdate()->findOrFail($package->id);
            if ($locked->status!=='draft') throw ValidationException::withMessages(['publish'=>'Hanya draft tryout yang dapat diterbitkan.']);
            $items=$locked->items()->with('version.question.topic.subject')->get();
            if ($items->isEmpty()) throw ValidationException::withMessages(['publish'=>'Tambahkan minimal satu soal terbit sebelum publikasi.']);
            foreach ($items as $item) {
                if (!$item->version || $item->version->review_status!=='published' ||
                    !$item->version->question || $item->version->question->status!=='published') {
                    throw ValidationException::withMessages(['publish'=>'Ada soal yang sudah tidak layak digunakan. Periksa daftar soal tryout.']);
                }
            }
            // Freeze taxonomy at publication, in the same transaction as status.
            // Each snapshot belongs to the pinned question version in this template.
            foreach ($items as $item) {
                $topic = $item->version->question->topic;
                $subject = $topic?->subject;
                if (!$subject) {
                    throw ValidationException::withMessages(['publish'=>'Kategori/topik soal tidak ditemukan.']);
                }
                $item->update([
                    'subject_id_snapshot'=>$subject->id,
                    'subject_code_snapshot'=>$subject->code,
                    'subject_name_snapshot'=>$subject->name,
                    'topic_id_snapshot'=>$topic->id,
                    'topic_name_snapshot'=>$topic->name,
                ]);
            }
            $locked->update(['status'=>'published','published_at'=>now()]);
            $this->audit($actor,'tryout.publish',$locked,['status'=>'draft'],[
                'status'=>'published','version_ids'=>$items->pluck('question_version_id')->all(),
                'duration_minutes'=>$locked->duration_minutes,'max_attempts'=>$locked->max_attempts,
            ]);
        },3);
    }

    public function archive(TryoutPackage $package, User $actor, string $reason): void
    {
        DB::transaction(function () use ($package,$actor,$reason) {
            $locked=TryoutPackage::query()->lockForUpdate()->findOrFail($package->id);
            if ($locked->status!=='published') throw ValidationException::withMessages(['archive'=>'Hanya tryout terbit yang dapat diarsipkan.']);
            $locked->update(['status'=>'archived','archived_at'=>now()]);
            $this->audit($actor,'tryout.archive',$locked,['status'=>'published'],['status'=>'archived','reason'=>trim($reason)]);
        },3);
    }
}
