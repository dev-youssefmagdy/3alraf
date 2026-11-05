<?php

namespace App\Models\Erp;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'erp_audit_logs';

    protected $fillable = ['action', 'subject_type', 'subject_id', 'user_id', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }
}
