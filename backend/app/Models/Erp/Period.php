<?php

namespace App\Models\Erp;

use Illuminate\Database\Eloquent\Model;

class Period extends Model
{

    protected $table = 'erp_periods';

    protected $fillable = ['fiscal_year_id', 'name', 'starts_on', 'ends_on', 'status', 'closed_at', 'closed_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'closed_at' => 'datetime'];
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }
}
