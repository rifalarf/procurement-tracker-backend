<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFieldConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_name',
        'label',
        'is_active',
        'is_searchable',
        'is_filterable',
        'display_order',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_searchable' => 'boolean',
        'is_filterable' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Get the user who last updated this config
     */
    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get only active custom fields
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('display_order');
    }

    /**
     * Scope to get only searchable custom fields
     */
    public function scopeSearchable($query)
    {
        return $query->where('is_active', true)->where('is_searchable', true);
    }

    /**
     * Scope to get only filterable custom fields
     */
    public function scopeFilterable($query)
    {
        return $query->where('is_active', true)->where('is_filterable', true);
    }

    /**
     * Get all active field configurations
     */
    public static function getActiveFields(): array
    {
        return static::active()->get()->toArray();
    }

    /**
     * Get searchable field names
     */
    public static function getSearchableFieldNames(): array
    {
        return static::searchable()->pluck('field_name')->toArray();
    }

    /**
     * Get filterable field names
     */
    public static function getFilterableFieldNames(): array
    {
        return static::filterable()->pluck('field_name')->toArray();
    }
}
