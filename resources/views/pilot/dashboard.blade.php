@extends('layouts.workspace')
@section('title','Pilot Quick Quiz')
@section('content')
<h1>Uji coba Quick Quiz</h1><p>Program terbatas, gratis, dan tidak berafiliasi dengan penyelenggara seleksi.</p>
<p><a href="{{ route('member.quick-quizzes.index') }}">Quiz Kilat</a> · <a href="{{ route('member.quick-quizzes.history') }}">Riwayat</a></p>
@include('pilot.faq')
<form method="POST" action="{{ route('pilot.research') }}">@csrf
<input type="hidden" name="binding" value="{{ app(\App\Services\PilotAccessService::class)->binding() }}">
<input type="hidden" name="research_binding" value="{{ app(\App\Services\PilotAccessService::class)->researchBinding() }}">
<p>Status riset: {{ app(\App\Services\PilotAccessService::class)->researchAllowed(auth()->user()) ? 'Bersedia' : 'Tidak bersedia' }}.</p>
<details><summary>Pemberitahuan riset</summary>@foreach(explode("\n",app(\App\Services\PilotAccessService::class)->document('research')) as $line) @if(trim($line)!=='')<p>{{ $line }}</p>@endif @endforeach</details>
<button name="opt_in" value="1">Bersedia mengikuti riset</button>
<button name="opt_in" value="0">Tarik persetujuan riset</button>
</form>
@endsection
