<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColumnConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_name',
        'label',
        'display_order',
        'width',
        'is_visible_in_table',
        'is_visible_in_detail',
        'updated_by',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_visible_in_table' => 'boolean',
        'is_visible_in_detail' => 'boolean',
    ];

    /**
     * Width size mapping
     */
    public const WIDTH_SIZES = [
        'sm' => '80px',
        'md' => '120px',
        'lg' => '180px',
        'xl' => '250px',
        'auto' => 'auto',
    ];

    /**
     * Custom field names
     */
    public const CUSTOM_FIELDS = [
        'custom_field_1',
        'custom_field_2',
        'custom_field_3',
        'custom_field_4',
        'custom_field_5',
    ];

    /**
     * Get the user who last updated this config
     */
    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get columns visible in table
     */
    public function scopeVisibleInTable($query)
    {
        return $query->where('is_visible_in_table', true)->orderBy('display_order');
    }

    /**
     * Scope to get columns visible in detail
     */
    public function scopeVisibleInDetail($query)
    {
        return $query->where('is_visible_in_detail', true)->orderBy('display_order');
    }

    /**
     * Scope to order by display_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    /**
     * Check if this is a custom field
     */
    public function isCustomField(): bool
    {
        return in_array($this->field_name, self::CUSTOM_FIELDS);
    }

    /**
     * Get pixel width from size string
     */
    public function getPixelWidth(): string
    {
        return self::WIDTH_SIZES[$this->width] ?? $this->width;
    }

    /**
     * Get all column configs ordered by display_order
     */
    public static function getAllOrdered(): \Illuminate\Database\Eloquent\Collection
    {
        return static::ordered()->get();
    }

    /**
     * Get table visible columns ordered
     */
    public static function getTableColumns(): \Illuminate\Database\Eloquent\Collection
    {
        return static::visibleInTable()->get();
    }

    /**
     * Get detail visible columns ordered
     */
    public static function getDetailColumns(): \Illuminate\Database\Eloquent\Collection
    {
        return static::visibleInDetail()->get();
    }
}
