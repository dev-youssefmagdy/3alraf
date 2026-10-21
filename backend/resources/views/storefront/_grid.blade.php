@forelse ($items as $tp)<x-product-card :tp="$tp" />@empty<p class="col-span-full py-10 text-center text-slate-500">{{ __('store.no_results') }}</p>@endforelse
