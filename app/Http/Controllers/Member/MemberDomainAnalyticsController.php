<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\MemberDomainAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MemberDomainAnalyticsController extends Controller
{
    public function index(Request $request, MemberDomainAnalyticsService $service): Response
    {
        return response()->view('member.analytics.index', $service->forUser((int) $request->user()->id))
            ->header('Cache-Control', 'private, no-store');
    }
}
