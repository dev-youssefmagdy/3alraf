<x-layouts.storefront :title="$t->name">
    @php $b = fn ($k) => __('growth_hub.batch.'.$k); $inp = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2'; @endphp
    <div class="mx-auto max-w-xl space-y-4">
        <h1 class="text-2xl font-extrabold">{{ $t->name }}</h1>
        <p class="text-sm text-slate-600">{{ $t->description }}</p>
        @if ($t->preorder)<div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm"><b>{{ $b('preorder') }}</b><p class="whitespace-pre-line">{{ $t->preorder_terms }}</p></div>@endif
        <form data-ajax-form action="{{ route('storefront.launch.signup', ['locale' => app()->getLocale(), 'code' => $t->code]) }}" method="POST" class="space-y-3" novalidate>@csrf
            <label class="text-sm">{{ $b('contact') }}<input name="contact" class="{{ $inp }}" required></label>
            @if ($t->mode === 'survey' && $t->survey_question)<label class="text-sm">{{ $t->survey_question }}<input name="answer" maxlength="300" class="{{ $inp }}"></label>@endif
            @if ($t->mode === 'limited_stock' || $t->preorder)<label class="text-sm">{{ $b('quantity') }}<input type="number" name="quantity" min="1" max="20" value="1" class="{{ $inp }}"></label>@endif
            @if ($t->preorder)<label class="flex items-start gap-2 text-sm"><input type="checkbox" name="preorder_accepted" value="1" class="mt-1"> {{ $b('accept_label') }}</label>@endif
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-1"> {{ $b('consent') }}</label>
            <button type="submit" class="btn btn-primary">{{ $b('join') }}</button>
        </form>
    </div>
</x-layouts.storefront>
