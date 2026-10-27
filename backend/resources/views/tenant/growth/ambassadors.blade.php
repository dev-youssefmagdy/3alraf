<x-layouts.admin guard="tenant" :title="__('growth_hub.amb.title')">
    @php $a = fn ($k, $r = []) => __('growth_hub.amb.'.$k, $r); $opts = $affiliates->mapWithKeys(fn ($x) => [$x->id => $x->name.' ('.$x->email.')'])->all(); $v = fn ($x) => $x ?? '—'; @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $a('hint') }}</p>
        @can('growth.manage')
            <x-card :title="$a('enroll')">
                <form data-ajax-form action="{{ route('tenant.growth.ambassadors.enroll') }}" method="POST" class="grid gap-3 sm:grid-cols-3" novalidate>@csrf
                    <x-select name="affiliate_id" :label="$a('affiliate')" :options="$opts" /><x-input name="reward_per_order" type="number" step="0.01" min="0" :label="$a('reward')" value="0" /><x-input name="reward_rule" :label="$a('rule')" />
                    <x-input name="disclosure" :label="$a('disclosure')" class="sm:col-span-3" required />
                    <label class="flex items-center gap-2 text-sm sm:col-span-3"><input type="checkbox" name="terms_accepted" value="1"> {{ $a('terms') }}</label>
                    <div class="sm:col-span-3"><x-button type="submit">{{ __('admin.save') }}</x-button></div>
                </form>
            </x-card>
        @endcan
        @forelse ($rows as $r)
            @php $p = $r->report; @endphp
            <x-card :title="$r->affiliate?->name">
                <p class="text-xs text-slate-500">{{ $a('statuses.'.$r->status) }} | {{ $a('reward') }}: {{ $r->reward_per_order }} | {{ $a('report') }} | {{ $a('window', ['d' => $p['window_days']]) }}</p>
                <div class="grid gap-2 text-sm sm:grid-cols-6">
                    @foreach (['valid' => $p['valid_orders'], 'pending' => $p['pending_orders'], 'excluded' => $p['excluded_orders'], 'new_share' => $p['new_customer_share'], 'cac' => $p['referral_cac'], 'margin' => $p['margin_after_rewards']] as $k => $x)<div><span class="text-slate-500">{{ $a($k) }}</span><br><b>{{ $v($x) }}</b></div>@endforeach
                </div>
                <p class="text-xs text-slate-500">{{ $r->disclosure }}</p>
                @can('growth.manage')
                    <form data-ajax-form action="{{ route('tenant.growth.ambassadors.update', $r->id) }}" method="POST" class="mt-2 flex flex-wrap items-end gap-2" novalidate>@csrf @method('PUT')
                        <x-select name="status" :label="$a('status')" :options="__('growth_hub.amb.statuses')" :value="$r->status" /><x-input name="reward_per_order" type="number" step="0.01" min="0" :label="$a('reward')" :value="$r->reward_per_order" /><x-input name="reward_rule" :label="$a('rule')" :value="$r->reward_rule" /><x-button type="submit">{{ __('admin.save') }}</x-button></form>
                @endcan
            </x-card>
        @empty<p class="text-sm text-slate-500">{{ $a('none') }}</p>@endforelse
        <x-card :title="$a('ideas')">
            @foreach ($ideas as $i)<p class="text-sm"><b>{{ $i->title }}</b>: {{ $i->body }} @can('growth.manage')<button type="button" data-delete="{{ route('tenant.growth.ambassadors.ideas.destroy', $i->id) }}" class="btn-sm btn-danger">{{ __('admin.delete') }}</button>@endcan</p>@endforeach
            @can('growth.manage')<form data-ajax-form action="{{ route('tenant.growth.ambassadors.ideas.store') }}" method="POST" class="mt-2 grid gap-3 sm:grid-cols-3" novalidate>@csrf<x-input name="title" :label="$a('idea_title')" /><x-input name="channel" :label="$a('channel')" /><x-textarea name="body" :label="$a('idea_body')" class="sm:col-span-3" /><x-button type="submit">{{ $a('add_idea') }}</x-button></form>@endcan
        </x-card>
    </div>
</x-layouts.admin>
