<?php

namespace App\Http\Controllers;

use App\Http\Requests\Registration\StoreRegistrationRequest;
use App\Models\ConsentRecord;
use App\Models\RegistrationRequest;
use App\Models\User;
use App\Services\RegistrationActivationService;
use App\Services\WhatsAppLinkService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class RegistrationController extends Controller
{
    private function isRegistrationOpen(WhatsAppLinkService $wa): bool
    {
        return config('siapegawai.registration_enabled') && config('siapegawai.privacy_approved')
            && config('siapegawai.terms_approved') && config('siapegawai.privacy_version') !== 'DRAFT'
            && $wa->officialNumber() !== null;
    }

    public function create(WhatsAppLinkService $wa): View
    {
        return view('registrations.create', ['registrationOpen' => $this->isRegistrationOpen($wa)]);
    }

    public function store(StoreRegistrationRequest $request, WhatsAppLinkService $wa): RedirectResponse
    {
        abort_unless($this->isRegistrationOpen($wa), 503, 'Registrasi belum diaktifkan pengelola.');
        $input = $request->validated();
        $user = DB::transaction(function () use ($input) {
            $user = new User();
            $user->name = $input['name'];
            $user->email = $input['email'];
            $number = preg_replace('/\D/', '', $input['whatsapp_number']);
            $user->whatsapp_number = str_starts_with($number, '0') ? '62'.substr($number, 1) : $number;
            $user->password = $input['password']; // hashed through User cast
            $user->role = 'participant';
            $user->status = 'pending';
            $user->save();
            RegistrationRequest::query()->create([
                'user_id' => $user->id,
                'reference_code' => 'SGP-'.strtoupper(bin2hex(random_bytes(6))),
                'status' => 'pending', 'submitted_at' => now(),
            ]);
            ConsentRecord::query()->create([
                'user_id' => $user->id, 'policy_version' => config('siapegawai.privacy_version'),
                'agreed_at' => now(), 'source' => 'web_registration',
            ]);
            return $user;
        }, 3);
        Auth::login($user);
        $request->session()->regenerate();
        $response = redirect()->route('registration.whatsapp');
        return $response->setStatusCode(303);
    }

    public function whatsapp(Request $request, WhatsAppLinkService $wa): View
    {
        abort_unless($request->user()?->role === 'participant', 403);
        $registration = $request->user()->registrationRequest;
        abort_unless($registration, 404);
        return view('registrations.whatsapp', [
            'registration' => $registration,
            'whatsappUrl' => $wa->registrationUrl($registration->reference_code),
            'autoRedirect' => true,
        ]);
    }

    public function status(Request $request, WhatsAppLinkService $wa): View
    {
        abort_unless($request->user()?->role === 'participant', 403);
        $registration = $request->user()->registrationRequest;
        abort_unless($registration, 404);
        return view('registrations.status', [
            'registration' => $registration,
            'whatsappUrl' => $wa->registrationUrl($registration->reference_code),
        ]);
    }

    // Same URL: GET /admin/registrasi -> Blade; AJAX -> Yajra JSON. No /data endpoint.
    public function index(Request $request): mixed
    {
        $query = RegistrationRequest::query()
            ->join('users', 'users.id', '=', 'registration_requests.user_id')
            ->select([
                'registration_requests.id', 'registration_requests.user_id',
                'registration_requests.reference_code', 'registration_requests.status',
                'registration_requests.submitted_at',
                'users.name as applicant_name', 'users.email as email',
            ]);
        if ($request->ajax()) {
            return DataTables::eloquent($query)
                ->addColumn('detail_url', fn (RegistrationRequest $r) => route('registration.show', $r->user_id))
                ->filterColumn('reference_code', fn ($q, $term) => $q->where('registration_requests.reference_code', 'like', '%'.$term.'%'))
                ->filterColumn('applicant_name', fn ($q, $term) => $q->where('users.name', 'like', '%'.$term.'%'))
                ->filterColumn('email', fn ($q, $term) => $q->where('users.email', 'like', '%'.$term.'%'))
                ->filterColumn('status', fn ($q, $term) => $q->where('registration_requests.status', 'like', '%'.$term.'%'))
                ->orderColumn('reference_code', 'registration_requests.reference_code $1')
                ->orderColumn('status', 'registration_requests.status $1')
                ->orderColumn('submitted_at', 'registration_requests.submitted_at $1')
                ->escapeColumns(['*'])
                ->toJson();
        }
        return view('registrations.index');
    }

    public function show(User $user): View
    {
        abort_unless($user->role === 'participant', 404);
        $registration = $user->registrationRequest;
        abort_unless($registration, 404);
        return view('registrations.show', compact('user', 'registration'));
    }

    public function decide(Request $request, User $user, RegistrationActivationService $service): RedirectResponse
    {
        abort_unless($user->role === 'participant', 404);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'admin_note' => ['required', 'string', 'min:10', 'max:1000'],
            'confirmed_external' => ['required', 'accepted'],
            'external_reference' => ['nullable', 'string', 'max:120'],
        ]);
        $service->decide($user, $request->user(), $data['decision'], $data['admin_note'], $data['external_reference'] ?? null);
        return redirect()->route('registration.show', $user)->with('success', 'Keputusan registrasi tersimpan dengan audit.');
    }
}
