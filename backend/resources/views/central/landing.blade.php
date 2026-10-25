<x-layouts.base :title="__('admin.site.title')" :is-rtl="$lang === 'ar'" body-class="bg-slate-50">
    <main class="mx-auto flex min-h-screen max-w-5xl flex-col px-4 py-10">
        <header class="mb-10 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="{{ asset('logo.png') }}" alt="" class="size-10 object-contain">
                <span class="text-lg font-bold">{{ config('app.name') }}</span>
            </div>
            <a class="text-sm font-semibold text-brand-600" href="{{ route('central.landing', ['lang' => $lang === 'ar' ? 'en' : 'ar']) }}">{{ __('admin.site.switch_lang') }}</a>
        </header>

        <section class="mb-8 text-center">
            <h1 class="text-3xl font-extrabold">{{ __('admin.site.headline') }}</h1>
            <p class="mt-2 text-slate-500">{{ __('admin.site.sub') }}</p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($tenants as $t)
                <a href="{{ route('central.choose', $t['slug']) }}" data-tenant-card="{{ $t['slug'] }}"
                   class="group rounded-2xl border bg-white p-6 shadow-sm transition hover:border-brand-600 hover:shadow-md {{ $preferred === $t['slug'] ? 'border-brand-600 ring-2 ring-brand-600/20' : 'border-slate-200' }}">
                    <div class="text-5xl">{{ $t['flag'] }}</div>
                    <h2 class="mt-3 text-lg font-bold">{{ $t['name'] }}</h2>
                    @if ($preferred === $t['slug'])<span class="text-xs font-semibold text-brand-600">{{ __('admin.site.last_choice') }}</span>@endif
                    <dl class="mt-3 space-y-1 text-sm text-slate-500">
                        <div class="flex justify-between"><dt>{{ __('admin.site.currency') }}</dt><dd class="font-semibold text-slate-700">{{ $t['currency'] }}</dd></div>
                        <div class="flex justify-between"><dt>{{ __('admin.site.languages') }}</dt><dd class="font-semibold text-slate-700">{{ implode('، ', $t['languages']) }}</dd></div>
                    </dl>
                    <span class="mt-4 inline-block text-sm font-semibold text-brand-600">{{ __('admin.site.go') }} &rarr;</span>
                </a>
            @empty
                <p class="col-span-full text-center text-slate-500">{{ __('admin.site.empty') }}</p>
            @endforelse
        </section>

        <footer class="mt-auto pt-12 text-center text-sm text-slate-400">
            <a href="{{ route('central.login') }}">{{ __('admin.site.admin') }}</a>
        </footer>
    </main>
</x-layouts.base>
