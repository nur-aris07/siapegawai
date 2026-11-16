<form method="POST" action="{{ $action }}" class="admin-v1-tryout-editor sg-editor-layout" data-tryout-editor data-search-url="{{ route('tryouts.admin.search') }}">
  @csrf @if($method==='PUT') @method('PUT') @endif
  <section class="admin-v1-panel admin-v1-form-panel"><h2>Identitas dan aturan</h2>
    <div class="admin-v1-editor-row">
      <label class="admin-v1-field">Kode tryout<input name="code" maxlength="48" pattern="[A-Z][A-Z0-9-]*" value="{{ old('code',$package->code??'') }}" required @if(isset($package)) readonly @endif></label>
      <label class="admin-v1-field">Nama tryout<input name="name" maxlength="180" value="{{ old('name',$package->name??'') }}" required></label>
    </div>
    <label class="admin-v1-field">Deskripsi<textarea name="description" maxlength="2500" rows="3">{{ old('description',$package->description??'') }}</textarea></label>
    <div class="admin-v1-editor-row">
      <label class="admin-v1-field">Durasi (menit, 5–360)<input type="number" name="duration_minutes" min="5" max="360" value="{{ old('duration_minutes',$package->duration_minutes??'90') }}" required></label>
      <label class="admin-v1-field">Maksimum percobaan (1–20)<input type="number" name="max_attempts" min="1" max="20" value="{{ old('max_attempts',$package->max_attempts??'1') }}" required></label>
    </div>
  <div class="sg-editor-desktop-actions"><div class="admin-v1-editor-actions"><button type="submit" class="button button-primary">Simpan draft tryout</button><a href="{{ route('tryouts.admin.index') }}" class="button button-outline">Kembali ke daftar</a></div></div>
</section>
  @include('admin.bulk-assignment.composer',[
    'composerCreate'=>!isset($package), 'composerMax'=>100, 'composerDomain'=>'cat',
    'composerSearchUrl'=>route('tryouts.admin.search')
  ])
  <div class="admin-v1-editor-actions"><button type="submit" class="button button-primary">Simpan draft tryout</button><a href="{{ route('tryouts.admin.index') }}" class="button button-outline">Kembali ke daftar</a></div>
</form>
