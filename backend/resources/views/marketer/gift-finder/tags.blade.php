<x-layouts.marketer :title="__('gift_finder.tags_title')">
    @php $g = fn ($k, $a = []) => __('gift_finder.'.$k, $a); @endphp
    <x-slot:header>
        <x-ui.page-header :title="$g('tags_title')" :subtitle="$g('tags_hint')" :breadcrumbs="[[$g('title'), route('marketer.gift-finder.index')]]">
            @can('gift_finder.manage')
                <form method="POST" action="{{ route('marketer.gift-finder.tags.suggest') }}" onsubmit="return confirm(@js($g('suggest_confirm')))">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" icon="sparkles">{{ $g('suggest') }}</x-ui.button>
                </form>
            @endcan
        </x-ui.page-header>
    </x-slot:header>
    <div class="space-y-4">
        @if (session('success'))<x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>@endif
        <form method="POST" action="{{ route('marketer.gift-finder.tags.save') }}" class="space-y-3">
            @csrf
            <div class="md:rounded-2xl md:border md:border-panel-border md:bg-panel-surface md:p-3 md:shadow-panel-xs">
                <x-ui.table>
                    <thead><tr><th>{{ $g('tag_product') }}</th><th>{{ $g('tag_suitable') }}</th><th>{{ $g('tag_recipients') }}</th><th>{{ $g('tag_occasions') }}</th></tr></thead>
                    <tbody>
                        @foreach ($products as $tp)
                            <tr class="align-top">
                                <td data-label="" class="font-semibold">{{ $tp->name }}</td>
                                <td data-label="{{ $g('tag_suitable') }}"><input type="checkbox" name="tags[{{ $tp->id }}][suitable]" value="1" @checked($tp->gift_suitable) class="size-5 rounded-md border-panel-border-strong" aria-label="{{ $g('tag_suitable') }}"></td>
                                <td data-label="{{ $g('tag_recipients') }}" class="max-md:!flex-wrap max-md:!justify-start"><div class="flex flex-wrap gap-x-3 gap-y-1">@foreach ($recipients as $o)<label class="inline-flex min-h-8 items-center gap-1.5 text-xs"><input type="checkbox" name="tags[{{ $tp->id }}][recipients][]" value="{{ $o }}" @checked(in_array($o, (array) $tp->gift_recipients, true)) class="size-4 rounded border-panel-border-strong">{{ __('gift_finder.recipients.'.$o) }}</label>@endforeach</div></td>
                                <td data-label="{{ $g('tag_occasions') }}" class="max-md:!flex-wrap max-md:!justify-start"><div class="flex flex-wrap gap-x-3 gap-y-1">@foreach ($occasions as $o)<label class="inline-flex min-h-8 items-center gap-1.5 text-xs"><input type="checkbox" name="tags[{{ $tp->id }}][occasions][]" value="{{ $o }}" @checked(in_array($o, (array) $tp->gift_occasions, true)) class="size-4 rounded border-panel-border-strong">{{ __('gift_finder.occasions.'.$o) }}</label>@endforeach</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>
            @can('gift_finder.manage')<x-ui.button type="submit">{{ __('gift_finder.tags_save') }}</x-ui.button>@endcan
        </form>
    </div>
</x-layouts.marketer>
