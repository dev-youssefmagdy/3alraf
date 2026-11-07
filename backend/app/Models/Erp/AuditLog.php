<?php

namespace App\Models\Erp;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{

    public $timestamps = false;

    protected $table = 'erp_audit_logs';

    protected $fillable = ['action', 'subject_type', 'subject_id', 'user_id', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }
}
