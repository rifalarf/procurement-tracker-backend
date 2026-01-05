<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_id' => 'nullable|exists:statuses,id',
        ];
    }

    public function messages(): array
    {
        return [
            'status_id.exists' => 'Status tidak ditemukan.',
        ];
    }
}
