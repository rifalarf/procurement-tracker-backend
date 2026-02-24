<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProcurementItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data for validation - convert empty strings to null for optional fields
     */
    protected function prepareForValidation(): void
    {
        // Convert empty strings to null for fields that have 'in:' validation
        // This handles both '' (empty string) and null values sent from frontend
        $procxManual = $this->input('procx_manual');
        if ($procxManual === '' || $procxManual === null || $procxManual === 'none') {
            // Use getInputSource to properly remove the field from request data
            // This prevents the 'in:' validation rule from running on this field
            $this->getInputSource()->remove('procx_manual');
        }
    }

    public function rules(): array
    {
        $user = $this->user();
        $itemId = $this->route('procurementItem')->id ?? null;

        // Buyers have limited fields they can update
        if ($user->role === 'buyer') {
            return [
                'buyer_id' => 'nullable|exists:buyers,id',
                'status_id' => 'nullable|exists:statuses,id',
                'tgl_status' => 'nullable|date',
                'emergency' => 'nullable|string|max:50',
                'no_po' => 'nullable|string|max:50',
                'nama_vendor' => 'nullable|string|max:255',
                'tgl_po' => 'nullable|date',
                // Custom fields
                'custom_field_1' => 'nullable|string|max:255',
                'custom_field_2' => 'nullable|string|max:255',
                'custom_field_3' => 'nullable|string|max:255',
                'custom_field_4' => 'nullable|string|max:255',
                'custom_field_5' => 'nullable|string|max:255',
            ];
        }

        // Admins can update all fields
        return [
            'no_pr' => 'sometimes|string|max:50|unique:procurement_items,no_pr,' . $itemId,
            'mat_code' => 'nullable|string|max:50',
            'nama_barang' => 'nullable|string|max:500',
            'item_category' => 'nullable|string|max:100',
            'qty' => 'sometimes|integer|min:0',
            'um' => 'nullable|string|max:50',
            'pg' => 'nullable|string|max:50',
            'user_requester' => 'nullable|string|max:255',
            'nilai' => 'nullable|numeric|min:0',
            'department_id' => 'nullable|exists:departments,id',
            'tgl_terima_dokumen' => 'nullable|date',
            'procx_manual' => ['nullable', 'regex:/^(PROCX|MANUAL)?$/'],
            'buyer_id' => 'nullable|exists:buyers,id',
            'status_id' => 'nullable|exists:statuses,id',
            'tgl_status' => 'nullable|date',
            'emergency' => 'nullable|string|max:50',
            'no_po' => 'nullable|string|max:50',
            'nama_vendor' => 'nullable|string|max:255',
            'tgl_po' => 'nullable|date',
            'tgl_datang' => 'nullable|date',
            'keterangan' => 'nullable|string',
            // Custom fields
            'custom_field_1' => 'nullable|string|max:255',
            'custom_field_2' => 'nullable|string|max:255',
            'custom_field_3' => 'nullable|string|max:255',
            'custom_field_4' => 'nullable|string|max:255',
            'custom_field_5' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'no_pr.unique' => 'Nomor PR sudah digunakan.',
            'no_pr.max' => 'Nomor PR maksimal 50 karakter.',
            'qty.integer' => 'Quantity harus berupa angka.',
            'qty.min' => 'Quantity tidak boleh negatif.',
            'nilai.numeric' => 'Nilai harus berupa angka.',
            'nilai.min' => 'Nilai tidak boleh negatif.',
            'department_id.exists' => 'Bagian tidak ditemukan.',
            'buyer_id.exists' => 'Buyer tidak ditemukan.',
            'status_id.exists' => 'Status tidak ditemukan.',
        ];
    }
}
