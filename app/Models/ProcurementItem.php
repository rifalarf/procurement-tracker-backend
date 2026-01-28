<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'no_pr',
        'mat_code',
        'nama_barang',
        'item_category',
        'qty',
        'um',
        'pg',
        'user_requester',
        'nilai',
        'department_id',
        'tgl_terima_dokumen',
        'procx_manual',
        'buyer_id',
        'status_id',
        'tgl_status',
        'is_emergency',
        'no_po',
        'nama_vendor',
        'tgl_po',
        'tgl_datang',
        'keterangan',
        'custom_field_1',
        'custom_field_2',
        'custom_field_3',
        'custom_field_4',
        'custom_field_5',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qty' => 'integer',
        'nilai' => 'decimal:2',
        'tgl_terima_dokumen' => 'date',
        'tgl_status' => 'date',
        'tgl_po' => 'date',
        'tgl_datang' => 'date',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function buyer()
    {
        return $this->belongsTo(Buyer::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}
