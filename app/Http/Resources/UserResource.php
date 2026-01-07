<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'departments' => $this->whenLoaded('departments', function () {
                return $this->departments->map(fn ($dept) => [
                    'id' => $dept->id,
                    'name' => $dept->name,
                ]);
            }),
            'buyer' => $this->whenLoaded('buyer', function () {
                return $this->buyer ? [
                    'id' => $this->buyer->id,
                    'color' => $this->buyer->color,
                    'text_color' => $this->buyer->text_color,
                ] : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
