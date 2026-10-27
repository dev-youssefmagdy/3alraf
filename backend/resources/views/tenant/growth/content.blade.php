<x-layouts.admin guard="tenant" :title="__('growth_hub.content.title')">
    @php $c = fn ($k) => __('growth_hub.content.'.$k); $inp = 'block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm'; @endphp
    <div class="space-y-4" id="ct">
        <p class="text-sm text-slate-600">{{ $c('hint') }} {{ $c('ai_note') }}</p>
        <a href="{{ route('tenant.ai.generators.index') }}" class="inline-block text-sm text-brand-700 underline">{{ $c('generate') }}</a>
        <x-card :title="$c('calendar')">
            @can('growth.manage')
                <form data-ajax-form action="{{ route('tenant.growth.content.schedule') }}" method="POST" class="mb-4 grid gap-3 sm:grid-cols-5" novalidate>
                    @csrf
                    <label class="text-sm text-slate-600 sm:col-span-2">{{ $c('fields.content_asset_id') }}<select name="content_asset_id" class="{{ $inp }}">@foreach ($assets as $a)<option value="{{ $a->id }}">#{{ $a->id }} {{ $c('types.'.$a->type) }} - {{ \Illuminate\Support\Str::limit($a->title ?: $a->body, 40) }}</option>@endforeach</select></label>
                    <label class="text-sm text-slate-600">{{ $c('fields.channel') }}<select name="channel" class="{{ $inp }}">@foreach ($channels as $ch)<option value="{{ $ch }}">{{ ucfirst($ch) }}</option>@endforeach</select></label>
                    <label class="text-sm text-slate-600">{{ $c('fields.scheduled_for') }}<input type="datetime-local" name="scheduled_for" class="{{ $inp }}"></label>
                    <div class="self-end"><x-button type="submit">{{ $c('schedule') }}</x-button></div>
                </form>
            @endcan
            @forelse ($items as $i)
                <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 py-2 text-sm" data-item="{{ $i->id }}">
                    <span>{{ $i->scheduled_for->format('Y-m-d H:i') }}</span><span class="badge badge-muted">{{ ucfirst($i->channel) }}</span>
                    <span class="flex-1">{{ $c('types.'.($i->asset?->type ?? 'hook')) }}: {{ \Illuminate\Support\Str::limit($i->asset?->title ?: $i->asset?->body, 60) }}</span>
                    <span class="badge {{ $i->status === 'published' ? 'badge-success' : 'badge-warning' }}">{{ $c('statuses.'.$i->status) }}</span>
                    @can('growth.manage')@if ($i->status === 'planned')<button type="button" data-act="publish" class="text-xs text-brand-700 underline">{{ $c('mark') }}</button><button type="button" data-act="skip" class="text-xs text-slate-500 underline">{{ $c('skip') }}</button>@endif @endcan
                </div>
            @empty<p class="text-sm text-slate-500">{{ $c('no_items') }}</p>@endforelse
        </x-card>
        <x-card :title="$c('assets')">
            @forelse ($assets as $a)
                <div class="border-t border-slate-100 py-2 text-sm"><div class="flex flex-wrap gap-2"><b>{{ $c('types.'.$a->type) }}</b><span class="text-slate-500">{{ $names[$a->product_id] ?? '' }}</span><span class="badge badge-muted">{{ $c('statuses.'.$a->status) }}</span></div>
                    @if ($a->title)<p class="font-medium">{{ $a->title }}</p>@endif<p class="whitespace-pre-line text-slate-600">{{ $a->body }}</p>
                    @if ($a->tracked_url)<p class="text-xs text-slate-400" dir="ltr">{{ $c('tracked') }}: {{ $a->tracked_url }}</p>@endif</div>
            @empty<p class="text-sm text-slate-500">{{ $c('no_assets') }}</p>@endforelse
        </x-card>
    </div>
    <script>
        document.querySelectorAll('#ct [data-act]').forEach(b => b.addEventListener('click', async () => {
            const r = await fetch(@json(url('/admin/growth/content')) + '/' + b.closest('[data-item]').dataset.item + '/' + b.dataset.act, {method: 'POST', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}});
            r.ok ? location.reload() : alert((await r.json().catch(() => ({}))).message || 'Error');
        }));
    </script>
</x-layouts.admin>
