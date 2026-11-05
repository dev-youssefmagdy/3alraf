<?php

namespace App\Models\Erp;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    use BelongsToTenant;

    protected $table = 'erp_fiscal_years';

    protected $fillable = ['name', 'starts_on', 'ends_on', 'status'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function periods()
    {
        return $this->hasMany(Period::class, 'fiscal_year_id')->orderBy('starts_on');
    }
}
