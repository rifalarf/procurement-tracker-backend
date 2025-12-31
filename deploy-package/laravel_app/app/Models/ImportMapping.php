<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_session_id',
        'excel_column',
        'database_field',
        'sample_data',
        'confidence_score',
    ];

    protected $casts = [
        'confidence_score' => 'integer',
    ];

    public function importSession()
    {
        return $this->belongsTo(ImportSession::class);
    }
}
