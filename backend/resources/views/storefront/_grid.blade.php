@forelse ($items as $item)<x-product-card :product="$item" :eager="$loop->index < 4" />@empty<p class="col-span-full py-10 text-center text-slate-500">{{ __('store.no_results') }}</p>@endforelse
