<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class MemberProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('member.profile.edit', ['member' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','min:2','max:120'],
            'whatsapp_number' => ['nullable','string','regex:/^\+?[1-9][0-9]{7,14}$/'],
        ]);
        DB::transaction(function () use ($request, $data): void {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->role === 'participant' && $user->status === 'active',403);
            $changes = [];
            foreach (['name','whatsapp_number'] as $field) {
                $new = $data[$field] ?? null;
                if ($user->{$field} !== $new) {
                    $user->{$field} = $new;
                    $changes[] = $field;
                }
            }
            if ($changes !== []) {
                $user->save();
                AuditLog::query()->create([
                    'actor_id'=>$user->id,'action'=>'participant.profile_updated',
                    'subject_type'=>'user','subject_id'=>$user->id,
                    'before_summary'=>['changed_fields'=>$changes],
                    'after_summary'=>['changed_fields'=>$changes],
                    'created_at'=>now(),
                ]);
            }
        },3);
        return redirect()->route('member.profile.edit')->with('success','Profil berhasil disimpan.');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password'=>['required','string'],
            'password'=>['required','confirmed','different:current_password',Password::min(12)->letters()->numbers()],
        ]);
        DB::transaction(function () use ($request, $data): void {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->role === 'participant' && $user->status === 'active',403);
            if (!Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password'=>'Kata sandi saat ini tidak sesuai.']);
            }
            $user->password = Hash::make($data['password']);
            $user->save();
            AuditLog::query()->create([
                'actor_id'=>$user->id,'action'=>'participant.password_changed',
                'subject_type'=>'user','subject_id'=>$user->id,
                'before_summary'=>['credential_changed'=>true],
                'after_summary'=>['credential_changed'=>true],
                'created_at'=>now(),
            ]);
        },3);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success','Kata sandi diubah. Silakan login kembali.');
    }
}
