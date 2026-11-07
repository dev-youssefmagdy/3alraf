<?php

namespace App\Models\Erp;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;

class Owner extends Model
{
    protected $table = 'erp_owners';

    protected $fillable = ['name', 'phone', 'ownership_percent', 'is_eligible', 'admin_id'];

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
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
