<?php
namespace App\Http\Middleware;
use App\Services\PilotAccessService;
use Closure;
use Illuminate\Http\Request;
final class EnsurePilotParticipantScope
{
    public function handle(Request $r, Closure $next)
    {
        $p=app(PilotAccessService::class); if (!$p->enabled()) return $next($r);
        abort_unless($p->coherent(),503);
        $name=$r->route()?->getName(); $u=$r->user();
        // This global web middleware executes before route-specific EnsureActiveAccount.
        // Re-read the account before authorization, including after invitation activation.
        if ($u) {
            $current = $u->fresh();
            abort_unless($current,403);
            $u->setRawAttributes($current->getAttributes(),true);
            $u->setRelations([]);
        }
        if (in_array($name,['register','register.store','google.redirect','google.callback','google.onboarding','google.onboarding.store','google.link','registration.whatsapp','registration.decide','admin.participants.plan','admin.classes.enroll','admin.classes.revoke'],true)) abort(404);
        if (!$u || $u->role!=='participant') return $next($r);
        if (in_array($name,['logout','page.privasi','page.syarat-layanan'],true)) return $next($r);
        if (in_array($name,['registration.status','participant.invitation.accept'],true)) { abort_unless($p->grant($u)&&in_array($u->status,['pending','active'],true),403); return $next($r); }
        $allowed=['dashboard','member.profile.edit','member.profile.update','member.profile.password','pilot.research','member.quick-quizzes.index','member.quick-quizzes.show','member.quick-quizzes.start','member.quick-quizzes.take','member.quick-quizzes.answer','member.quick-quizzes.next','member.quick-quizzes.result','member.quick-quizzes.history','member.quick-quizzes.media'];
        abort_unless(in_array($name,$allowed,true),404);
        if (!$p->eligible($u)) {
            if ($name==='member.quick-quizzes.media') abort(404);
            if ($u->status==='active' && $p->grant($u) && $r->isMethod('GET') && !$r->expectsJson()) return redirect()->route('registration.status');
            abort(403);
        }
        return $next($r);
    }
}
