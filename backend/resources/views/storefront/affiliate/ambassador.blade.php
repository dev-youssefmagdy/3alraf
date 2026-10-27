<x-layouts.storefront :title="__('growth_hub.amb.title')">
    @include('storefront.affiliate._nav')
    @php $a = fn ($k, $r = []) => __('growth_hub.amb.'.$k, $r); $inp = 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm'; @endphp
    <div class="space-y-6">
        <x-card :title="$a('guide')"><ul class="list-disc ps-5 text-sm">@foreach (__('growth_hub.amb.guide_body') as $g)<li>{{ $g }}</li>@endforeach</ul></x-card>
        @if (! $amb)
            <x-card :title="$a('join')"><form data-ajax-form action="{{ route('storefront.affiliate.ambassador.join', ['locale' => app()->getLocale()]) }}" method="POST" class="space-y-3" novalidate>@csrf
                <label class="block text-sm">{{ $a('disclosure') }}<input name="disclosure" class="{{ $inp }} w-full" required></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="terms_accepted" value="1"> {{ $a('terms') }}</label>
                <button type="submit" class="btn btn-primary">{{ $a('join') }}</button></form></x-card>
        @else
            <x-card :title="$a('report')">
                <div class="grid gap-2 text-sm sm:grid-cols-5">@foreach (['valid' => $report['valid_orders'], 'pending' => $report['pending_orders'], 'excluded' => $report['excluded_orders'], 'new_share' => $report['new_customer_share'], 'cac' => $report['referral_cac']] as $k => $x)<div><span class="text-slate-500">{{ $a($k) }}</span><br><b>{{ $x ?? '—' }}</b></div>@endforeach</div>
                <p class="mt-2 text-xs text-slate-500">{{ $a('rewards_note') }} {{ $a('window', ['d' => $report['window_days']]) }}</p>
            </x-card>
            <x-card :title="$a('my_links')">
                @foreach ($links as $l)<p class="text-sm"><code>{{ $l->short }}</code> | {{ $l->channel }} | {{ $a('clicks') }}: {{ $l->clicks }}</p>@endforeach
                <form data-ajax-form action="{{ route('storefront.affiliate.ambassador.links', ['locale' => app()->getLocale()]) }}" method="POST" class="mt-3 flex flex-wrap items-end gap-2" novalidate>@csrf
                    <select name="product_id" class="{{ $inp }}">@foreach ($products as $tp)<option value="{{ $tp->product_id }}">{{ $tp->product?->name }}</option>@endforeach</select>
                    <select name="channel" class="{{ $inp }}">@foreach ($channels as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select>
                    <button type="submit" class="btn btn-primary">{{ $a('new_link') }}</button></form>
            </x-card>
            <x-card :title="$a('ideas')">@foreach ($ideas as $i)<p class="text-sm"><b>{{ $i->title }}</b>: {{ $i->body }}</p>@endforeach</x-card>
        @endif
    </div>
</x-layouts.storefront>
