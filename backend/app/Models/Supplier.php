<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['name', 'contact_name', 'phone', 'email', 'address', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'bool'];
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
