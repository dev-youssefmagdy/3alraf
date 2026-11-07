<?php

namespace App\Models\Erp;

use Illuminate\Database\Eloquent\Model;

class ErpSetting extends Model
{

    protected $table = 'erp_settings';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
