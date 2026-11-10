@extends('layouts.workspace')
@section('title','Mengerjakan '.$attempt->practice->title)
@section('content')
<div class="member-exam-heading"><div><a class="task-back" href="{{ route('member.class-practices.index',$class) }}">← Latihan Kelas</a><h1>{{ $attempt->practice->title }}</h1><p>Percobaan {{ $attempt->attempt_number }} · <span id="member-answered-label">{{ $answers->filter(fn($a)=>$a->answer_label!==null)->count() }}</span> dari {{ $items->count() }} dijawab</p></div><div class="timer"><strong id="member-countdown" data-expires="{{ $attempt->expires_at->timestamp }}" data-server-now="{{ now()->timestamp }}">--:--</strong></div></div>
<section class="member-exam" id="member-exam" data-save-url="{{ route('member.class-practices.save',[$class,$attempt]) }}" data-result-url="{{ route('member.class-practices.result',[$class,$attempt]) }}">
<div class="member-exam-pane"><div class="member-exam-toolbar"><span id="member-topic">Soal 1 dari {{ $items->count() }}</span><span id="member-save-status" role="status" aria-live="polite">Jawaban tersimpan</span><button class="member-retry-save" id="member-retry-save" type="button" hidden>Coba simpan lagi</button></div>
@foreach($items as $n=>$item)
@php($saved=$answers->get($item->id))
<section class="member-question" data-member-question="{{ $n }}" data-item-id="{{ $item->id }}" @if($n!==0) hidden @endif>
<div class="member-question-meta">{{ $item->subject_code_snapshot ?? 'MATERI' }} · Soal {{ $n+1 }} dari {{ $items->count() }}</div>
<h2 tabindex="-1">{{ $item->version?->body }}</h2>
<div class="member-options" role="radiogroup" aria-label="Pilihan jawaban soal {{ $n+1 }}">
@foreach($item->version?->options ?? [] as $choice)
<label class="member-option"><input type="radio" name="answer_{{ $item->id }}" value="{{ $choice->label }}" @checked($saved?->answer_label===$choice->label)><span class="member-option-letter">{{ $choice->label }}</span><span>{{ $choice->body }}</span></label>
@endforeach
</div></section>
@endforeach
<div class="member-exam-actions"><div class="member-action-group"><button class="button button-outline" type="button" id="member-prev">← Sebelumnya</button><button class="button button-primary" type="button" id="member-next">Berikutnya →</button></div><label class="member-flag"><input type="checkbox" id="member-flag"> Tandai untuk ditinjau</label></div></div>
<aside class="member-exam-aside"><h2>Nomor soal</h2><p><span id="member-answered-side">{{ $answers->filter(fn($a)=>$a->answer_label!==null)->count() }}</span> dari {{ $items->count() }} dijawab</p><div class="member-number-grid">@foreach($items as $n=>$item)<button type="button" data-member-nav="{{ $n }}" data-flagged="{{ $answers->get($item->id)?->flagged ? '1' : '0' }}" aria-label="Soal {{ $n+1 }}">{{ $n+1 }}</button>@endforeach</div><p class="member-aside-tip">Jawaban tersimpan otomatis selama koneksi tersedia.</p>
<form id="member-submit-form" method="POST" action="{{ route('member.class-practices.submit',[$class,$attempt]) }}">@csrf<button type="submit" class="button button-primary member-submit">Kumpulkan jawaban →</button></form></aside>
</section><p class="member-disclaimer">Latihan kelas memakai durasi seluruh sesi. Kunci dan pembahasan tidak ditampilkan sebelum sesi selesai.</p>
@endsection
