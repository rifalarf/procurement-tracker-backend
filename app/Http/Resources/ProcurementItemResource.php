<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcurementItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'no_pr' => $this->no_pr,
            'version' => $this->version ?? 1,
            'mat_code' => $this->mat_code,
            'nama_barang' => $this->nama_barang,
            'item_category' => $this->item_category,
            'qty' => $this->qty,
            'um' => $this->um,
            'pg' => $this->pg,
            'user_requester' => $this->user_requester,
            'nilai' => $this->nilai,
            'department_id' => $this->department_id,
            'buyer_id' => $this->buyer_id,
            'status_id' => $this->status_id,
            'department' => $this->whenLoaded('department', function () {
                return $this->department ? [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                ] : null;
            }),
            'tgl_terima_dokumen' => $this->tgl_terima_dokumen?->format('Y-m-d'),
            'procx_manual' => $this->procx_manual,
            'buyer' => $this->whenLoaded('buyer', function () {
                return $this->buyer ? [
                    'id' => $this->buyer->id,
                    'name' => $this->buyer->name,
                    'color' => $this->buyer->color,
                    'user_id' => $this->buyer->user_id,
                ] : null;
            }),
            'status' => $this->whenLoaded('status', function () {
                return $this->status ? [
                    'id' => $this->status->id,
                    'name' => $this->status->name,
                    'bg_color' => $this->status->bg_color,
                    'text_color' => $this->status->text_color,
                ] : null;
            }),
            'tgl_status' => $this->tgl_status?->format('Y-m-d'),
            'is_emergency' => $this->is_emergency,
            'no_po' => $this->no_po,
            'nama_vendor' => $this->nama_vendor,
            'tgl_po' => $this->tgl_po?->format('Y-m-d'),
            'tgl_datang' => $this->tgl_datang?->format('Y-m-d'),
            'keterangan' => $this->keterangan,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
