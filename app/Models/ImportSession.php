<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_filename',
        'file_size',
        'total_rows',
        'processed_rows',
        'success_rows',
        'error_rows',
        'status',
        'created_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'success_rows' => 'integer',
        'error_rows' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mappings()
    {
        return $this->hasMany(ImportMapping::class);
    }
}
