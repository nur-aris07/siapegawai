<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassEnrollmentService
{
    public function grant(LearningClass $class, User $participant, User $actor, string $reason): void
    {
        abort_unless($actor->role === 'superadmin' && $actor->status === 'active',403);
        DB::transaction(function () use ($class,$participant,$actor,$reason): void {
            $lockedClass = LearningClass::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();
            $target = User::query()->whereKey($participant->id)->lockForUpdate()->firstOrFail();
            if ($lockedClass->status !== 'open' || $target->role !== 'participant' || $target->status !== 'active') {
                throw ValidationException::withMessages(['user_id'=>'Kelas harus dibuka dan peserta harus aktif.']);
            }
            $enrollment = ClassEnrollment::query()->where('class_id',$lockedClass->id)->where('user_id',$target->id)->lockForUpdate()->first();
            if ($enrollment?->status === 'active') {
                throw ValidationException::withMessages(['user_id'=>'Peserta sudah mengikuti kelas ini.']);
            }
            if ($lockedClass->capacity !== null && ClassEnrollment::query()->where('class_id',$lockedClass->id)->where('status','active')->count() >= $lockedClass->capacity) {
                throw ValidationException::withMessages(['user_id'=>'Kapasitas kelas sudah penuh.']);
            }
            if (!$enrollment) $enrollment = new ClassEnrollment(['class_id'=>$lockedClass->id,'user_id'=>$target->id]);
            $before = $enrollment->exists ? $enrollment->status : 'none';
            $enrollment->fill(['status'=>'active','approved_by'=>$actor->id,'approved_at'=>now(),'revoke_reason'=>null]);
            $enrollment->save();
            $this->audit($actor, $enrollment, 'class.enrollment_granted', $before,'active', $reason);
        },3);
    }

    public function revoke(LearningClass $class, User $participant, User $actor, string $reason): void
    {
        abort_unless($actor->role === 'superadmin' && $actor->status === 'active',403);
        DB::transaction(function () use ($class,$participant,$actor,$reason): void {
            $lockedClass = LearningClass::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();
            $enrollment = ClassEnrollment::query()->where('class_id',$lockedClass->id)->where('user_id',$participant->id)->lockForUpdate()->first();
            if (!$enrollment || $enrollment->status !== 'active') {
                throw ValidationException::withMessages(['reason'=>'Enrollment aktif tidak ditemukan.']);
            }
            $enrollment->status='revoked';
            $enrollment->revoke_reason=$reason;
            $enrollment->save();
            $this->audit($actor,$enrollment,'class.enrollment_revoked','active','revoked',$reason);
        },3);
    }

    private function audit(User $actor, ClassEnrollment $enrollment, string $action,string $from,string $to,string $reason): void
    {
        AuditLog::query()->create([
            'actor_id'=>$actor->id,'action'=>$action,
            'subject_type'=>'enrollment','subject_id'=>$enrollment->id,
            'before_summary'=>['status'=>$from,'class_id'=>$enrollment->class_id,'user_id'=>$enrollment->user_id],
            'after_summary'=>['status'=>$to,'class_id'=>$enrollment->class_id,'user_id'=>$enrollment->user_id,'reason'=>$reason],
            'created_at'=>now(),
        ]);
    }
}
