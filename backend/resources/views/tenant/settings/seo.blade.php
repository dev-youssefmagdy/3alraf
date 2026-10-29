@php
    $checks = [
        'indexable' => $seo['indexable'],
        'sitemap' => true,
        'robots' => $seo['indexable'],
        'verified' => $seo['google_verification'] !== '',
        'analytics' => $seo['ga4_id'] !== '' || $seo['gtm_id'] !== '',
        'home' => collect($tenant->locales)->every(fn ($l) => ($seo['home_title'][$l] ?? '') !== '' && ($seo['home_description'][$l] ?? '') !== ''),
        'business' => $seo['org_logo'] !== '' && ($seo['org_phone'] !== '' || $seo['org_email'] !== '') && collect($seo['social'])->filter()->isNotEmpty(),
    ];
    $done = collect($checks)->filter()->count();
    $connected = fn (string ...$keys) => collect($keys)->contains(fn ($k) => $seo[$k] !== '');
    $sections = [
        'general' => [__('seo.tabs.general'), 'M21 21l-4.3-4.3M11 18a7 7 0 100-14 7 7 0 000 14z', null],
        'verify' => [__('seo.tabs.verify'), 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', $connected('google_verification', 'bing_verification', 'yandex_verification', 'pinterest_verification', 'facebook_domain_verification')],
        'analytics' => [__('seo.tabs.analytics'), 'M4 19V9m5 10V5m5 14v-7m5 7V8', $connected('ga4_id', 'gtm_id', 'google_ads_id', 'meta_pixel_id', 'tiktok_pixel_id', 'snapchat_pixel_id', 'clarity_id')],
        'business' => [__('seo.tabs.business'), 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6', $seo['org_logo'] !== '' || collect($seo['social'])->filter()->isNotEmpty()],
        'guide' => [__('seo.tabs.guide'), 'M12 6v12m-6-6h12', null],
    ];
    $first = $languages->first()?->code;
    $copyBtn = fn ($v) => '<button type="button" class="btn btn-secondary btn-sm" data-copy="'.e($v).'" data-done="'.e(__('seo.general.copied')).'">'.e(__('seo.general.copy')).'</button>';
@endphp
<x-layouts.admin guard="tenant" :title="__('seo.title')">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900">{{ __('seo.title') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('seo.subtitle') }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]">
        {{-- sticky side: health + section nav --}}
        <aside class="space-y-4 lg:sticky lg:top-4 lg:self-start">
            <x-card :title="__('seo.health.title')">
                <div class="mb-3 flex items-center gap-3">
                    <div class="grid size-12 shrink-0 place-items-center rounded-full text-sm font-bold {{ $done >= 6 ? 'bg-emerald-100 text-emerald-700' : ($done >= 4 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">{{ $done }}/{{ count($checks) }}</div>
                    <p class="text-xs text-slate-500">{{ __('seo.health.done', ['done' => $done, 'total' => count($checks)]) }}</p>
                </div>
                <ul class="space-y-2 text-sm">
                    @foreach ($checks as $k => $ok)
                        <li class="flex items-start gap-2 {{ $ok ? 'text-slate-700' : 'text-slate-500' }}">
                            <svg class="mt-0.5 size-4 shrink-0 {{ $ok ? 'text-emerald-600' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ok ? 'M5 13l4 4L19 7' : 'M12 8v5m0 3h.01' }}"/></svg>
                            <span>{{ __('seo.health.'.$k) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
            <nav class="rounded-xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="{{ __('seo.title') }}">
                @foreach ($sections as $id => [$label, $icon, $on])
                    <a href="#sec-{{ $id }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-brand-700">
                        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                        <span class="flex-1">{{ $label }}</span>
                        @if ($on !== null)<span class="size-2 rounded-full {{ $on ? 'bg-emerald-500' : 'bg-slate-300' }}" title="{{ $on ? __('seo.status.connected') : __('seo.status.not_set') }}"></span>@endif
                    </a>
                @endforeach
            </nav>
        </aside>

        <form data-ajax-form data-method="PUT" data-validate-url="{{ route('tenant.seo.validate') }}" action="{{ route('tenant.seo.update') }}" method="POST" class="min-w-0 space-y-6" novalidate>
            @csrf

            {{-- 1. search appearance --}}
            <x-card id="sec-general" class="scroll-mt-4" :title="__('seo.tabs.general')">
                <div class="space-y-6">
                    <div>
                        <x-checkbox name="indexable" :label="__('seo.general.indexable')" :checked="$seo['indexable']" />
                        <p class="mt-1 ps-6 text-xs text-slate-500">{{ __('seo.general.indexable_hint') }}</p>
                    </div>
                    <div class="space-y-4 border-t border-slate-100 pt-5">
                        <div><h3 class="text-sm font-semibold text-slate-800">{{ __('seo.general.home') }}</h3><p class="text-xs text-slate-500">{{ __('seo.general.home_hint') }}</p></div>
                        <x-translatable-tabs name="home_title" :label="__('seo.fields.home_title')" :languages="$languages" :values="$seo['home_title']" />
                        <x-translatable-tabs name="home_description" type="textarea" :rows="2" :label="__('seo.fields.home_description')" :languages="$languages" :values="$seo['home_description']" />
                        <x-translatable-tabs name="title_suffix" :label="__('seo.fields.title_suffix')" :languages="$languages" :values="$seo['title_suffix']" />
                        <p class="-mt-2 text-xs text-slate-500">{{ __('seo.general.suffix_hint') }}</p>
                        <div class="rounded-lg border border-slate-200 bg-white p-4" aria-live="polite">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('seo.general.preview') }}</p>
                            <p class="truncate text-xs text-slate-600" dir="ltr">{{ parse_url(url('/'), PHP_URL_HOST) }} › {{ $first }}</p>
                            <p class="mt-0.5 truncate text-lg text-blue-800" data-prev-title>{{ ($seo['home_title'][$first] ?? '') ?: $tenant->name }}</p>
                            <p class="line-clamp-2 text-sm text-slate-600" data-prev-desc>{{ ($seo['home_description'][$first] ?? '') ?: __('store.seo_default_desc') }}</p>
                            <p class="mt-2 text-xs text-slate-400"><span data-prev-tlen>0</span> / 60 · <span data-prev-dlen>0</span> / 160 {{ __('seo.general.chars') }}</p>
                        </div>
                    </div>
                    <div class="space-y-4 border-t border-slate-100 pt-5">
                        <h3 class="text-sm font-semibold text-slate-800">{{ __('seo.general.sharing') }}</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-input name="default_og_image" type="url" dir="ltr" placeholder="https://" :label="__('seo.fields.default_og_image')" :value="$seo['default_og_image']" :hint="__('seo.general.og_image_hint')" />
                            <x-input name="twitter_handle" dir="ltr" placeholder="@store" :label="__('seo.fields.twitter_handle')" :value="$seo['twitter_handle']" :hint="__('seo.general.twitter_hint')" />
                        </div>
                    </div>
                    <div class="space-y-3 border-t border-slate-100 pt-5">
                        <h3 class="text-sm font-semibold text-slate-800">{{ __('seo.general.urls') }}</h3>
                        @foreach ([__('seo.general.sitemap_url') => $sitemapUrl, __('seo.general.robots_url') => $robotsUrl] as $lbl => $u)
                            <div class="flex flex-wrap items-center gap-2 rounded-lg bg-slate-50 px-3 py-2">
                                <span class="w-20 text-xs font-semibold text-slate-500">{{ $lbl }}</span>
                                <a href="{{ $u }}" target="_blank" rel="noopener" dir="ltr" class="min-w-0 flex-1 truncate text-sm text-brand-700 hover:underline">{{ $u }}</a>
                                {!! $copyBtn($u) !!}
                            </div>
                        @endforeach
                        <p class="rounded-lg bg-emerald-50 p-3 text-xs leading-5 text-emerald-800">{{ __('seo.general.auto') }}</p>
                    </div>
                </div>
            </x-card>

            {{-- 2. verification --}}
            <x-card id="sec-verify" class="scroll-mt-4" :title="__('seo.tabs.verify')">
                <p class="mb-4 text-sm text-slate-600">{{ __('seo.verify.intro') }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="google_verification" dir="ltr" autocomplete="off" :label="__('seo.verify.google')" :value="$seo['google_verification']" :hint="__('seo.verify.google_hint')" />
                    <x-input name="bing_verification" dir="ltr" autocomplete="off" :label="__('seo.verify.bing')" :value="$seo['bing_verification']" :hint="__('seo.verify.bing_hint')" />
                    <x-input name="yandex_verification" dir="ltr" autocomplete="off" :label="__('seo.verify.yandex')" :value="$seo['yandex_verification']" />
                    <x-input name="pinterest_verification" dir="ltr" autocomplete="off" :label="__('seo.verify.pinterest')" :value="$seo['pinterest_verification']" />
                    <x-input name="facebook_domain_verification" dir="ltr" autocomplete="off" :label="__('seo.verify.facebook')" :value="$seo['facebook_domain_verification']" />
                </div>
                <p class="mt-4 text-xs text-slate-500">{{ __('seo.verify.sitemap_note') }} <a class="text-brand-700 underline" dir="ltr" href="{{ $sitemapUrl }}" target="_blank" rel="noopener">{{ $sitemapUrl }}</a></p>
            </x-card>

            {{-- 3. analytics & pixels --}}
            <x-card id="sec-analytics" class="scroll-mt-4" :title="__('seo.tabs.analytics')">
                <p class="mb-4 text-sm text-slate-600">{{ __('seo.analytics.intro') }}</p>
                <div class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input name="ga4_id" dir="ltr" placeholder="G-XXXXXXXXXX" autocomplete="off" :label="__('seo.analytics.ga4')" :value="$seo['ga4_id']" :hint="__('seo.analytics.ga4_hint')" />
                        <x-input name="gtm_id" dir="ltr" placeholder="GTM-XXXXXXX" autocomplete="off" :label="__('seo.analytics.gtm')" :value="$seo['gtm_id']" :hint="__('seo.analytics.gtm_hint')" />
                    </div>
                    <div class="grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                        <x-input name="google_ads_id" dir="ltr" placeholder="AW-123456789" autocomplete="off" :label="__('seo.analytics.ads').' — '.__('seo.fields.google_ads_id')" :value="$seo['google_ads_id']" :hint="__('seo.analytics.ads_id_hint')" />
                        <x-input name="google_ads_label" dir="ltr" autocomplete="off" :label="__('seo.fields.google_ads_label')" :value="$seo['google_ads_label']" :hint="__('seo.analytics.ads_label_hint')" />
                    </div>
                    <div class="grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                        <x-input name="meta_pixel_id" dir="ltr" inputmode="numeric" autocomplete="off" :label="__('seo.analytics.meta')" :value="$seo['meta_pixel_id']" :hint="__('seo.analytics.meta_hint')" />
                        <x-input name="tiktok_pixel_id" dir="ltr" autocomplete="off" :label="__('seo.analytics.tiktok')" :value="$seo['tiktok_pixel_id']" :hint="__('seo.analytics.tiktok_hint')" />
                        <x-input name="snapchat_pixel_id" dir="ltr" autocomplete="off" :label="__('seo.analytics.snapchat')" :value="$seo['snapchat_pixel_id']" :hint="__('seo.analytics.snapchat_hint')" />
                        <x-input name="clarity_id" dir="ltr" autocomplete="off" :label="__('seo.analytics.clarity')" :value="$seo['clarity_id']" :hint="__('seo.analytics.clarity_hint')" />
                    </div>
                    <div class="space-y-1 border-t border-slate-100 pt-5">
                        <h3 class="text-sm font-semibold text-slate-800">{{ __('seo.analytics.privacy') }}</h3>
                        <x-checkbox name="track_staff" :label="__('seo.analytics.track_staff')" :checked="$seo['track_staff']" />
                        <p class="ps-6 text-xs text-slate-500">{{ __('seo.analytics.track_staff_hint') }}</p>
                        <p class="pt-2 text-xs text-amber-700">{{ __('seo.analytics.consent') }}</p>
                    </div>
                </div>
            </x-card>

            {{-- 4. business & social --}}
            <x-card id="sec-business" class="scroll-mt-4" :title="__('seo.tabs.business')">
                <p class="mb-4 text-sm text-slate-600">{{ __('seo.business.intro') }}</p>
                <div class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-select name="org_type" :label="__('seo.fields.org_type')" :options="__('seo.business.types')" :value="$seo['org_type'] ?: 'Organization'" />
                        <x-input name="org_logo" type="url" dir="ltr" placeholder="https://" :label="__('seo.fields.org_logo')" :value="$seo['org_logo']" :hint="__('seo.business.logo_hint')" />
                        <x-input name="org_phone" type="tel" dir="ltr" placeholder="+20…" :label="__('seo.fields.org_phone')" :value="$seo['org_phone']" />
                        <x-input name="org_email" type="email" dir="ltr" :label="__('seo.fields.org_email')" :value="$seo['org_email']" />
                    </div>
                    <div class="border-t border-slate-100 pt-5">
                        <h3 class="mb-3 text-sm font-semibold text-slate-800">{{ __('seo.business.address') }}</h3>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-input name="org_street" class="sm:col-span-3" :label="__('seo.fields.org_street')" :value="$seo['org_street']" />
                            <x-input name="org_city" :label="__('seo.fields.org_city')" :value="$seo['org_city']" />
                            <x-input name="org_region" :label="__('seo.fields.org_region')" :value="$seo['org_region']" />
                            <x-input name="org_postal" dir="ltr" :label="__('seo.fields.org_postal')" :value="$seo['org_postal']" />
                            <x-input name="org_country" dir="ltr" maxlength="2" placeholder="EG" :label="__('seo.fields.org_country')" :value="$seo['org_country']" />
                        </div>
                    </div>
                    <div class="border-t border-slate-100 pt-5">
                        <h3 class="text-sm font-semibold text-slate-800">{{ __('seo.business.social') }}</h3>
                        <p class="mb-3 text-xs text-slate-500">{{ __('seo.business.social_hint') }}</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach (\App\Services\SeoSettingsService::SOCIALS as $s)
                                <x-input name="social[{{ $s }}]" type="url" dir="ltr" placeholder="https://" :label="__('seo.fields.social.'.$s)" :value="$seo['social'][$s]" />
                            @endforeach
                        </div>
                    </div>
                    <div class="border-t border-slate-100 pt-5">
                        <h3 class="text-sm font-semibold text-slate-800">{{ __('seo.business.apps') }}</h3>
                        <p class="mb-3 text-xs text-slate-500">{{ __('seo.business.apps_hint') }}</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-input name="app_links[ios]" type="url" dir="ltr" placeholder="https://apps.apple.com/..." label="App Store" :value="$seo['app_links']['ios']" />
                            <x-input name="app_links[android]" type="url" dir="ltr" placeholder="https://play.google.com/..." label="Google Play" :value="$seo['app_links']['android']" />
                        </div>
                    </div>
                </div>
            </x-card>

            <div class="sticky bottom-0 z-10 -mx-1 rounded-xl border border-slate-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur">
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                    <a href="{{ route('tenant.dashboard') }}" class="btn btn-secondary">{{ __('admin.cancel') }}</a>
                </div>
            </div>
        </form>
    </div>

    {{-- 5. guide (outside the form) --}}
    <x-card id="sec-guide" class="mt-6 scroll-mt-4 lg:ms-[18.5rem]" :title="__('seo.tabs.guide')">
        <p class="mb-4 text-sm text-slate-600">{{ __('seo.guide.intro') }}</p>
        <ol class="space-y-3">
            @foreach (__('seo.guide.steps') as $step)
                <li class="rounded-lg border border-slate-100 bg-slate-50 p-4">
                    <h3 class="text-sm font-semibold text-slate-800">{{ $step['t'] }}</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ $step['b'] }}</p>
                </li>
            @endforeach
        </ol>
        <p class="mt-4 text-xs text-slate-500">{{ __('seo.guide.docs') }}</p>
    </x-card>

    <script>
        (function () {
            document.querySelectorAll('[data-copy]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var v = b.dataset.copy, label = b.textContent;
                    (navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).catch(function () {}).finally(function () { b.textContent = b.dataset.done; setTimeout(function () { b.textContent = label; }, 1500); });
                });
            });
            var t = document.querySelector('[name="home_title[{{ $first }}]"]'), d = document.querySelector('[name="home_description[{{ $first }}]"]');
            function sync() {
                var tt = document.querySelector('[data-prev-title]'), dd = document.querySelector('[data-prev-desc]');
                if (t) { tt.textContent = t.value || @js($tenant->name); document.querySelector('[data-prev-tlen]').textContent = t.value.length; }
                if (d) { dd.textContent = d.value || @js(__('store.seo_default_desc')); document.querySelector('[data-prev-dlen]').textContent = d.value.length; }
            }
            [t, d].forEach(function (el) { if (el) el.addEventListener('input', sync); }); sync();
        })();
    </script>
</x-layouts.admin>
