{{-- AvatarGroup: $items = [['name' => , 'src' => ]], overlapping, "+N" when more than max. --}}
@props(['items' => [], 'max' => 4, 'size' => 'sm'])
<div {{ $attributes->merge(['class' => 'flex items-center -space-x-2 rtl:space-x-reverse']) }}>
    @foreach (array_slice($items, 0, $max) as $a)<x-ui.avatar :name="$a['name'] ?? ''" :src="$a['src'] ?? null" :size="$size" class="ring-2 ring-panel-surface" />@endforeach
    @if (count($items) > $max)<span class="inline-grid size-8 place-items-center rounded-full bg-panel-surface-sunken text-xs font-bold text-panel-text-muted ring-2 ring-panel-surface"><bdi dir="ltr">+{{ count($items) - $max }}</bdi></span>@endif
</div>
