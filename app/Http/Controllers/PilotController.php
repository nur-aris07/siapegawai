<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\PilotAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
final class PilotController extends Controller
{
    public function revoke(Request $r,User $user,PilotAccessService $p) {
        abort_unless($p->enabled() && $r->user()?->role==='superadmin' && $r->user()->status==='active',403);
        $data=$r->validate(['reason'=>'required|string|max:500']); $p->revoke($user,$r->user(),$data['reason']); return back()->with('success','Akses baru dicabut.');
    }
    public function research(Request $r,PilotAccessService $p) {
        abort_unless($p->enabled(),404); $data=$r->validate(['opt_in'=>'required|boolean','binding'=>'required|string','research_binding'=>'required|string']);
        DB::transaction(function()use($r,$p,$data){ $u=User::query()->lockForUpdate()->findOrFail($r->user()->id); abort_unless($p->eligible($u),403); $p->validateConsent($data['binding']); $p->validateResearchBinding($data['research_binding']); $p->research($u,(bool)$data['opt_in']); },3);
        return back()->with('success','Pilihan riset disimpan.');
    }
}
