<?php

namespace App\DataTables;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\EloquentDataTable;

/** Audit log. Tenant panel: only the current tenant's rows; central panel: everything. */
class ActivityDataTable extends BaseDataTable
{
    public function __construct(private bool $central = false) {}

    public function query(): Builder
    {
        $q = Activity::query()->with('causer')->latest('id');

        return $this->central ? $q : $q->where('tenant_id', current_tenant()?->id);
    }

    public function columns(): array
    {
        return [
            ['data' => 'created', 'title' => __('access.audit_when'), 'name' => 'created_at'],
            ['data' => 'who', 'title' => __('access.audit_who'), 'orderable' => false, 'searchable' => false],
            ['data' => 'log_name', 'title' => __('access.audit_area')],
            ['data' => 'description', 'title' => __('access.audit_event')],
            ['data' => 'subject', 'title' => __('access.audit_subject'), 'orderable' => false, 'searchable' => false],
            ['data' => 'changes', 'title' => __('access.audit_changes'), 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('created', fn (Activity $a) => e($a->created_at?->format('Y-m-d H:i:s')))
            ->addColumn('who', fn (Activity $a) => e($a->causer?->name ?? $a->causer?->email ?? '-'))
            ->addColumn('subject', fn (Activity $a) => e(class_basename((string) $a->subject_type).' #'.$a->subject_id))
            ->addColumn('changes', function (Activity $a) {
                $p = $a->properties;
                $attrs = (array) ($p['attributes'] ?? []);
                $old = (array) ($p['old'] ?? []);
                if (! $attrs) {
                    return e(\Illuminate\Support\Str::limit(json_encode($p->toArray(), JSON_UNESCAPED_UNICODE), 160));
                }
                $out = [];
                foreach ($attrs as $k => $v) {
                    $out[] = e($k).': '.(isset($old[$k]) ? e(is_scalar($old[$k]) ? (string) $old[$k] : json_encode($old[$k], JSON_UNESCAPED_UNICODE)).' &rarr; ' : '').e(is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));
                }

                return implode('<br>', array_slice($out, 0, 6));
            });
    }

    protected function rawColumns(): array
    {
        return ['created', 'who', 'subject', 'changes'];
    }
}
