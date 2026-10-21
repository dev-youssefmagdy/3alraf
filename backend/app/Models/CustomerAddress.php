<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    use BelongsToTenant;

    protected $fillable = ['customer_id', 'label', 'name', 'phone', 'city_id', 'address', 'notes', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'bool'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class)->withTrashed();
    }
}
