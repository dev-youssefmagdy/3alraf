<x-layouts.storefront :title="__('growth.blog')">
    <h1 class="mb-4 text-2xl font-bold">{{ __('growth.blog') }}</h1>
    <div class="grid gap-4 md:grid-cols-3">
        @forelse ($posts as $p)
            <a href="{{ route('storefront.blog.show', ['locale' => app()->getLocale(), 'slug' => $p->slug]) }}" class="block rounded-xl border border-slate-200 bg-white p-4 hover:shadow">
                <h2 class="font-semibold">{{ $p->title }}</h2>
                <p class="mt-1 text-xs text-slate-400">{{ $p->published_at?->format('Y-m-d') }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ $p->excerpt }}</p>
                <span class="mt-2 inline-block text-sm text-brand-700">{{ __('growth.read_more') }}</span>
            </a>
        @empty<p class="col-span-full text-slate-500">{{ __('admin.no_data') }}</p>@endforelse
    </div>
    <div class="mt-6">{{ $posts->links() }}</div>
</x-layouts.storefront>
