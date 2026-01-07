<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcurementItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'no_pr' => 'required|string|max:50|unique:procurement_items,no_pr',
            'mat_code' => 'nullable|string|max:50',
            'nama_barang' => 'nullable|string|max:500',
            'item_category' => 'nullable|string|max:100',
            'qty' => 'integer|min:0',
            'um' => 'nullable|string|max:50',
            'pg' => 'nullable|string|max:50',
            'user_requester' => 'nullable|string|max:255',
            'nilai' => 'numeric|min:0',
            'department_id' => 'nullable|exists:departments,id',
            'tgl_terima_dokumen' => 'nullable|date',
            'procx_manual' => 'in:PROCX,MANUAL',
            'buyer_id' => 'nullable|exists:buyers,id',
            'status_id' => 'nullable|exists:statuses,id',
            'tgl_status' => 'nullable|date',
            'emergency' => 'nullable|string|max:50',
            'no_po' => 'nullable|string|max:50',
            'nama_vendor' => 'nullable|string|max:255',
            'tgl_po' => 'nullable|date',
            'tgl_datang' => 'nullable|date',
            'keterangan' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'no_pr.required' => 'Nomor PR wajib diisi.',
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
