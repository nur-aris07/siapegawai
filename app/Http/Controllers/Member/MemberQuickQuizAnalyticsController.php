<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\QuickQuizAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class MemberQuickQuizAnalyticsController extends Controller
{
    public function index(Request $request, QuickQuizAnalyticsService $analytics): View
    {
        return view('member.quick-quizzes.analytics', $analytics->forUser((int) $request->user()->id));
    }
}
