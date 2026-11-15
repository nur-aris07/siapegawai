@extends('layouts.admin')
@section('title', 'Pratinjau Pengaturan Website')
@section('content')
<div class="admin-v1-heading site-settings-heading"><div><p class="admin-v1-crumb">Operasional / Website</p><h1>Pratinjau perubahan</h1><p>Periksa sebelum menyimpan.</p></div></div>
<section class="admin-v1-panel site-settings-panel" aria-labelledby="site-settings-preview-title">
@if(isset($customPreview))
  <h2 id="site-settings-preview-title">{{ $customPreview['mode'] === 'custom_create' ? 'Pengaturan baru' : 'Edit pengaturan' }}</h2>
  <div class="site-settings-preview-intro">Pengaturan tambahan disimpan sebagai data, tidak otomatis dipakai oleh website.</div>
  <div class="table-responsive site-settings-scroll"><table class="site-settings-table">
    <thead><tr><th scope="col">Pengaturan</th><th scope="col">Sebelum</th><th scope="col">Sesudah</th></tr></thead>
    <tbody><tr><th scope="row">{{ $customLabel }}<small class="site-settings-key-note">{{ $customPreview['key'] }}</small></th>
      <td class="site-settings-value">{{ $customBefore ?? '—' }}</td><td class="site-settings-value">{{ $customPreview['value'] }}</td></tr></tbody>
  </table></div>
  <form class="site-settings-confirm" action="{{ route('admin.site-settings.update') }}" method="POST">
    @csrf @method('PUT')
    <input type="hidden" name="mode" value="{{ $customPreview['mode'] }}">
    <input type="hidden" name="version" value="{{ $customPreview['version'] }}">
    @if($customPreview['mode'] === 'custom_create')
      <input type="hidden" name="custom_label" value="{{ $customLabel }}">
    @else
      <input type="hidden" name="custom_key" value="{{ $customPreview['key'] }}">
    @endif
    <input type="hidden" name="custom_value" value="{{ $customPreview['value'] }}">
    <label class="site-settings-confirm-label"><input type="checkbox" name="confirmed" value="1" required> Saya sudah memeriksa perubahan ini.</label>
    <div class="site-settings-editor-actions"><button type="submit" class="button button-primary">Simpan pengaturan</button><a href="{{ route('admin.site-settings.index') }}" class="button button-outline">Batal</a></div>
  </form>
@else
  <h2 id="site-settings-preview-title">Tampilan publik</h2>
  <div class="site-settings-preview-frame">
    <div class="site-settings-preview-brand"><strong>{{ $proposed['site_name'] }}</strong><p>{{ $proposed['tagline'] }}</p></div>
    <div class="site-settings-preview-content">
      <h3>Kontak &amp; informasi</h3>
      @if($proposed['support_email'])<p>Email bantuan: {{ $proposed['support_email'] }}</p>@endif
      @if($proposed['support_url'])<p>Halaman bantuan: {{ $proposed['support_url'] }}</p>@endif
      <p>{{ $proposed['footer_note'] }}</p>
      @foreach(['social_instagram' => 'Instagram', 'social_youtube' => 'YouTube', 'social_tiktok' => 'TikTok'] as $key => $label)
        @if($proposed[$key])<p>{{ $label }}: {{ $proposed[$key] }}</p>@endif
      @endforeach
    </div>
  </div>
  <h2>Ringkasan perubahan</h2>
  <div class="table-responsive site-settings-scroll"><table class="site-settings-table"><thead><tr><th scope="col">Pengaturan</th><th scope="col">Sebelum</th><th scope="col">Sesudah</th></tr></thead><tbody>
  @php($hasChanges = false)
  @foreach($fields as $key => $meta)
    @if($current[$key] !== $proposed[$key])
      @php($hasChanges = true)
      <tr><th scope="row">{{ $meta['label'] }}</th><td class="site-settings-value">{{ $current[$key] ?: '—' }}</td><td class="site-settings-value">{{ $proposed[$key] ?: '—' }}</td></tr>
    @endif
  @endforeach
  @unless($hasChanges)<tr><td colspan="3">Tidak ada perubahan nilai.</td></tr>@endunless
  </tbody></table></div>
  <form class="site-settings-confirm" method="POST" action="{{ route('admin.site-settings.update') }}">
    @csrf @method('PUT')<input type="hidden" name="version" value="{{ $version }}">
    @foreach($proposed as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
    <label class="site-settings-confirm-label"><input type="checkbox" name="confirmed" value="1" required> Saya sudah memeriksa dan menyetujui perubahan ini.</label>
    <div class="site-settings-editor-actions"><button type="submit" class="button button-primary">Simpan pengaturan</button><a href="{{ route('admin.site-settings.index') }}" class="button button-outline">Batal</a></div>
  </form>
@endif
</section>
@endsection
