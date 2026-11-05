<?php

namespace App\Models\Erp;

use App\Models\Concerns\BelongsToTenant;
use App\Models\TenantAdmin;
use Illuminate\Database\Eloquent\Model;

class Owner extends Model
{
    use BelongsToTenant;

    protected $table = 'erp_owners';

    protected $fillable = ['name', 'phone', 'ownership_percent', 'is_eligible', 'tenant_admin_id'];

    protected function casts(): array
    {
        return ['ownership_percent' => 'decimal:4', 'is_eligible' => 'bool'];
    }

    public function transactions()
    {
        return $this->hasMany(OwnerTransaction::class, 'owner_id');
    }

    public function user()
    {
        return $this->belongsTo(TenantAdmin::class, 'tenant_admin_id');
    }
}
