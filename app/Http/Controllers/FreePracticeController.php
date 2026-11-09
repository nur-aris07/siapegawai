<?php

namespace App\Http\Controllers;

use App\Services\SampleQuestionBank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FreePracticeController extends Controller
{
    public function intro(): View { return view('practice.intro'); }

    public function take(Request $request, SampleQuestionBank $bank): View
    {
        $start = (int) $request->session()->get('demo_started_at', 0);
        if (! $start || $request->boolean('ulang')) {
            $start = now()->timestamp;
            $request->session()->put('demo_started_at', $start);
            $request->session()->forget('demo_result');
        }
        return view('practice.take', ['questions' => $bank->exam(), 'expiresAt' => $start + 720]);
    }

    public function submit(Request $request, SampleQuestionBank $bank): RedirectResponse
    {
        $start = (int) $request->session()->get('demo_started_at', 0);
        abort_if(! $start, 419, 'Sesi demo tidak ditemukan. Mulai ulang latihan.');
        $answers = $request->input('answers', []);
        if (! is_array($answers)) $answers = [];
        $expired = now()->timestamp > $start + 720;
        if ($expired) $answers = []; // Server enforces the public-demo time limit.
        $result = [];
        $correct = 0;
        foreach ($bank->exam() as $q) {
            $selected = $answers[$q['id']] ?? null;
            $selected = is_scalar($selected) && preg_match('/^[0-3]$/', (string) $selected) ? (int) $selected : null;
            $isCorrect = $selected !== null && $selected === $q['a'];
            if ($isCorrect) $correct++;
            $result[] = ['question' => $q, 'selected' => $selected, 'correct' => $isCorrect];
        }
        $request->session()->put('demo_result', [
            'rows' => $result, 'correct' => $correct,
            'expired' => $expired,
        ]);
        $request->session()->forget('demo_started_at');
        return redirect()->route('practice.result');
    }

    public function result(Request $request): View|RedirectResponse
    {
        $result = $request->session()->get('demo_result');
        if (! $result) return redirect()->route('practice.intro');
        return view('practice.result', compact('result'));
    }
}
