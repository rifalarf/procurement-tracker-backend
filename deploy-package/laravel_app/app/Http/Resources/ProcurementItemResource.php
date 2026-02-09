<?php

namespace App\Http\Resources;

use App\Models\FieldPermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcurementItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canEdit = $this->determineCanEdit($user);
        $editableFields = $canEdit ? $this->getEditableFields($user) : [];
        $viewableFields = $this->getViewableFields($user);

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
                    'text_color' => $this->buyer->text_color,
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

            // Custom fields
            'custom_field_1' => $this->custom_field_1,
            'custom_field_2' => $this->custom_field_2,
            'custom_field_3' => $this->custom_field_3,
            'custom_field_4' => $this->custom_field_4,
            'custom_field_5' => $this->custom_field_5,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Permission fields
            'can_edit' => $canEdit,
            'editable_fields' => $editableFields,
            'viewable_fields' => $viewableFields,
        ];
    }

    /**
     * Determine if the current user can edit this item
     */
    private function determineCanEdit($user): bool
    {
        if (!$user) {
            return false;
        }

        // Admin and Staff can edit all items
        if (in_array($user->role, ['admin', 'staff'])) {
            return true;
        }

        // AVP can edit items from their assigned departments
        if ($user->role === 'avp') {
            $departmentIds = $user->departments()->pluck('departments.id')->toArray();
            return in_array($this->department_id, $departmentIds);
        }

        // Buyer can edit:
        // 1. Items assigned to them (via buyer relationship)
        // 2. Unassigned items from their departments
        if ($user->role === 'buyer') {
            // Check if assigned to this buyer
            if ($this->buyer && $this->buyer->user_id === $user->id) {
                return true;
            }

            // Check if unassigned and from buyer's department
            if ($this->buyer_id === null) {
                $departmentIds = $user->departments()->pluck('departments.id')->toArray();
                return in_array($this->department_id, $departmentIds);
            }

            return false;
        }

        return false;
    }

    /**
     * Get editable fields based on user's role
     */
    private function getEditableFields($user): array
    {
        if (!$user) {
            return [];
        }

        return FieldPermission::getEditableFields($user->role);
    }

    /**
     * Get viewable fields based on user's role
     */
    private function getViewableFields($user): array
    {
        if (!$user) {
            return [];
        }

        return FieldPermission::getViewableFields($user->role);
    }
}
