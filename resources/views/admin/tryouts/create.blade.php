@extends('layouts.admin')
@section('title','Buat Tryout')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb"><a href="{{ route('tryouts.admin.index') }}">Tryout</a> / Baru</p><h1>Buat template tryout</h1><p>Simpan sebagai draft sebelum melakukan review dan publikasi.</p></div></div>
@include('admin.tryouts.form',['action'=>route('tryouts.admin.store'),'method'=>'POST'])
@endsection
