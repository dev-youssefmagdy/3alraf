@php
    $t = fn ($k, $r = []) => __('marketing.meta.'.$k, $r);
    $f = fn ($k) => __('marketing.meta.fields.'.$k);
    $S = \App\Enums\Marketing\MetaDraftStatus::class;
    $st = $d->status;
    $me = auth('marketer')->user();
    $aud = (array) $d->audience; $loc = (array) $d->locations; $pl = (array) $d->placements; $cr = (array) $d->creative; $cp = (array) $d->copy;
    $editable = $st->editable() && ! $d->hasMetaObjects();
    $canPublish = $me->can('meta_ads.publish') && $approval && (in_array($st, [$S::APPROVED, $S::FAILED], true) || \App\Services\Marketing\Meta\MetaPublisher::interrupted($d)) && $hashOk && $status['configured'] && $status['page_configured'];
    $row = fn ($label, $value) => '<dt class="text-panel-text-muted">'.e($label).'</dt><dd class="min-w-0 break-words">'.($value === null || $value === '' ? '—' : e($value)).'</dd>';
@endphp
<x-layouts.marketer :title="$d->name">
    <x-slot:header>
        <x-ui.page-header :title="'#'.$d->id.' '.$d->name" :breadcrumbs="[[$t('title'), route('marketer.meta-ads.index')]]">
            <div class="flex flex-wrap items-center gap-2 max-md:w-full" data-actions>
                @if ($editable && $me->can('meta_ads.draft'))
                    <a class="btn btn-secondary btn-md" href="{{ route('marketer.meta-ads.edit', $d->id) }}" data-action="edit">{{ $t('btn.edit') }}</a>
                @endif
                @if (in_array($st, [$S::DRAFT, $S::REJECTED], true) && $me->can('meta_ads.draft'))
                    <button type="button" class="btn btn-secondary btn-md" data-action="submit" data-post-action="{{ route('marketer.meta-ads.submit', $d->id) }}">{{ $t('btn.submit') }}</button>
                @endif
                @if ($st === $S::PENDING_APPROVAL && $me->can('meta_ads.approve') && $hashOk && ! $check['errors'])
                    <button type="button" class="btn btn-primary btn-md" data-action="approve" data-post-action="{{ route('marketer.meta-ads.approve', $d->id) }}" data-confirm="{{ $t('confirm.approve') }}">{{ $t('btn.approve') }}</button>
                @endif
                @if ($canPublish)
                    <button type="button" class="btn btn-primary btn-md" data-action="publish" data-post-action="{{ route('marketer.meta-ads.publish', $d->id) }}" data-confirm="{{ $t('confirm.publish') }}">{{ $t('btn.publish') }}</button>
                @endif
                @if (! in_array($st, [$S::PUBLISHING, $S::ARCHIVED], true) && $me->can('meta_ads.draft'))
                    <button type="button" class="btn btn-secondary btn-md" data-action="archive" data-post-action="{{ route('marketer.meta-ads.archive', $d->id) }}" data-confirm="{{ $t('confirm.archive') }}">{{ $t('btn.archive') }}</button>
                @endif
            </div>
        </x-ui.page-header>
    </x-slot:header>
    <div class="space-y-4" data-meta-draft="{{ $d->id }}" data-status="{{ $st->value }}">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge :tone="$st->badge()" dot data-status-badge>{{ $st->label() }}</x-ui.badge>
            <x-ui.badge>{{ $f('version') }} {{ $d->version }}</x-ui.badge>
            <x-ui.badge>{{ $t('objectives.'.$d->objective) }}</x-ui.badge>
        </div>

        <x-ui.alert tone="success" data-safety-notice>{{ $t('safety_notice') }}</x-ui.alert>
        @unless ($status['configured'])<x-ui.alert tone="info" data-not-configured>{{ $t('not_configured') }}</x-ui.alert>@endunless
        @if ($status['configured'] && ! $status['page_configured'])<x-ui.alert tone="warning">{{ $t('page_missing_hint') }}</x-ui.alert>@endif
        @unless ($hashOk)<x-ui.alert tone="danger" data-hash-bad>{{ $t('hash_bad') }}</x-ui.alert>@endunless
        @if ($approval)<x-ui.alert tone="success" data-approval>{{ $t('approved_until', ['v' => $approval->draft_version, 'at' => $approval->expires_at?->format('Y-m-d H:i')]) }} {{ $t('publish_hint') }}</x-ui.alert>@endif
        @if ($d->hasMetaObjects() && $st !== $S::PUBLISHED)<x-ui.alert tone="warning" data-frozen>{{ $t('frozen') }}</x-ui.alert>@endif
        @if ($d->last_error)<x-ui.alert tone="danger" class="break-words" data-last-error>{{ $d->last_error }}</x-ui.alert>@endif
        @if ($check['errors'] && ! in_array($st, [$S::PUBLISHED, $S::ARCHIVED], true))
            <x-ui.alert tone="danger" data-check-errors><ul class="ms-5 list-disc">@foreach ($check['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul></x-ui.alert>
        @endif
        @if ($check['warnings'] && ! in_array($st, [$S::PUBLISHED, $S::ARCHIVED], true))
            <x-ui.alert tone="warning" data-warnings><div><b>{{ $t('checks') }}:</b><ul class="ms-5 list-disc">@foreach ($check['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul></div></x-ui.alert>
        @endif

        @if (in_array($st, [$S::PENDING_APPROVAL, $S::APPROVED], true) && $me->can('meta_ads.approve'))
            <form data-ajax-form action="{{ route('marketer.meta-ads.reject', $d->id) }}" method="POST" class="flex flex-wrap items-center gap-2" novalidate data-reject-form>
                @csrf
                <input type="text" name="note" maxlength="1000" class="form-control min-w-0 flex-1" placeholder="{{ $t('note_placeholder') }}" aria-label="{{ $f('note') }}">
                <x-ui.button type="submit" variant="danger" data-action="reject">{{ $t('btn.reject') }}</x-ui.button>
            </form>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <x-ui.card :title="$t('sections.basics')">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm" data-summary>
                    {!! $row($f('objective'), $t('objectives.'.$d->objective)) !!}
                    {!! $row($f('optimization_goal'), $d->optimization_goal) !!}
                    {!! $row($f('budget_amount'), $d->budget_amount.' '.$d->currency.' / '.$t('budget_types.'.$d->budget_type)) !!}
                    {!! $row($f('start_at'), $d->start_at?->format('Y-m-d H:i')) !!}
                    {!! $row($f('end_at'), $d->end_at?->format('Y-m-d H:i')) !!}
                    {!! $row($f('landing_url'), \App\Services\Marketing\Meta\MetaCampaignDraftService::absoluteLanding($d)) !!}
                    {!! $row($f('content_hash'), substr($d->content_hash, 0, 16).'…') !!}
                </dl>
            </x-ui.card>
            <x-ui.card :title="$t('sections.audience')">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    {!! $row($f('audience_description'), $aud['description'] ?? null) !!}
                    {!! $row($f('age_min').' – '.$f('age_max'), ($aud['age_min'] ?? '').' – '.($aud['age_max'] ?? '')) !!}
                    {!! $row($f('genders'), $t('genders.'.($aud['genders'] ?? 'all'))) !!}
                    {!! $row($f('interests'), implode(', ', (array) ($aud['interests'] ?? []))) !!}
                    {!! $row($f('countries'), implode(', ', (array) ($loc['countries'] ?? []))) !!}
                    {!! $row($f('cities'), implode(', ', (array) ($loc['cities'] ?? []))) !!}
                    {!! $row($f('placements'), ($pl['automatic'] ?? true) ? $f('automatic') : implode(', ', array_merge((array) ($pl['publisher_platforms'] ?? []), (array) ($pl['facebook_positions'] ?? []), (array) ($pl['instagram_positions'] ?? [])))) !!}
                </dl>
            </x-ui.card>
            <x-ui.card :title="$t('sections.products')">
                <ul class="space-y-1 text-sm" data-products>
                    @foreach ($products as $p)<li class="break-words">{{ $p->name }} <span class="text-panel-text-muted" dir="ltr">{{ $p->sku }} · {{ $p->price }}</span></li>@endforeach
                </ul>
                <ul class="mt-2 space-y-1 text-xs text-panel-text-muted" dir="ltr">@foreach ((array) $d->product_urls as $u)<li class="break-all">{{ $u }}</li>@endforeach</ul>
            </x-ui.card>
            <x-ui.card :title="$t('sections.copy')">
                <p class="whitespace-pre-line break-words text-sm">{{ $cp['primary_text'] ?? '' }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    {!! $row($f('headline'), $cp['headline'] ?? null) !!}
                    {!! $row($f('description'), $cp['description'] ?? null) !!}
                    {!! $row($f('cta'), $d->cta) !!}
                </dl>
            </x-ui.card>
            <x-ui.card :title="$t('sections.creative')">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" data-creatives>
                    @foreach ($assets as $a)
                        @can('ai_media.view')
                            <a href="{{ route('marketer.media.show', $a->id) }}" class="block min-w-0">
                                @if ($a->isImage())<img src="{{ route('marketer.media.thumb', $a->id) }}" alt="{{ $a->title }}" class="aspect-square w-full rounded-lg object-cover">@else<div class="flex aspect-square items-center justify-center rounded-lg bg-panel-surface-sunken text-xs text-panel-text-muted">{{ $a->kind->label() }}</div>@endif
                                <span class="mt-1 block truncate text-xs">{{ $a->title }} · {{ $a->status->label() }}</span>
                            </a>
                        @else
                            <span class="block truncate text-xs">{{ $a->title }} · {{ $a->status->label() }}</span>
                        @endcan
                    @endforeach
                </div>
                @if ($cr['canva_url'] ?? null)<p class="mt-2 break-all text-xs" dir="ltr"><a class="text-panel-accent-text hover:underline" href="{{ $cr['canva_url'] }}" target="_blank" rel="noopener noreferrer">{{ $cr['canva_url'] }}</a></p>@endif
                @if ($cr['notes'] ?? null)<p class="mt-2 break-words text-sm text-panel-text-muted">{{ $cr['notes'] }}</p>@endif
            </x-ui.card>
            <x-ui.card :title="$t('sections.kpis').' / '.$t('sections.risks')">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    @foreach ((array) $d->expected_kpis as $k => $v){!! $row($k, $v) !!}@endforeach
                    {!! $row($f('risks'), $d->risks) !!}
                    {!! $row($f('assumptions'), $d->assumptions) !!}
                </dl>
            </x-ui.card>
        </div>

        @if ($d->hasMetaObjects())
            <x-ui.card :title="$f('meta_ids')">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm" data-meta-ids dir="ltr">
                    {!! $row('Campaign', $d->meta_campaign_id) !!}{!! $row('Ad set', $d->meta_adset_id) !!}{!! $row('Creative', $d->meta_creative_id) !!}{!! $row('Ad', $d->meta_ad_id) !!}
                    @foreach ((array) $d->meta_statuses as $k => $s){!! $row($t('verified_statuses').' — '.$k, $s['status'] ?? null) !!}@endforeach
                </dl>
            </x-ui.card>
        @endif

        <details class="rounded-2xl border border-panel-border bg-panel-surface p-4 shadow-panel-xs" data-payload-preview>
            <summary class="cursor-pointer select-none font-semibold text-panel-text">{{ $t('payload_preview') }}</summary>
            <pre class="mt-3 overflow-x-auto rounded-lg bg-panel-surface-sunken p-3 text-xs" dir="ltr">{{ json_encode($payloads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>

        <x-ui.card :title="$t('approvals')">
            <x-ui.table data-approvals>
                    <thead><tr><th>{{ $f('version') }}</th><th>{{ $f('status') }}</th><th>{{ $f('approver') }}</th><th>{{ $f('expires_at') }}</th><th>{{ $f('execution') }}</th><th>{{ $f('note') }}</th></tr></thead>
                    <tbody>@foreach ($approvals as $a)<tr><td data-label="">{{ $a->draft_version }}</td><td data-label="{{ $f('status') }}" dir="ltr">{{ $a->approval_status }}{{ $a->invalidated_reason ? ' ('.$a->invalidated_reason.')' : '' }}</td><td data-label="{{ $f('approver') }}">{{ $a->approver?->name }}</td><td data-label="{{ $f('expires_at') }}" dir="ltr">{{ $a->expires_at?->format('Y-m-d H:i') }}</td><td data-label="{{ $f('execution') }}" dir="ltr">{{ $a->execution_status }}</td><td data-label="{{ $f('note') }}">{{ $a->note }}</td></tr>@endforeach</tbody>
                </x-ui.table>
        </x-ui.card>

        <x-ui.card :title="$t('audit')">
            <x-ui.table data-audit>
                    <thead><tr><th>{{ $f('when') }}</th><th>{{ $f('action') }}</th><th>{{ $f('actor') }}</th><th>{{ $f('endpoint') }}</th></tr></thead>
                    <tbody>@foreach ($logs as $l)<tr><td data-label="" dir="ltr">{{ $l->created_at?->format('Y-m-d H:i:s') }}</td><td data-label="{{ $f('action') }}" dir="ltr">{{ $l->action }}</td><td data-label="{{ $f('actor') }}" dir="ltr">{{ $l->actor_type }}{{ $l->actor_id ? '#'.$l->actor_id : '' }}</td><td data-label="{{ $f('endpoint') }}" dir="ltr">{{ $l->endpoint }}{{ $l->http_status ? ' ('.$l->http_status.')' : '' }}</td></tr>@endforeach</tbody>
                </x-ui.table>
        </x-ui.card>
    </div>
</x-layouts.marketer>
