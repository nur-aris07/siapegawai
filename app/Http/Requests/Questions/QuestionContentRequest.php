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
        $codeRules = ['required', 'string', 'max:48', 'regex:/^[A-Z0-9][A-Z0-9-]*$/', Rule::unique('questions', 'code')->ignore($question?->id)];
        return [
            'topic_id' => ['required', 'integer', $question && (int) $question->topic_id === (int) $this->input('topic_id') ? Rule::exists('topics', 'id') : Rule::exists('topics', 'id')->where('status','active')],
            'code' => $codeRules,
            'body' => ['required', 'string', 'min:12', 'max:5000'],
            'explanation' => ['required', 'string', 'min:12', 'max:5000'],
            'option_a' => ['required', 'string', 'max:1200'],
            'option_b' => ['required', 'string', 'max:1200'],
            'option_c' => ['required', 'string', 'max:1200'],
            'option_d' => ['required', 'string', 'max:1200'],
            'option_e' => ['nullable', 'string', 'max:1200'],
            'correct_option' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $topic = \App\Models\Topic::with('subject')->find($this->input('topic_id'));
            $existing = $this->route('question');
            if ($topic && (!$existing || (int)$existing->topic_id !== (int)$topic->id) && ($topic->status !== 'active' || $topic->subject?->status !== 'active')) {
                $validator->errors()->add('topic_id', 'Pilih topik pada kategori dan materi yang aktif.');
            }
            if ($this->input('correct_option') === 'E' && !trim((string) $this->input('option_e'))) {
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
