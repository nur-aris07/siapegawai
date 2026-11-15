<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\PublicSiteSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/** SITE-SETTINGS-01 C-03: public whitelist and inert custom entries share the existing site_settings table. */
final class SiteSettingsController extends Controller
{
    private const CUSTOM_LIMIT = 100;
    private const CUSTOM_KEY_PATTERN = '/^custom_[a-z][a-z0-9_]{1,59}$/D';
    private const FORBIDDEN_KEY_PARTS = '/(?:password|passwd|secret|token|smtp|oauth|google|legal|privacy|term|registration|entitlement|grant|quota|price|payment|access|role|api_key|credential|private|harga|paket|biaya|tarif|kuota|langganan|pembayaran|bayar|billing|package|plan|subscription|checkout|sandi|rahasia|kredensial)/';

    private function guard(Request $request): void
    {
        abort_unless($request->user()?->role === 'superadmin' && $request->user()?->status === 'active', 403);
        abort_unless(Schema::hasTable('site_settings'), 503, 'Tabel pengaturan website belum tersedia.');
    }

    private function allowedCustomKey(string $key): bool
    {
        return preg_match(self::CUSTOM_KEY_PATTERN, $key) === 1
            && preg_match(self::FORBIDDEN_KEY_PARTS, $key) === 0;
    }

    /** Inert namespaced notes only; never expose other modules' keys or the mutex. */
    private function customSettings(): array
    {
        $raw = DB::table('site_settings')->where('key', 'like', 'custom%')->pluck('value', 'key')->all();
        $out = [];
        foreach ($raw as $key => $value) {
            if (! is_string($key) || ! $this->allowedCustomKey($key) || ! is_string($value)) continue;
            if (! $this->customValueIsSafe($value)) continue;
            $out[$key] = trim($value);
        }
        ksort($out);
        return $out;
    }

    private function customVersion(array $custom): string
    {
        ksort($custom);
        return hash('sha256', json_encode($custom, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function customValueIsSafe(string $value): bool
    {
        return mb_check_encoding($value, 'UTF-8') && mb_strlen($value) <= 500
            && preg_match('/[<>\x00-\x1f\x7f]/u', $value) === 0;
    }

    private function customLabel(string $key): string
    {
        return Str::headline(substr($key, 7));
    }

    private function listedSettings(array $builtIn, array $custom): array
    {
        $rows = [];
        foreach (PublicSiteSettings::FIELDS as $key => $meta) {
            $rows[] = ['label' => $meta['label'], 'key' => $key, 'value' => $builtIn[$key], 'kind' => 'built_in'];
        }
        foreach ($custom as $key => $value) {
            $rows[] = ['label' => $this->customLabel($key), 'key' => $key, 'value' => $value, 'kind' => 'custom'];
        }
        return $rows;
    }

    public function index(Request $request, PublicSiteSettings $settings): mixed
    {
        $this->guard($request);
        $builtIn = $settings->all();
        $custom = $this->customSettings();
        if ($request->ajax()) {
            // Existing Yajra server-side DataTables protocol; data strictly from safe public names.
            $length = filter_var($request->input('length', 10), FILTER_VALIDATE_INT);
            $request->merge(['length' => min(50, max(1, $length === false ? 10 : $length))]);
            return DataTables::collection(collect($this->listedSettings($builtIn, $custom)))->escapeColumns([])->toJson();
        }
        $selectedKey = $request->query('setting');
        if (! is_string($selectedKey) || (! isset(PublicSiteSettings::FIELDS[$selectedKey]) && ! array_key_exists($selectedKey, $custom))) $selectedKey = null;
        return view('admin.site-settings.index', [
            'fields' => PublicSiteSettings::FIELDS, 'values' => $builtIn,
            'version' => $settings->version($builtIn),
            'custom' => $custom, 'customVersion' => $this->customVersion($custom),
            'rows' => $this->listedSettings($builtIn, $custom),
            'selectedKey' => $selectedKey, 'creatingCustom' => $request->query('create') === '1',
        ]);
    }

    private function validated(Request $request, PublicSiteSettings $settings, bool $saving): array
    {
        $allowed = array_merge(array_keys(PublicSiteSettings::FIELDS), ['_token', '_method', 'version', 'confirmed']);
        if (array_diff(array_keys($request->all()), $allowed)) {
            throw ValidationException::withMessages(['settings' => 'Ada field yang tidak diizinkan.']);
        }
        $rules = ['version' => ['required', 'regex:/^[a-f0-9]{64}$/']];
        if ($saving) $rules['confirmed'] = ['required', 'accepted'];
        foreach (PublicSiteSettings::FIELDS as $key => $meta) {
            $rules[$key] = [$meta['type'] === 'text' ? 'required' : 'nullable', 'string', 'max:'.$meta['max']];
        }
        $data = $request->validate($rules);
        $clean = [];
        foreach (PublicSiteSettings::FIELDS as $key => $meta) {
            $value = trim((string) ($data[$key] ?? ''));
            if (! $settings->valid($key, $value)) {
                throw ValidationException::withMessages([$key => 'Nilai tidak valid. Gunakan teks biasa, email sah atau URL HTTPS publik yang sesuai.']);
            }
            $clean[$key] = $value;
        }
        return $clean;
    }

    /** Validate custom namespace, reject secret/feature/entitlement terminology. */
    private function customRequest(Request $request, bool $saving): array
    {
        $allowed = ['_token', '_method', 'mode', 'version', 'custom_key', 'custom_label', 'custom_value', 'confirmed'];
        if (array_diff(array_keys($request->all()), $allowed)) {
            throw ValidationException::withMessages(['settings' => 'Field pengaturan tidak diizinkan.']);
        }
        $rules = [
            'mode' => ['required', 'in:custom_create,custom_edit'],
            'version' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'custom_value' => ['required', 'string', 'max:500'],
        ];
        if ($saving) $rules['confirmed'] = ['required', 'accepted'];
        if ($request->input('mode') === 'custom_create') $rules['custom_label'] = ['required', 'string', 'max:80'];
        else $rules['custom_key'] = ['required', 'string', 'regex:/^custom_[a-z][a-z0-9_]{1,59}$/D'];
        $data = $request->validate($rules);
        $value = trim($data['custom_value']);
        if ($value === '' || ! $this->customValueIsSafe($value)) {
            throw ValidationException::withMessages(['custom_value' => 'Gunakan teks biasa hingga 500 karakter; HTML/kontrol tidak diizinkan.']);
        }
        $mode = $data['mode'];
        if ($mode === 'custom_create') {
            $label = trim($data['custom_label']);
            $slug = Str::slug($label, '_');
            $key = 'custom_'.$slug;
            if (preg_match(self::CUSTOM_KEY_PATTERN, $key) !== 1) {
                throw ValidationException::withMessages(['custom_label' => 'Gunakan nama dengan huruf Latin dan angka, maksimal 60 karakter kunci.']);
            }
        } else $key = $data['custom_key'];
        if (! $this->allowedCustomKey($key)) {
            throw ValidationException::withMessages(['settings' => 'Pengaturan sensitif atau hak akses tidak boleh dibuat di sini.']);
        }
        return ['mode' => $mode, 'key' => $key, 'value' => $value, 'version' => $data['version']];
    }

    private function previewCustom(Request $request): View
    {
        $input = $this->customRequest($request, false);
        $custom = $this->customSettings();
        abort_unless(hash_equals($this->customVersion($custom), $input['version']), 409, 'Pengaturan berubah. Muat ulang halaman.');
        $exists = array_key_exists($input['key'], $custom);
        if ($input['mode'] === 'custom_create' && $exists) {
            throw ValidationException::withMessages(['custom_label' => 'Nama pengaturan sudah digunakan.']);
        }
        if ($input['mode'] === 'custom_edit' && ! $exists) abort(409, 'Pengaturan tidak ditemukan. Muat ulang halaman.');
        return view('admin.site-settings.preview', [
            'customPreview' => $input, 'customLabel' => $this->customLabel($input['key']),
            'customBefore' => $custom[$input['key']] ?? null,
        ]);
    }

    public function preview(Request $request, PublicSiteSettings $settings): View
    {
        $this->guard($request);
        if ($request->has('mode')) return $this->previewCustom($request);
        $proposed = $this->validated($request, $settings, false);
        $current = $settings->all();
        if (! hash_equals($settings->version($current), (string) $request->input('version'))) {
            abort(409, 'Pengaturan berubah. Muat ulang halaman sebelum membuat pratinjau.');
        }
        return view('admin.site-settings.preview', [
            'fields' => PublicSiteSettings::FIELDS, 'current' => $current,
            'proposed' => $proposed, 'version' => $settings->version($current),
        ]);
    }

    private function saveCustom(Request $request): RedirectResponse
    {
        $input = $this->customRequest($request, true);
        $actor = (int) $request->user()->id;
        $changed = DB::transaction(function () use ($input, $actor) {
            $this->lockSettings();
            $custom = $this->customSettings();
            abort_unless(hash_equals($this->customVersion($custom), $input['version']), 409, 'Pengaturan berubah. Muat ulang halaman.');
            $exists = array_key_exists($input['key'], $custom);
            if ($input['mode'] === 'custom_create' && $exists) abort(409, 'Pengaturan sudah ada.');
            if ($input['mode'] === 'custom_edit' && ! $exists) abort(409, 'Pengaturan tidak ditemukan.');
            if (! $exists && count($custom) >= self::CUSTOM_LIMIT) abort(422, 'Batas pengaturan tambahan telah tercapai.');
            // No silent overwrite of any existing (possibly unrelated or malformed) DB key.
            if (! $exists && DB::table('site_settings')->where('key', $input['key'])->exists()) abort(409, 'Kunci pengaturan telah digunakan.');
            $before = $custom[$input['key']] ?? null;
            if ($before === $input['value']) return false;
            DB::table('site_settings')->updateOrInsert(['key' => $input['key']], [
                'value' => $input['value'], 'updated_by' => $actor, 'updated_at' => now(),
            ]);
            AuditLog::query()->create([
                'actor_id' => $actor, 'action' => $exists ? 'site_settings.custom_updated' : 'site_settings.custom_created',
                'subject_type' => 'site_setting', 'subject_id' => 0,
                'before_summary' => ['key' => $input['key'], 'value' => $before],
                'after_summary' => ['key' => $input['key'], 'value' => $input['value']],
                'created_at' => now(),
            ]);
            $this->advanceMutex();
            return true;
        }, 3);
        return redirect()->route('admin.site-settings.index')->with('success', $changed
            ? 'Pengaturan tambahan tersimpan; belum otomatis ditampilkan di website.'
            : 'Tidak ada perubahan nilai.');
    }

    private function lockSettings(): void
    {
        DB::table('site_settings')->insertOrIgnore([
            'key' => '__public_site_settings_mutex', 'value' => '0', 'updated_by' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        abort_unless(DB::table('site_settings')->where('key', '__public_site_settings_mutex')->lockForUpdate()->first(), 500, 'Gagal mengunci pengaturan.');
    }

    private function advanceMutex(): void
    {
        DB::table('site_settings')->where('key', '__public_site_settings_mutex')->update([
            'value' => (string) Str::uuid(), 'updated_at' => now(),
        ]);
    }

    public function update(Request $request, PublicSiteSettings $settings): RedirectResponse
    {
        $this->guard($request);
        if ($request->has('mode')) return $this->saveCustom($request);
        $proposed = $this->validated($request, $settings, true);
        $expected = (string) $request->input('version');
        $actor = (int) $request->user()->id;
        $changed = DB::transaction(function () use ($settings, $proposed, $expected, $actor) {
            $this->lockSettings();
            $raw = DB::table('site_settings')->whereIn('key', array_keys(PublicSiteSettings::FIELDS))->pluck('value', 'key')->all();
            $before = $settings->normalize($raw);
            abort_unless(hash_equals($settings->version($before), $expected), 409, 'Pengaturan diubah oleh sesi lain. Muat ulang.');
            $changes = [];
            foreach (PublicSiteSettings::FIELDS as $key => $meta) {
                $next = $proposed[$key];
                if ($before[$key] === $next) continue;
                DB::table('site_settings')->updateOrInsert(['key' => $key], [
                    'value' => $next, 'updated_by' => $actor, 'updated_at' => now(),
                ]);
                AuditLog::query()->create([
                    'actor_id' => $actor, 'action' => 'site_settings.updated',
                    'subject_type' => 'site_setting', 'subject_id' => 0,
                    'before_summary' => ['key' => $key, 'value' => $before[$key]],
                    'after_summary' => ['key' => $key, 'value' => $next],
                    'created_at' => now(),
                ]);
                $changes[] = $key;
            }
            if ($changes) $this->advanceMutex();
            return $changes;
        }, 3);
        $settings->clearCache();
        return redirect()->route('admin.site-settings.index')->with('success', $changed
            ? 'Pengaturan website tersimpan dan ditampilkan pada halaman publik.'
            : 'Tidak ada perubahan pengaturan website.');
    }
}
