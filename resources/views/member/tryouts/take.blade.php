@extends('layouts.workspace')
@section('title','Mengerjakan '.$attempt->package->name)
@section('content')
<div class="member-exam-heading"><div><a class="task-back" href="{{ route('member.tryouts.index') }}">← Daftar tryout</a><h1>{{ $attempt->package->name }}</h1><p>Percobaan {{ $attempt->attempt_number }} · <span id="member-answered-label">{{ $answers->filter(fn($a)=>$a->answer_label!==null)->count() }}</span> dari {{ $items->count() }} dijawab · Jawaban tersimpan di MySQL</p></div><div class="timer"><span aria-hidden="true">◷</span> <strong id="member-countdown" data-expires="{{ $attempt->expires_at->timestamp }}" data-server-now="{{ now()->timestamp }}">--:--</strong></div></div>
<section class="member-exam" id="member-exam" data-save-url="{{ route('member.tryouts.save',$attempt) }}" data-result-url="{{ route('member.tryouts.result',$attempt) }}">
<div class="member-exam-pane"><div class="member-exam-toolbar"><span id="member-topic">Soal 1 dari {{ $items->count() }}</span><span id="member-save-status" role="status" aria-live="polite">Jawaban tersimpan</span><button class="member-retry-save" id="member-retry-save" type="button" hidden>Coba simpan lagi</button></div>
@foreach($items as $n=>$item)
@php($saved=$answers->get($item->id))
<section class="member-question" data-member-question="{{ $n }}" data-item-id="{{ $item->id }}" @if($n!==0) hidden @endif>
<div class="member-question-meta">{{ $item->subject_code_snapshot ?? $item->version?->question?->topic?->subject?->code ?? 'MATERI' }} · Soal {{ $n+1 }} dari {{ $items->count() }}</div>
<h2 tabindex="-1">{{ $item->version?->body }}</h2>
<div class="member-options" role="radiogroup" aria-label="Pilihan jawaban soal {{ $n+1 }}">
@foreach($item->version?->options ?? [] as $choice)
<label class="member-option"><input type="radio" name="answer_{{ $item->id }}" value="{{ $choice->label }}" @checked($saved?->answer_label===$choice->label)><span class="member-option-letter">{{ $choice->label }}</span><span>{{ $choice->body }}</span></label>
@endforeach
</div>
</section>
@endforeach
<div class="member-exam-actions"><div class="member-action-group"><button class="button button-outline" type="button" id="member-prev">← Sebelumnya</button><button class="button button-primary" type="button" id="member-next">Berikutnya →</button></div><label class="member-flag"><input type="checkbox" id="member-flag" aria-label="Tandai untuk ditinjau"> Tandai untuk ditinjau</label></div></div>
<aside class="member-exam-aside"><h2>Nomor soal</h2><p><span id="member-answered-side">{{ $answers->filter(fn($a)=>$a->answer_label!==null)->count() }}</span> dari {{ $items->count() }} dijawab</p><div class="member-number-grid">@foreach($items as $n=>$item)<button type="button" data-member-nav="{{ $n }}" data-flagged="{{ $answers->get($item->id)?->flagged ? '1' : '0' }}" aria-label="Soal {{ $n+1 }}">{{ $n+1 }}</button>@endforeach</div><p class="member-aside-tip">Tanda * berarti perlu ditinjau. Jawaban disimpan otomatis saat dipilih.</p>
<form id="member-submit-form" method="POST" action="{{ route('member.tryouts.submit',$attempt) }}">@csrf<button class="button button-primary member-submit" type="submit">Kumpulkan jawaban →</button></form></aside>
</section>
<p class="member-disclaimer">Timer ditetapkan oleh server. Menutup browser tidak menghentikan waktu, tetapi kamu bisa melanjutkan sesi yang masih aktif.</p>
@endsection
