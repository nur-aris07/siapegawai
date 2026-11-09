<?php

namespace App\Http\Controllers;

use App\Services\SampleQuestionBank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index(Request $request, SampleQuestionBank $bank): View
    {
        $category = $request->query('kategori', 'Semua');
        if (! is_string($category)) $category = 'Semua';
        $queryText = $request->query('cari', '');
        $search = is_string($queryText) ? trim(mb_substr($queryText, 0, 100)) : '';
        $questions = array_values(array_filter($bank->all(), fn (array $q) =>
            ($category === 'Semua' || $q['cat'] === $category) &&
            ($search === '' || mb_stripos($q['q'].' '.$q['why'], $search) !== false)));
        return view('questions.index', compact('questions', 'category', 'search'));
    }
}
