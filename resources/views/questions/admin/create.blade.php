@extends('layouts.admin')
@section('title','Tambah Soal')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb"><a href="{{ route('questions.admin.index') }}">Bank soal</a> / Tambah</p><h1>Tambah soal</h1><p>Siapkan teks dan beberapa gambar sekaligus. Simpan sebagai draft, lalu lakukan review sebelum penerbitan.</p></div></div>
@include('questions.admin.form', ['action'=>route('questions.admin.store'), 'method'=>'POST', 'question'=>null, 'version'=>null])
@endsection
