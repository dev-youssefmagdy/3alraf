<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{

    protected $fillable = ['expense_category_id', 'branch_id', 'amount', 'is_paid', 'paid_from', 'supplier_id', 'expense_date', 'note', 'attachment', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_paid' => 'bool', 'expense_date' => 'date'];
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment ? private_url($this->attachment) : null;
    }
}
