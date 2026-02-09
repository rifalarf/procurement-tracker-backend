<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'procurement_item_id',
        'old_status_id',
        'new_status_id',
        'changed_by',
        'changed_at',
        'notes',
        'event_type',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function procurementItem()
    {
        return $this->belongsTo(ProcurementItem::class);
    }

    public function oldStatus()
    {
        return $this->belongsTo(Status::class, 'old_status_id');
    }

    public function newStatus()
    {
        return $this->belongsTo(Status::class, 'new_status_id');
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
