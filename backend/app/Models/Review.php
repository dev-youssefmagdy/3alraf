<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{

    protected $fillable = ['product_id', 'customer_id', 'order_id', 'rating', 'body', 'status'];

    protected function casts(): array
    {
        return ['status' => ReviewStatus::class, 'rating' => 'int'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
