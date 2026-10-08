<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'flag_threshold_pct' => ['nullable', 'numeric', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a CSV file to upload.',
            'file.mimes' => 'The file must be a CSV. In Excel, use File › Save As › CSV UTF-8.',
            'file.max' => 'The file must be smaller than 5 MB.',
        ];
    }

    public function threshold(): float
    {
        return (float) ($this->validated('flag_threshold_pct') ?? config('prices.flag_threshold_pct'));
    }
}
