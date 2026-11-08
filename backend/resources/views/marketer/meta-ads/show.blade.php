@php
    $t = fn ($k, $r = []) => __('marketing.meta.'.$k, $r);
    $f = fn ($k) => __('marketing.meta.fields.'.$k);
    $S = \App\Enums\Marketing\MetaDraftStatus::class;
    $st = $d->status;
    $me = auth('admin')->user();
    $aud = (array) $d->audience; $loc = (array) $d->locations; $pl = (array) $d->placements; $cr = (array) $d->creative; $cp = (array) $d->copy;
    $editable = $st->editable() && ! $d->hasMetaObjects();
    $canPublish = $me->can('meta_ads.publish') && $approval && (in_array($st, [$S::APPROVED, $S::FAILED], true) || \App\Services\Marketing\Meta\MetaPublisher::interrupted($d)) && $hashOk && $status['configured'] && $status['page_configured'];
    $row = fn ($label, $value) => '<dt class="text-slate-500">'.e($label).'</dt><dd class="min-w-0 break-words">'.($value === null || $value === '' ? '—' : e($value)).'</dd>';
@endphp
<x-layouts.marketer :title="$d->name">
    <div class="space-y-4" data-meta-draft="{{ $d->id }}" data-status="{{ $st->value }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <a href="{{ route('marketer.meta-ads.index') }}" class="text-sm text-brand-600 hover:underline">← {{ $t('btn.back') }}</a>
                <h1 class="mt-1 break-words text-xl font-bold text-slate-800">#{{ $d->id }} {{ $d->name }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    <span class="badge {{ $st->badge() }}" data-status-badge>{{ $st->label() }}</span>
                    <span class="badge badge-muted">{{ $f('version') }} {{ $d->version }}</span>
                    <span class="badge badge-muted">{{ $t('objectives.'.$d->objective) }}</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2" data-actions>
                @if ($editable && $me->can('meta_ads.draft'))
                    <a class="btn btn-secondary" href="{{ route('marketer.meta-ads.edit', $d->id) }}" data-action="edit">{{ $t('btn.edit') }}</a>
                @endif
                @if (in_array($st, [$S::DRAFT, $S::REJECTED], true) && $me->can('meta_ads.draft'))
                    <button type="button" class="btn btn-secondary" data-action="submit" data-post-action="{{ route('marketer.meta-ads.submit', $d->id) }}">{{ $t('btn.submit') }}</button>
                @endif
                @if ($st === $S::PENDING_APPROVAL && $me->can('meta_ads.approve') && $hashOk && ! $check['errors'])
                    <button type="button" class="btn btn-primary" data-action="approve" data-post-action="{{ route('marketer.meta-ads.approve', $d->id) }}" data-confirm="{{ $t('confirm.approve') }}">{{ $t('btn.approve') }}</button>
                @endif
                @if ($canPublish)
                    <button type="button" class="btn btn-primary" data-action="publish" data-post-action="{{ route('marketer.meta-ads.publish', $d->id) }}" data-confirm="{{ $t('confirm.publish') }}">{{ $t('btn.publish') }}</button>
                @endif
                @if (! in_array($st, [$S::PUBLISHING, $S::ARCHIVED], true) && $me->can('meta_ads.draft'))
                    <button type="button" class="btn btn-secondary" data-action="archive" data-post-action="{{ route('marketer.meta-ads.archive', $d->id) }}" data-confirm="{{ $t('confirm.archive') }}">{{ $t('btn.archive') }}</button>
                @endif
            </div>
        </div>

        <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="note" data-safety-notice>{{ $t('safety_notice') }}</p>
        @unless ($status['configured'])<p class="rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700" role="status" data-not-configured>{{ $t('not_configured') }}</p>@endunless
        @if ($status['configured'] && ! $status['page_configured'])<p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert">{{ $t('page_missing_hint') }}</p>@endif
        @unless ($hashOk)<p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert" data-hash-bad>{{ $t('hash_bad') }}</p>@endunless
        @if ($approval)<p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800" data-approval>{{ $t('approved_until', ['v' => $approval->draft_version, 'at' => $approval->expires_at?->format('Y-m-d H:i')]) }} {{ $t('publish_hint') }}</p>@endif
        @if ($d->hasMetaObjects() && $st !== $S::PUBLISHED)<p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="status" data-frozen>{{ $t('frozen') }}</p>@endif
        @if ($d->last_error)<p class="break-words rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert" data-last-error>{{ $d->last_error }}</p>@endif
        @if ($check['errors'] && ! in_array($st, [$S::PUBLISHED, $S::ARCHIVED], true))
            <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert" data-check-errors><ul class="ms-5 list-disc">@foreach ($check['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif
        @if ($check['warnings'] && ! in_array($st, [$S::PUBLISHED, $S::ARCHIVED], true))
            <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="status" data-warnings><b>{{ $t('checks') }}:</b><ul class="ms-5 list-disc">@foreach ($check['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul></div>
        @endif

        @if (in_array($st, [$S::PENDING_APPROVAL, $S::APPROVED], true) && $me->can('meta_ads.approve'))
            <form data-ajax-form action="{{ route('marketer.meta-ads.reject', $d->id) }}" method="POST" class="flex flex-wrap items-center gap-2" novalidate data-reject-form>
                @csrf
                <input type="text" name="note" maxlength="1000" class="form-control min-w-0 flex-1" placeholder="{{ $t('note_placeholder') }}" aria-label="{{ $f('note') }}">
                <x-button type="submit" variant="danger" data-action="reject">{{ $t('btn.reject') }}</x-button>
            </form>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <x-card :title="$t('sections.basics')">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm" data-summary>
                    {!! $row($f('objective'), $t('objectives.'.$d->objective)) !!}
                    {!! $row($f('optimization_goal'), $d->optimization_goal) !!}
                    {!! $row($f('budget_amount'), $d->budget_amount.' '.$d->currency.' / '.$t('budget_types.'.$d->budget_type)) !!}
                    {!! $row($f('start_at'), $d->start_at?->format('Y-m-d H:i')) !!}
                    {!! $row($f('end_at'), $d->end_at?->format('Y-m-d H:i')) !!}
                    {!! $row($f('landing_url'), \App\Services\Marketing\Meta\MetaCampaignDraftService::absoluteLanding($d)) !!}
                    {!! $row($f('content_hash'), substr($d->content_hash, 0, 16).'…') !!}
                </dl>
            </x-card>
            <x-card :title="$t('sections.audience')">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    {!! $row($f('audience_description'), $aud['description'] ?? null) !!}
                    {!! $row($f('age_min').' – '.$f('age_max'), ($aud['age_min'] ?? '').' – '.($aud['age_max'] ?? '')) !!}
                    {!! $row($f('genders'), $t('genders.'.($aud['genders'] ?? 'all'))) !!}
                    {!! $row($f('interests'), implode(', ', (array) ($aud['interests'] ?? []))) !!}
                    {!! $row($f('countries'), implode(', ', (array) ($loc['countries'] ?? []))) !!}
                    {!! $row($f('cities'), implode(', ', (array) ($loc['cities'] ?? []))) !!}
                    {!! $row($f('placements'), ($pl['automatic'] ?? true) ? $f('automatic') : implode(', ', array_merge((array) ($pl['publisher_platforms'] ?? []), (array) ($pl['facebook_positions'] ?? []), (array) ($pl['instagram_positions'] ?? [])))) !!}
                </dl>
            </x-card>
            <x-card :title="$t('sections.products')">
                <ul class="space-y-1 text-sm" data-products>
                    @foreach ($products as $p)<li class="break-words">{{ $p->name }} <span class="text-slate-500" dir="ltr">{{ $p->sku }} · {{ $p->price }}</span></li>@endforeach
                </ul>
                <ul class="mt-2 space-y-1 text-xs text-slate-500" dir="ltr">@foreach ((array) $d->product_urls as $u)<li class="break-all">{{ $u }}</li>@endforeach</ul>
            </x-card>
            <x-card :title="$t('sections.copy')">
                <p class="whitespace-pre-line break-words text-sm">{{ $cp['primary_text'] ?? '' }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    {!! $row($f('headline'), $cp['headline'] ?? null) !!}
                    {!! $row($f('description'), $cp['description'] ?? null) !!}
                    {!! $row($f('cta'), $d->cta) !!}
                </dl>
            </x-card>
            <x-card :title="$t('sections.creative')">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" data-creatives>
                    @foreach ($assets as $a)
                        @can('ai_media.view')
                            <a href="{{ route('marketer.media.show', $a->id) }}" class="block min-w-0">
                                @if ($a->isImage())<img src="{{ route('marketer.media.thumb', $a->id) }}" alt="{{ $a->title }}" class="aspect-square w-full rounded-lg object-cover">@else<div class="flex aspect-square items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-500">{{ $a->kind->label() }}</div>@endif
                                <span class="mt-1 block truncate text-xs">{{ $a->title }} · {{ $a->status->label() }}</span>
                            </a>
                        @else
                            <span class="block truncate text-xs">{{ $a->title }} · {{ $a->status->label() }}</span>
                        @endcan
                    @endforeach
                </div>
                @if ($cr['canva_url'] ?? null)<p class="mt-2 break-all text-xs" dir="ltr"><a class="text-brand-700 hover:underline" href="{{ $cr['canva_url'] }}" target="_blank" rel="noopener noreferrer">{{ $cr['canva_url'] }}</a></p>@endif
                @if ($cr['notes'] ?? null)<p class="mt-2 break-words text-sm text-slate-600">{{ $cr['notes'] }}</p>@endif
            </x-card>
            <x-card :title="$t('sections.kpis').' / '.$t('sections.risks')">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    @foreach ((array) $d->expected_kpis as $k => $v){!! $row($k, $v) !!}@endforeach
                    {!! $row($f('risks'), $d->risks) !!}
                    {!! $row($f('assumptions'), $d->assumptions) !!}
                </dl>
            </x-card>
        </div>

        @if ($d->hasMetaObjects())
            <x-card :title="$f('meta_ids')">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm" data-meta-ids dir="ltr">
                    {!! $row('Campaign', $d->meta_campaign_id) !!}{!! $row('Ad set', $d->meta_adset_id) !!}{!! $row('Creative', $d->meta_creative_id) !!}{!! $row('Ad', $d->meta_ad_id) !!}
                    @foreach ((array) $d->meta_statuses as $k => $s){!! $row($t('verified_statuses').' — '.$k, $s['status'] ?? null) !!}@endforeach
                </dl>
            </x-card>
        @endif

        <details class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" data-payload-preview>
            <summary class="cursor-pointer select-none font-semibold text-slate-800">{{ $t('payload_preview') }}</summary>
            <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-50 p-3 text-xs" dir="ltr">{{ json_encode($payloads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>

        <x-card :title="$t('approvals')">
            <div class="overflow-x-auto" data-approvals>
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-500"><tr><th class="py-1 text-start">{{ $f('version') }}</th><th class="py-1 text-start">{{ $f('status') }}</th><th class="py-1 text-start">{{ $f('approver') }}</th><th class="py-1 text-start">{{ $f('expires_at') }}</th><th class="py-1 text-start">{{ $f('execution') }}</th><th class="py-1 text-start">{{ $f('note') }}</th></tr></thead>
                    <tbody>@foreach ($approvals as $a)<tr class="border-t border-slate-100"><td class="py-1.5">{{ $a->draft_version }}</td><td class="py-1.5" dir="ltr">{{ $a->approval_status }}{{ $a->invalidated_reason ? ' ('.$a->invalidated_reason.')' : '' }}</td><td class="py-1.5">{{ $a->approver?->name }}</td><td class="whitespace-nowrap py-1.5" dir="ltr">{{ $a->expires_at?->format('Y-m-d H:i') }}</td><td class="py-1.5" dir="ltr">{{ $a->execution_status }}</td><td class="py-1.5">{{ $a->note }}</td></tr>@endforeach</tbody>
                </table>
            </div>
        </x-card>

        <x-card :title="$t('audit')">
            <div class="overflow-x-auto" data-audit>
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-500"><tr><th class="py-1 text-start">{{ $f('when') }}</th><th class="py-1 text-start">{{ $f('action') }}</th><th class="py-1 text-start">{{ $f('actor') }}</th><th class="py-1 text-start">{{ $f('endpoint') }}</th></tr></thead>
                    <tbody>@foreach ($logs as $l)<tr class="border-t border-slate-100"><td class="whitespace-nowrap py-1.5" dir="ltr">{{ $l->created_at?->format('Y-m-d H:i:s') }}</td><td class="py-1.5" dir="ltr">{{ $l->action }}</td><td class="py-1.5" dir="ltr">{{ $l->actor_type }}{{ $l->actor_id ? '#'.$l->actor_id : '' }}</td><td class="py-1.5" dir="ltr">{{ $l->endpoint }}{{ $l->http_status ? ' ('.$l->http_status.')' : '' }}</td></tr>@endforeach</tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-layouts.marketer>
