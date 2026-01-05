<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buyer_id' => 'nullable|exists:buyers,id',
        ];
    }

    public function messages(): array
    {
        return [
            'buyer_id.exists' => 'Buyer tidak ditemukan.',
        ];
    }
}
