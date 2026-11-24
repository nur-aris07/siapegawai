@extends('layouts.account')
@section('title','Draft kebijakan pilot')
@section('content')
<section class="account-panel account-panel-wide"><h1>Dokumen pilot — draft lokal</h1>
@foreach(explode("\n",app(\App\Services\PilotAccessService::class)->document($document)) as $line)
@if(trim($line)!=='')<p>{{ $line }}</p>@endif
@endforeach
</section>
@endsection
