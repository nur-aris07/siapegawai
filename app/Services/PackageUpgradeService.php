<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MemberPlan;
use App\Models\PackageUpgradeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Manual external settlement. Creating or viewing a request never changes membership. */
class PackageUpgradeService
{
    public function __construct(private readonly MemberPlanService $plans, private readonly WhatsAppLinkService $whatsApp) {}

    public function request(User $participant, MemberPlan $target): PackageUpgradeRequest
    {
        return DB::transaction(function () use ($participant, $target): PackageUpgradeRequest {
            $participant = User::query()->lockForUpdate()->findOrFail($participant->id);
            abort_unless($participant->role === 'participant' && $participant->status === 'active', 403);
            $target = MemberPlan::query()->findOrFail($target->id);
            if ($target->is_archived || $target->availability !== 'requestable' || !$target->published_version_id) {
                throw ValidationException::withMessages(['package' => 'Paket belum menerima pengajuan.']);
            }
            $current = $this->plans->current($participant)['plan'];
            if ($current->id === $target->id) {
                throw ValidationException::withMessages(['package' => 'Paket ini sudah digunakan.']);
            }
            // Exactly one open request per user; duplicate submission returns the same reference.
            $pending = PackageUpgradeRequest::query()->where('user_id', $participant->id)
                ->where('status', 'pending')->lockForUpdate()->first();
            if ($pending) {
                if ($pending->target_member_plan_id === $target->id) return $pending;
                throw ValidationException::withMessages(['package' => 'Selesaikan pengajuan sebelumnya sebelum memilih paket lain.']);
            }
            $request = PackageUpgradeRequest::query()->create([
                'reference' => 'SGP-'.strtoupper(Str::random(16)),
                'user_id' => $participant->id, 'from_member_plan_id' => $current->id,
                'target_member_plan_id' => $target->id,
                'target_published_version_id' => $target->published_version_id,
                'status' => 'pending',
            ]);
            AuditLog::query()->create(['actor_id' => $participant->id, 'action' => 'package.request_created',
                'subject_type' => PackageUpgradeRequest::class, 'subject_id' => $request->id,
                'before_summary' => [], 'after_summary' => ['reference' => $request->reference, 'target' => $target->code],
                'created_at' => now()]);
            return $request;
        }, 3);
    }

    public function whatsappUrl(PackageUpgradeRequest $request): ?string
    {
        $number = $this->whatsApp->officialNumber();
        if (!$number) return null;
        $message = 'Halo admin Siapegawai, mohon tindak lanjut pengajuan perubahan paket dengan referensi '.$request->reference.'.';
        return 'https://wa.me/'.$number.'?'.http_build_query(['text' => $message], '', '&', PHP_QUERY_RFC3986);
    }

    public function decide(User $actor, PackageUpgradeRequest $request, string $decision, string $reason, bool $manualVerified = false): void
    {
        abort_unless($actor->role === 'superadmin' && $actor->status === 'active', 403);
        if ($decision === 'completed' && !$manualVerified) {
            throw ValidationException::withMessages(['manual_verified' => 'Konfirmasi verifikasi manual wajib diberikan.']);
        }
        DB::transaction(function () use ($actor, $request, $decision, $reason): void {
            $request = PackageUpgradeRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['request' => 'Pengajuan sudah diproses.']);
            }
            $participant = User::query()->lockForUpdate()->findOrFail($request->user_id);
            $current = $this->plans->current($participant)['plan'];
            if ($decision === 'completed') {
                if ($current->id !== $request->from_member_plan_id) {
                    throw ValidationException::withMessages(['request' => 'Paket peserta berubah setelah pengajuan; tinjau kembali sebelum memproses.']);
                }
                // Existing service enforces superadmin/active participant, and keeps QQ usage + CAT history.
                $target = MemberPlan::query()->findOrFail($request->target_member_plan_id);
                $this->plans->setPlan($actor, $participant, $target, $reason);
            }
            $request->update(['status' => $decision, 'admin_reason' => $reason,
                'processed_by' => $actor->id, 'processed_at' => now()]);
            AuditLog::query()->create(['actor_id' => $actor->id, 'action' => 'package.request_'.$decision,
                'subject_type' => PackageUpgradeRequest::class, 'subject_id' => $request->id,
                'before_summary' => ['status' => 'pending'],
                'after_summary' => ['status' => $decision, 'reference' => $request->reference, 'reason' => $reason],
                'created_at' => now()]);
        }, 3);
    }
}
