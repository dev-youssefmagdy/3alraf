<x-layouts.admin guard="tenant" :title="__('growth_hub.settings.title')">
    <x-card class="mb-4 max-w-3xl">
        <h2 class="mb-1 font-semibold">{{ __('growth_hub.features.title') }}</h2>
        <p class="mb-4 text-sm text-slate-600">{{ __('growth_hub.features.hint') }}</p>
        <form data-ajax-form data-method="PUT" action="{{ route('tenant.growth.settings.features') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            @foreach (collect($registry)->groupBy('group', true) as $group => $items)
                <div>
                    <div class="mb-1 text-xs font-semibold uppercase text-slate-500">{{ __('growth_hub.features.groups.'.$group) }}</div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($items as $k => $f)
                            @php($key = $k)
                            <div>
                                <x-checkbox name="features[{{ $key }}]" value="1" :label="$f[app()->getLocale() === 'ar' ? 'ar' : 'en']" :checked="$switched[$key] ?? false" />
                                @if (! empty($f['depends_on']))
                                    <div class="ms-6 text-xs text-slate-500">{{ __('growth_hub.features.needs') }}: {{ collect($f['depends_on'])->map(fn ($d) => $registry[$d][app()->getLocale() === 'ar' ? 'ar' : 'en'] ?? $d)->implode('، ') }}</div>
                                @endif
                                @if (($switched[$key] ?? false) && ! ($active[$key] ?? false))
                                    <div class="ms-6 text-xs text-amber-600">{{ __('growth_hub.features.inactive_dep') }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <x-button type="submit">{{ __('admin.save') }}</x-button>
        </form>
    </x-card>
    <x-card class="max-w-3xl">
        <p class="mb-4 text-sm text-slate-600">{{ __('growth_hub.settings.hint') }}</p>
        <form data-ajax-form data-method="PUT" action="{{ route('tenant.growth.settings.update') }}" method="POST" class="grid gap-4 sm:grid-cols-2" novalidate>
            @csrf
            @foreach ($limits as $k => [$min, $max])
                <x-input name="{{ $k }}" type="number" step="any" :min="$min" :max="$max" :label="__('growth_hub.settings.fields.'.$k)" :value="$overrides[$k] ?? null"
                    :hint="__('growth_hub.settings.default', ['v' => $defaults[$k]])" />
            @endforeach
            <x-input name="auto_reply_allowlist" class="sm:col-span-2" :label="__('growth_hub.settings.fields.auto_reply_allowlist')" :value="implode(', ', $overrides['auto_reply_allowlist'] ?? [])" :hint="__('growth_hub.settings.allowlist_hint')" />
            <div class="sm:col-span-2"><x-button type="submit">{{ __('admin.save') }}</x-button></div>
        </form>
    </x-card>
</x-layouts.admin>
