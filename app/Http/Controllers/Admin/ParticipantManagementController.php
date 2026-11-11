<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessGrant;
use App\Models\MemberPlan;
use App\Services\MemberPlanService;
use App\Services\QuickQuizQuotaService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use App\Models\AuditLog;
use App\Models\RegistrationRequest;
use App\Models\TryoutAttempt;
use App\Models\User;
use App\Services\ParticipantAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class ParticipantManagementController extends Controller
{
    /** GET /admin/peserta serves HTML and Yajra JSON on the same URL. */
    public function index(Request $request): mixed
    {
        $query = User::query()->where('users.role', 'participant')
            ->select(['users.id', 'users.name', 'users.email', 'users.status', 'users.created_at']);
        $status = (string) $request->query('status', '');
        if (in_array($status, ['pending', 'active', 'suspended', 'rejected'], true)) {
            $query->where('users.status', $status);
        }

        if ($request->ajax()) {
            return DataTables::eloquent($query)
                ->addColumn('detail_url', fn (User $user) => route('admin.participants.show', $user))
                ->filterColumn('name', fn ($q, $term) => $q->where('users.name', 'like', '%'.$term.'%'))
                ->filterColumn('email', fn ($q, $term) => $q->where('users.email', 'like', '%'.$term.'%'))
                ->filterColumn('status', fn ($q, $term) => $q->where('users.status', 'like', '%'.$term.'%'))
                ->orderColumn('name', 'users.name $1')
                ->orderColumn('email', 'users.email $1')
                ->orderColumn('status', 'users.status $1')
                ->orderColumn('created_at', 'users.created_at $1')
                ->escapeColumns(['*'])
                ->toJson();
        }

        return view('admin.participants.index', [
            'totalParticipants' => User::query()->where('role', 'participant')->count(),
            'activeParticipants' => User::query()->where('role', 'participant')->where('status', 'active')->count(),
            'suspendedParticipants' => User::query()->where('role', 'participant')->where('status', 'suspended')->count(),
        ]);
    }

    public function show(User $user): View
    {
        abort_unless($user->role === 'participant', 404);

        return view('admin.participants.show', [
            'participant' => $user,
            'memberPlans' => MemberPlan::query()->orderBy('tier')->get(),
            'currentMemberPlan' => app(MemberPlanService::class)->current($user),
            'qqQuotaToday' => app(QuickQuizQuotaService::class)->remaining($user),
            'registration' => RegistrationRequest::query()->where('user_id', $user->id)->first(),
            'attemptCount' => TryoutAttempt::query()->where('user_id', $user->id)->count(),
            'memberGrant' => AccessGrant::query()->where('user_id', $user->id)->where('grant_type', 'member')
                ->latest('id')->first(),
            'accountAudits' => AuditLog::query()->where('subject_type', 'user')->where('subject_id', $user->id)
                ->whereIn('action', ['participant.suspend', 'participant.restore'])
                ->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    public function changeStatus(Request $request, User $user, ParticipantAccountService $service): RedirectResponse
    {
        abort_unless($request->user()?->role === 'superadmin', 403);
        abort_unless($user->role === 'participant', 404);

        $data = $request->validate([
            'action' => ['required', 'in:suspend,restore'],
            'reason' => ['required', 'string', 'min:12', 'max:500'],
            'confirmed' => ['required', 'accepted'],
        ]);

        $service->changeStatus($user, $request->user(), $data['action'], $data['reason']);
        return redirect()->route('admin.participants.show', $user)
            ->with('success', $data['action'] === 'suspend'
                ? 'Akses peserta ditangguhkan dan tercatat di audit.'
                : 'Akses peserta diaktifkan kembali dan tercatat di audit.');
    }
    /** Package ownership is managed with the participant account, not with Quiz Kilat grants. */
    public function changePlan(Request $request, User $user, MemberPlanService $service): RedirectResponse
    {
        abort_unless($request->user()?->role === 'superadmin' && $request->user()?->status === 'active', 403);
        abort_unless($user->role === 'participant', 404);
        $data = $request->validate([
            'member_plan_id' => ['required', 'integer', Rule::exists('member_plans', 'id')],
            'reason' => ['required', 'string', 'min:12', 'max:500'],
            'ends_at' => ['nullable', 'date'],
            'confirmed' => ['required', 'accepted'],
        ]);
        $plan = MemberPlan::query()->findOrFail($data['member_plan_id']);
        $endsAt = !empty($data['ends_at'])
            ? CarbonImmutable::parse($data['ends_at'], 'Asia/Jakarta')->utc()->toDateTimeString() : null;
        $service->setPlan($request->user(), $user, $plan, $data['reason'], $endsAt);
        return redirect()->route('admin.participants.show', $user)
            ->with('success', 'Paket peserta diperbarui. Kuota hari ini tidak di-reset.');
    }
}
