<?php

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() === null; }
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'whatsapp_number' => ['required', 'regex:/^(?:\+?62|0)8\d{7,12}$/'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
            'consent' => ['accepted'],
        ];
    }
}
