<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ] : null;
            }),
            'procurement_item_id' => $this->procurement_item_id,
            'procurement_item' => $this->whenLoaded('procurementItem', function () {
                return $this->procurementItem ? [
                    'id' => $this->procurementItem->id,
                    'no_pr' => $this->procurementItem->no_pr,
                    'nama_barang' => $this->procurementItem->nama_barang,
                    'user_requester' => $this->procurementItem->user_requester,
                ] : null;
            }),
            'event_type' => $this->event_type,
            'description' => $this->description,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
        ];
    }
}
