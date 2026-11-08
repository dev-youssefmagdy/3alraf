<x-layouts.marketer :title="__('gift_finder.tags_title')">
    @php $g = fn ($k, $a = []) => __('gift_finder.'.$k, $a); @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $g('tags_hint') }}</p>
        @if (session('success'))<p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('success') }}</p>@endif
        @can('gift_finder.manage')
        <form method="POST" action="{{ route('marketer.gift-finder.tags.suggest') }}" onsubmit="return confirm(@js($g('suggest_confirm')))">
            @csrf
            <button class="btn btn-secondary">{{ $g('suggest') }}</button>
            <a href="{{ route('marketer.gift-finder.index') }}" class="ms-3 text-sm text-brand-700 underline">{{ $g('title') }}</a>
        </form>
        @endcan
        <form method="POST" action="{{ route('marketer.gift-finder.tags.save') }}" class="space-y-3">
            @csrf
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr>
                        <th class="px-3 py-2 text-start">{{ $g('tag_product') }}</th><th class="px-3 py-2">{{ $g('tag_suitable') }}</th>
                        <th class="px-3 py-2 text-start">{{ $g('tag_recipients') }}</th><th class="px-3 py-2 text-start">{{ $g('tag_occasions') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($products as $tp)
                            <tr class="align-top">
                                <td class="px-3 py-2 font-medium">{{ $tp->name }}</td>
                                <td class="px-3 py-2 text-center"><input type="checkbox" name="tags[{{ $tp->id }}][suitable]" value="1" @checked($tp->gift_suitable) class="size-4"></td>
                                <td class="px-3 py-2"><div class="flex flex-wrap gap-x-3 gap-y-1">@foreach ($recipients as $o)<label class="inline-flex items-center gap-1 text-xs"><input type="checkbox" name="tags[{{ $tp->id }}][recipients][]" value="{{ $o }}" @checked(in_array($o, (array) $tp->gift_recipients, true))>{{ __('gift_finder.recipients.'.$o) }}</label>@endforeach</div></td>
                                <td class="px-3 py-2"><div class="flex flex-wrap gap-x-3 gap-y-1">@foreach ($occasions as $o)<label class="inline-flex items-center gap-1 text-xs"><input type="checkbox" name="tags[{{ $tp->id }}][occasions][]" value="{{ $o }}" @checked(in_array($o, (array) $tp->gift_occasions, true))>{{ __('gift_finder.occasions.'.$o) }}</label>@endforeach</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @can('gift_finder.manage')<button class="btn btn-primary">{{ __('gift_finder.tags_save') }}</button>@endcan
        </form>
    </div>
</x-layouts.marketer>
