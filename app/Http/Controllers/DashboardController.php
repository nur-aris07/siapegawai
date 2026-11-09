<?php

namespace App\Http\Controllers;

use App\Models\RegistrationRequest;
use App\Models\Question;
use App\Models\TryoutPackage;
use App\Models\TryoutAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function participant(Request $request): View
    {
        return view('dashboard.index', ['user' => $request->user(), 'availableTryouts' => TryoutPackage::query()->where('status','published')->count(), 'memberAttempts' => TryoutAttempt::query()->where('user_id',$request->user()->id)->count()]);
    }
    public function admin(Request $request): View
    {
        return view('dashboard.admin', [
            'pendingCount' => RegistrationRequest::query()->where('status', 'pending')->count(),
            'draftQuestions' => Question::query()->where('status', 'draft')->count(),
            'publishedQuestions' => Question::query()->where('status', 'published')->count(),
        ]);
    }
}
