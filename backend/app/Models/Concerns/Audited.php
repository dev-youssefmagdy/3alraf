<?php

namespace App\Models\Concerns;

use App\Support\Mask;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Audit trail (spatie/activitylog) for money-relevant models: only changed attributes, secrets excluded, tenant stamped,
 * causer = whoever is signed in on any of our guards (tenant admin, central admin, affiliate, customer).
 *
 * Models can list extra private attributes in `$auditExcept`, and PII they still want evidence of (masked, e.g. j***@x.com) in `$auditMasked`.
 */
trait Audited
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName(strtolower(class_basename($this)))->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()
            ->logExcept(array_merge(['updated_at', 'password', 'remember_token', 'credentials'], $this->auditExcept ?? []));
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        /** @var Model $this */
        if (! empty($this->auditMasked)) {
            $props = $activity->properties->toArray();
            foreach (['attributes', 'old'] as $bag) {
                foreach ($this->auditMasked as $k) {
                    if (isset($props[$bag][$k])) {
                        $props[$bag][$k] = Mask::value((string) $props[$bag][$k]);
                    }
                }
            }
            $activity->properties = collect($props);
        }
        $activity->tenant_id = $this->tenant_id ?? current_tenant()?->id;
        foreach (['tenant', 'central', 'affiliate', 'web'] as $guard) {
            if (auth($guard)->check()) {
                $activity->causer()->associate(auth($guard)->user());
                break;
            }
        }
    }
}
