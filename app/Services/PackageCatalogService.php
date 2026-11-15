<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MemberPlan;
use App\Models\MemberPlanVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Versioned editorial content; no entitlement, payment, or package-grant side effects. */
class PackageCatalogService
{
    public function manager(User $actor): void
    {
        abort_unless($actor->role === 'superadmin' && $actor->status === 'active', 403);
    }

    public function fingerprint(MemberPlanVersion $version): string
    {
        return hash('sha256', json_encode([$version->id, $version->status, $version->title,
            $version->summary, $version->benefits, $version->updated_at?->toISOString()], JSON_THROW_ON_ERROR));
    }

    /** Benefits are editorial copy, never interpreted as access controls. */
    public function benefitLines(?string $lines): array
    {
        $benefits = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $lines))));
        if (count($benefits) > 12) throw ValidationException::withMessages(['benefits' => 'Maksimal 12 manfaat.']);
        foreach ($benefits as $line) {
            if (mb_strlen($line) > 180 || preg_match('/[<>]/', $line)) {
                throw ValidationException::withMessages(['benefits' => 'Setiap manfaat maksimal 180 karakter tanpa HTML.']);
            }
        }
        return $benefits;
    }

    private function guardCapabilityClaims(MemberPlan $plan, string $summary, array $benefits): void
    {
        $copy = $summary.' '.implode(' ', $benefits);
        // No catalog-only access promises for CAT or private classes.
        if (preg_match('/\b(CAT|kelas|enrollment)\b/ui', $copy)) {
            throw ValidationException::withMessages(['benefits' => 'Manfaat CAT/Kelas belum dapat dipublikasikan sebagai privilege paket otomatis.']);
        }
        if (preg_match('/\b(quiz\s+kilat|kuota\s+qq)\b/ui', $copy)) {
            $policy = $plan->quizQuotaPolicy;
            $now = now();
            if (!$policy || $policy->scope !== 'plan' || $policy->status !== 'active'
                || ($policy->starts_at && $policy->starts_at->isFuture())
                || ($policy->ends_at && $policy->ends_at->lessThanOrEqualTo($now))
                || (!$policy->unlimited && $policy->daily_limit === null)) {
                throw ValidationException::withMessages(['benefits' => 'Klaim Quiz Kilat membutuhkan kebijakan kuota paket yang aktif dan terkonfigurasi.']);
            }
        }
    }

    private function audit(User $actor, string $action, MemberPlan $plan, array $before, array $after): void
    {
        AuditLog::query()->create(['actor_id' => $actor->id, 'action' => $action,
            'subject_type' => MemberPlan::class, 'subject_id' => $plan->id,
            'before_summary' => $before, 'after_summary' => $after, 'created_at' => now()]);
    }

    public function create(User $actor, array $data): MemberPlan
    {
        $this->manager($actor);
        return DB::transaction(function () use ($actor, $data): MemberPlan {
            $code = strtoupper($data['code']);
            if (MemberPlan::query()->where('code', $code)->exists()) {
                throw ValidationException::withMessages(['code' => 'Kode paket sudah digunakan.']);
            }
            // Creation only appends to the end. Rank is never entered in the editor.
            // Lock the current rows to serialize concurrent catalog mutations.
            $last = MemberPlan::query()->orderByDesc('tier')->lockForUpdate()->first();
            $nextTier = ($last?->tier ?? 0) + 1;
            if ($nextTier > 255) {
                throw ValidationException::withMessages(['package' => 'Batas urutan penyimpanan tercapai.']);
            }
            $plan = MemberPlan::query()->create([
                'code' => $code, 'name' => $data['name'], 'is_paid' => (bool) $data['is_paid'],
                'tier' => $nextTier, 'availability' => 'planned', 'is_archived' => false,
            ]);
            $benefits = $this->benefitLines($data['benefits'] ?? '');
            $this->guardCapabilityClaims($plan, $data['summary'], $benefits);
            MemberPlanVersion::query()->create(['member_plan_id' => $plan->id, 'version_no' => 1,
                'title' => $data['name'], 'summary' => $data['summary'],
                'benefits' => $benefits, 'status' => 'draft', 'created_by' => $actor->id]);
            $this->audit($actor, 'package.create', $plan, [], ['code' => $code, 'availability' => 'planned']);
            return $plan;
        }, 3);
    }

    public function startRevision(User $actor, MemberPlan $plan): MemberPlanVersion
    {
        $this->manager($actor);
        return DB::transaction(function () use ($actor, $plan): MemberPlanVersion {
            $plan = MemberPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($plan->is_archived) throw ValidationException::withMessages(['package' => 'Paket diarsipkan.']);
            if ($plan->versions()->where('status', 'draft')->exists()) {
                throw ValidationException::withMessages(['package' => 'Masih ada draft; edit atau batalkan draft tersebut.']);
            }
            $published = $plan->publishedVersion;
            if (!$published) throw ValidationException::withMessages(['package' => 'Draft awal belum diterbitkan.']);
            $next = (int) $plan->versions()->max('version_no') + 1;
            $draft = $plan->versions()->create(['version_no' => $next, 'title' => $published->title,
                'summary' => $published->summary, 'benefits' => $published->benefits,
                'status' => 'draft', 'created_by' => $actor->id]);
            $this->audit($actor, 'package.revision_start', $plan, ['published' => $published->version_no], ['draft' => $next]);
            return $draft;
        }, 3);
    }

    public function updateDraft(User $actor, MemberPlan $plan, array $data): MemberPlanVersion
    {
        $this->manager($actor);
        return DB::transaction(function () use ($actor, $plan, $data): MemberPlanVersion {
            $plan = MemberPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $draft = $plan->versions()->where('status', 'draft')->lockForUpdate()->firstOrFail();
            if (!hash_equals($this->fingerprint($draft), $data['fingerprint'])) {
                throw ValidationException::withMessages(['fingerprint' => 'Draft berubah pada sesi lain. Muat ulang dahulu.']);
            }
            $before = ['version' => $draft->version_no, 'title' => $draft->title];
            $benefits = $this->benefitLines($data['benefits'] ?? '');
            $this->guardCapabilityClaims($plan, $data['summary'], $benefits);
            $draft->update(['title' => $data['title'], 'summary' => $data['summary'], 'benefits' => $benefits]);
            $this->audit($actor, 'package.draft_update', $plan, $before, ['version' => $draft->version_no, 'title' => $draft->title]);
            return $draft;
        }, 3);
    }

    public function publish(User $actor, MemberPlan $plan, string $fingerprint): void
    {
        $this->manager($actor);
        DB::transaction(function () use ($actor, $plan, $fingerprint): void {
            $plan = MemberPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($plan->is_archived) throw ValidationException::withMessages(['package' => 'Paket diarsipkan.']);
            $draft = $plan->versions()->where('status', 'draft')->lockForUpdate()->firstOrFail();
            if (!hash_equals($this->fingerprint($draft), $fingerprint)) {
                throw ValidationException::withMessages(['fingerprint' => 'Draft berubah. Pratinjau ulang sebelum terbit.']);
            }
            $this->guardCapabilityClaims($plan, $draft->summary, $draft->benefits ?? []);
            $old = $plan->published_version_id;
            $draft->update(['status' => 'published', 'published_at' => now()]);
            if ($old) $plan->versions()->whereKey($old)->update(['status' => 'superseded']);
            $plan->update(['published_version_id' => $draft->id]);
            $this->audit($actor, 'package.publish', $plan, ['published_version_id' => $old], ['published_version_id' => $draft->id, 'version' => $draft->version_no]);
        }, 3);
    }

    public function discard(User $actor, MemberPlan $plan): void
    {
        $this->manager($actor);
        DB::transaction(function () use ($actor, $plan): void {
            $plan = MemberPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $draft = $plan->versions()->where('status', 'draft')->lockForUpdate()->firstOrFail();
            $draft->update(['status' => 'discarded']);
            $this->audit($actor, 'package.discard', $plan, ['draft' => $draft->version_no], ['discarded' => $draft->version_no]);
        }, 3);
    }

    public function availability(User $actor, MemberPlan $plan, string $availability): void
    {
        $this->manager($actor);
        DB::transaction(function () use ($actor, $plan, $availability): void {
            $plan = MemberPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($availability === 'requestable' && (!$plan->published_version_id || $plan->is_archived)) {
                throw ValidationException::withMessages(['availability' => 'Terbitkan informasi dan pulihkan paket sebelum membuka pengajuan.']);
            }
            $before = ['availability' => $plan->availability, 'archived' => $plan->is_archived];
            $plan->update(['availability' => $availability, 'is_archived' => $availability === 'archived']);
            $this->audit($actor, 'package.availability', $plan, $before, ['availability' => $availability, 'archived' => $availability === 'archived']);
        }, 3);
    }

    /** Stable ordering fingerprint for stale-form detection. */
    public function orderFingerprint(): string
    {
        $rows = MemberPlan::query()->orderBy('tier')->orderBy('id')->get(['id', 'tier']);
        return hash('sha256', json_encode($rows->map(fn (MemberPlan $plan) =>
            [(int) $plan->id, (int) $plan->tier])->all(), JSON_THROW_ON_ERROR));
    }

    /** Persist the complete ordering atomically, never a page/search subset. */
    public function reorder(User $actor, array $ids, string $fingerprint): bool
    {
        $this->manager($actor);
        return DB::transaction(function () use ($actor, $ids, $fingerprint): bool {
            $current = MemberPlan::query()->orderBy('tier')->orderBy('id')->lockForUpdate()->get();
            $before = $current->map(fn (MemberPlan $plan) => [(int) $plan->id, (int) $plan->tier])->all();
            $actualFingerprint = hash('sha256', json_encode($before, JSON_THROW_ON_ERROR));
            if (!hash_equals($actualFingerprint, $fingerprint)) {
                throw ValidationException::withMessages(['order' => 'Urutan telah berubah. Muat ulang sebelum menyimpan.']);
            }
            $currentIds = $current->pluck('id')->map(fn ($id) => (int) $id)->all();
            $requested = array_map(fn ($id) => (int) $id, $ids);
            if (count($requested) !== count($currentIds) || count(array_unique($requested)) !== count($requested)
                || array_diff($requested, $currentIds) || array_diff($currentIds, $requested)) {
                throw ValidationException::withMessages(['order' => 'Daftar urutan tidak lengkap atau tidak valid. Muat ulang.']);
            }
            if (count($requested) > 255) {
                throw ValidationException::withMessages(['order' => 'Batas urutan penyimpanan tercapai.']);
            }
            // Avoid changes, timestamps and audit entries for an identical order.
            if ($requested === $currentIds) return false;
            $ranks = array_flip($requested);
            foreach ($current as $plan) {
                $rank = $ranks[(int) $plan->id] + 1;
                if ($plan->tier !== $rank) $plan->update(['tier' => $rank]);
            }
            $this->audit($actor, 'package.reorder', $current->firstOrFail(),
                ['codes' => $current->pluck('code')->all()],
                ['codes' => MemberPlan::query()->orderBy('tier')->orderBy('id')->pluck('code')->all()]);
            return true;
        }, 3);
    }
}
