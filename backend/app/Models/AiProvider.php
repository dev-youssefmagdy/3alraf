<?php

namespace App\Models;

use App\Enums\AiDriverType;
use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Central AI vendor + credentials. The key is encrypted at rest, hidden from serialisation and excluded from the audit trail. */
class AiProvider extends Model
{
    use Audited, SoftDeletes;

    protected array $auditExcept = ['api_key', 'settings'];

    protected $fillable = ['driver', 'name', 'api_key', 'base_url', 'is_active', 'settings', 'last_tested_at', 'last_test_ok'];

    protected $hidden = ['api_key', 'settings'];

    protected function casts(): array
    {
        return ['driver' => AiDriverType::class, 'api_key' => 'encrypted', 'settings' => 'encrypted:array', 'is_active' => 'bool', 'last_tested_at' => 'datetime', 'last_test_ok' => 'bool'];
    }

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }

    public function maskedKey(): string
    {
        $k = (string) $this->api_key;

        return $k === '' ? '' : str_repeat('•', 8).substr($k, -4);
    }
}
