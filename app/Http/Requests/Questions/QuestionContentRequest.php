<?php
namespace App\Http\Requests\Questions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionContentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() && $this->user()?->status === 'active'; }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    public function rules(): array
    {
        $question = $this->route('question');
        $partial = $this->boolean('draft_shell') || $this->boolean('figural_draft');
        $codeRules = ['required', 'string', 'max:48', 'regex:/^[A-Z0-9][A-Z0-9-]*$/', Rule::unique('questions', 'code')->ignore($question?->id)];
        return [
            'topic_id' => ['required', 'integer', $question && (int) $question->topic_id === (int) $this->input('topic_id') ? Rule::exists('topics', 'id') : Rule::exists('topics', 'id')->where('status','active')],
            'code' => $codeRules,
            'body' => $partial ? ['nullable','string','max:5000'] : ['required','string','min:12','max:5000'],
            'explanation' => $partial ? ['nullable','string','max:5000'] : ['required','string','min:12','max:5000'],
            'option_a' => $partial ? ['nullable','string','max:1200'] : ['required','string','max:1200'],
            'option_b' => $partial ? ['nullable','string','max:1200'] : ['required','string','max:1200'],
            'option_c' => $partial ? ['nullable','string','max:1200'] : ['required','string','max:1200'],
            'option_d' => $partial ? ['nullable','string','max:1200'] : ['required','string','max:1200'],
            'option_e' => ['nullable', 'string', 'max:1200'],
            'correct_option' => [$partial ? 'nullable' : 'required', Rule::in(['A', 'B', 'C', 'D', 'E'])],
            'draft_shell' => ['sometimes','boolean'], 'figural_draft' => ['sometimes','boolean'],
            'edit_token' => ['nullable','string','size:64'],
            'media_files' => ['sometimes','array','max:12'],
            'media_files.*' => ['required','file','mimetypes:image/jpeg,image/png,image/webp','max:5120'],
            'media_slots' => ['sometimes','array','max:12'],
            'media_positions' => ['sometimes','array','max:12'],
            'media_positions.*' => ['required','integer','between:1,8'],
            'media_slots.*' => ['required', Rule::in(['stem','option_A','option_B','option_C','option_D','option_E','explanation'])],
            'media_alts' => ['sometimes','array','max:12'],
            'media_alts.*' => ['nullable','string','max:300'],
            'media_captions' => ['sometimes','array','max:12'],
            'media_captions.*' => ['nullable','string','max:300'],
            'media_existing_ids' => ['sometimes','array','max:56'],
            'media_existing_ids.*' => ['required','integer','min:1'],
            'media_existing_slots' => ['sometimes','array','max:56'],
            'media_existing_positions' => ['sometimes','array','max:56'],
            'media_existing_positions.*' => ['required','integer','between:1,8'],
            'media_existing_slots.*' => ['required', Rule::in(['stem','option_A','option_B','option_C','option_D','option_E','explanation'])],
            'media_existing_alts' => ['sometimes','array','max:56'],
            'media_existing_alts.*' => ['nullable','string','max:300'],
            'media_existing_captions' => ['sometimes','array','max:56'],
            'media_existing_captions.*' => ['nullable','string','max:300'],
            'media_existing_remove' => ['sometimes','array','max:56'],
            'media_existing_remove.*' => ['required', Rule::in(['0','1'])],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $uploads = $this->file('media_files') ?? [];
            if ($uploads !== []) {
                if (!is_array($uploads) || count($uploads) !== count((array) $this->input('media_slots', []))
                    || count($uploads) !== count((array) $this->input('media_positions', []))
                    || count($uploads) !== count((array) $this->input('media_alts', []))
                    || count($uploads) !== count((array) $this->input('media_captions', []))) {
                    $validator->errors()->add('media_files', 'Setiap gambar harus mempunyai bagian soal, teks alternatif, dan metadata berpasangan.');
                }
            }
            $positions=[];
            $newSlots=(array) $this->input('media_slots',[]);
            $newPositions=(array) $this->input('media_positions',[]);
            $oldSlots=(array) $this->input('media_existing_slots',[]);
            $oldPositions=(array) $this->input('media_existing_positions',[]);
            $oldRemoves=(array) $this->input('media_existing_remove',[]);
            foreach ($newSlots as $i=>$slot) {
                $pos=$newPositions[$i]??null;
                if (!is_string($slot) || !is_numeric($pos)) continue;
                $id=$slot.':'.$pos;
                if (isset($positions[$id])) $validator->errors()->add('media_positions','Posisi gambar baru bertabrakan dengan gambar lain.');
                $positions[$id]=true;
            }
            foreach ($oldSlots as $i=>$slot) {
                if (($oldRemoves[$i]??'0')==='1') continue;
                $pos=$oldPositions[$i]??null;
                if (!is_string($slot) || !is_numeric($pos)) continue;
                $id=$slot.':'.$pos;
                if (isset($positions[$id])) $validator->errors()->add('media_existing_positions','Posisi gambar tersimpan bertabrakan dengan gambar lain.');
                $positions[$id]=true;
            }
            $topic = \App\Models\Topic::with('subject')->find($this->input('topic_id'));
            $existing = $this->route('question');
            if ($topic && (!$existing || (int)$existing->topic_id !== (int)$topic->id) && ($topic->status !== 'active' || $topic->subject?->status !== 'active')) {
                $validator->errors()->add('topic_id', 'Pilih topik pada kategori dan materi yang aktif.');
            }
            $draft = $existing?->versions()->orderByDesc('version_number')->first();
            $eHasMedia = $draft && $draft->review_status === 'draft' && $draft->media()->where('slot','option_E')->exists();
            if ($this->input('correct_option') === 'E' && !trim((string) $this->input('option_e')) && !$eHasMedia && !in_array('option_E', (array) $this->input('media_slots',[]),true) && !$this->boolean('draft_shell')) {
                $validator->errors()->add('correct_option', 'Opsi E harus diisi apabila menjadi jawaban benar.');
            }
            if (isset($this->route('question')->current_version_id) && (int) $this->route('question')->topic_id !== (int) $this->input('topic_id')) {
                $validator->errors()->add('topic_id', 'Kategori soal terbit tidak dapat dipindah. Buat soal baru untuk kategori berbeda.');
            }
            if ($this->route('question') && $this->route('question')->code !== $this->input('code')) {
                $validator->errors()->add('code', 'Kode soal tidak dapat diganti setelah dibuat.');
            }
        }];
    }
}
