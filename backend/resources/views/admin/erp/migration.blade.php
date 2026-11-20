<x-layouts.admin :title="__('erp.cards.migration')[0]">
    <x-slot:header><x-ui.page-header :title="__('erp.cards.migration')[0]" /></x-slot:header>
    @include('admin.erp._flash')
    <x-ui.card :title="__('erp.bf.auto_title')" class="mb-5">
        <p class="mb-3 text-sm text-panel-text-muted">{{ __('erp.bf.auto_hint') }} @if ($openingDate)<span class="font-medium">{{ __('erp.bf.opening_date') }}: {{ $openingDate }}</span>@endif</p>
        <form method="POST" action="{{ route('admin.erp.migration.toggle') }}" class="flex items-center gap-3 text-sm" data-auto>@csrf @method('PUT')
            <span class="rounded-full px-2 py-0.5 text-xs {{ $auto ? 'bg-panel-success-bg text-panel-success-fg' : 'bg-panel-warning-bg text-panel-warning-fg' }}">{{ $auto ? __('erp.bf.on') : __('erp.bf.off') }}</span>
            <input type="hidden" name="enabled" value="{{ $auto ? 0 : 1 }}">
            <x-ui.button type="submit">{{ $auto ? __('erp.bf.disable') : __('erp.bf.enable') }}</x-ui.button>
        </form>
    </x-ui.card>

    @if (($failures ?? collect())->isNotEmpty())
    <x-ui.card :title="__('erp_acc.failures')" class="mb-5">
        <table class="w-full text-sm" data-posting-failures><thead><tr class="border-b text-panel-text-subtle"><th class="py-2 text-start">{{ __('erp.f.date') }}</th><th class="text-start">{{ __('erp.f.source') }}</th><th class="text-start">{{ __('erp.f.description') }}</th><th></th></tr></thead><tbody>
        @foreach ($failures as $f)<tr class="border-b border-panel-border"><td class="py-1.5">{{ $f->created_at }}</td><td>{{ $f->subject_type }} #{{ $f->subject_id }}</td><td class="text-xs">{{ $f->meta['event'] ?? '' }}: {{ \Illuminate\Support\Str::limit($f->meta['error'] ?? '', 160) }}</td>
            <td class="text-end"><form method="POST" action="{{ route('admin.erp.migration.retry', $f->id) }}">@csrf<x-ui.button type="submit">{{ __('erp_acc.retry') }}</x-ui.button></form></td></tr>@endforeach
        </tbody></table>
    </x-ui.card>
    @endif
    <x-ui.card :title="__('erp.bf.runs')" class="mb-5">
        <div class="overflow-x-auto"><table class="w-full text-sm" data-runs>
            <thead><tr class="border-b text-panel-text-subtle"><th class="py-2 text-start">#</th><th class="text-start">{{ __('erp.bf.from') }}</th><th class="text-start">{{ __('erp.bf.status') }}</th><th class="text-start">{{ __('erp.bf.started') }}</th><th class="text-start">{{ __('erp.bf.by') }}</th><th class="text-start">{{ __('erp.bf.counts') }}</th></tr></thead>
            <tbody>@forelse ($runs as $run)<tr class="border-b border-panel-border"><td class="py-1.5">{{ $run->id }}</td><td>{{ $run->from_date->toDateString() }}</td><td>{{ $run->status }}</td><td>{{ $run->started_at }}</td><td>{{ $run->triggered_by }}</td>
                <td class="text-xs text-panel-text-subtle">@foreach ((array) $run->counts as $type => $c)@if (is_array($c)){{ $type }}: {{ $c['created'] ?? 0 }}/{{ $c['skipped'] ?? 0 }}/{{ $c['failed'] ?? 0 }}@if (! $loop->last) · @endif @endif @endforeach</td></tr>
            @empty<tr><td colspan="6" class="py-4 text-panel-text-subtle">{{ __('erp.bf.no_runs') }}</td></tr>@endforelse</tbody>
        </table></div>
        <p class="mt-2 text-xs text-panel-text-subtle">{{ __('erp.bf.counts_hint') }}</p>
    </x-ui.card>

    <x-ui.card :title="__('erp.bf.recon')" class="mb-5">
        <form method="POST" action="{{ route('admin.erp.migration.reconcile') }}" class="mb-3">@csrf<x-ui.button type="submit">{{ __('erp.bf.rerun') }}</x-ui.button></form>
        @if ($recon)
            <p data-mismatches="{{ $recon['mismatches'] }}" class="mb-2 text-sm {{ $recon['mismatches'] ? 'text-panel-danger-fg' : 'text-panel-success-fg' }}" data-recon>{{ __('erp.bf.recon_result', ['n' => $recon['mismatches'], 'from' => $recon['from'], 'to' => $recon['to']]) }}</p>
            <table class="w-full text-sm"><thead><tr class="border-b text-panel-text-subtle"><th class="py-1 text-start">{{ __('erp.bf.check') }}</th><th class="text-end">{{ __('erp.bf.expected') }}</th><th class="text-end">{{ __('erp.bf.actual') }}</th><th class="text-end">{{ __('erp.bf.diff') }}</th><th></th></tr></thead>
            <tbody>@foreach ($recon['checks'] as $c)<tr class="border-b border-panel-border"><td class="py-1">{{ $c['key'] }}</td><td class="text-end">{{ $c['expected'] }}</td><td class="text-end">{{ $c['actual'] }}</td><td class="text-end">{{ $c['difference'] }}</td><td class="text-end">{{ $c['ok'] ? 'OK' : __('erp.bf.mismatch') }}</td></tr>@endforeach</tbody></table>
        @endif
        <h4 class="mt-4 text-sm font-semibold text-panel-text">{{ __('erp.bf.benign_title') }}</h4>
        <ul class="mt-1 list-disc ps-5 text-xs text-panel-text-muted" data-benign>@foreach (__('erp.bf.benign') as $b)<li>{{ $b }}</li>@endforeach</ul>
    </x-ui.card>

    <x-ui.card :title="__('erp.bf.missing')">
        <p class="mb-3 text-sm text-panel-text-muted">{{ __('erp.bf.missing_hint') }}</p>
        <div class="mb-3 flex gap-3">
            <form method="POST" action="{{ route('admin.erp.migration.detect') }}">@csrf<x-ui.button type="submit">{{ __('erp.bf.detect') }}</x-ui.button></form>
            <form method="POST" action="{{ route('admin.erp.migration.post-missing') }}" onsubmit="return confirm('{{ __('erp.bf.confirm_post') }}')">@csrf<x-ui.button type="submit">{{ __('erp.bf.post_missing') }}</x-ui.button></form>
        </div>
        @if ($missing)
            <table class="w-full text-sm" data-missing><thead><tr class="border-b text-panel-text-subtle"><th class="py-1 text-start">{{ __('erp.bf.type') }}</th><th class="text-end">{{ __('erp.bf.n_missing') }}</th><th class="text-end">{{ __('erp.bf.amount') }}</th><th class="text-start ps-4">{{ __('erp.bf.sample') }}</th></tr></thead>
            <tbody>@foreach ($missing as $type => $m)<tr class="border-b border-panel-border"><td class="py-1">{{ $type }}</td><td class="text-end">{{ $m['created'] }}</td><td class="text-end">{{ $m['amount'] }}</td><td class="ps-4 text-xs text-panel-text-subtle">{{ implode(', ', $m['missing']) }}</td></tr>@endforeach</tbody></table>
        @endif
    </x-ui.card>
</x-layouts.admin>
