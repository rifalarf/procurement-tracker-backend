<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FieldPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'role',
        'field_name',
        'can_view',
        'can_edit',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_edit' => 'boolean',
    ];

    /**
     * Get all editable fields for a specific role
     */
    public static function getEditableFields(string $role): array
    {
        return Cache::remember("field_permissions.editable.{$role}", 3600, function () use ($role) {
            return self::where('role', $role)
                ->where('can_edit', true)
                ->pluck('field_name')
                ->toArray();
        });
    }

    /**
     * Get all viewable fields for a specific role
     */
    public static function getViewableFields(string $role): array
    {
        return Cache::remember("field_permissions.viewable.{$role}", 3600, function () use ($role) {
            return self::where('role', $role)
                ->where('can_view', true)
                ->pluck('field_name')
                ->toArray();
        });
    }

    /**
     * Check if a role can edit a specific field
     */
    public static function canEdit(string $role, string $fieldName): bool
    {
        $editableFields = self::getEditableFields($role);
        return in_array($fieldName, $editableFields);
    }

    /**
     * Clear permissions cache for a role
     */
    public static function clearCache(?string $role = null): void
    {
        if ($role) {
            Cache::forget("field_permissions.editable.{$role}");
            Cache::forget("field_permissions.viewable.{$role}");
        } else {
            foreach (['admin', 'buyer', 'avp', 'staff'] as $r) {
                Cache::forget("field_permissions.editable.{$r}");
                Cache::forget("field_permissions.viewable.{$r}");
            }
        }
    }
}
