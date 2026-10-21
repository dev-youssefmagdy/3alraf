<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use BelongsToTenant, HasTranslations;

    protected array $translatable = ['name'];

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'bool'];
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
