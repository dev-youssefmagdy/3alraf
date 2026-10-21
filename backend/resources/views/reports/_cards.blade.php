<div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ($cards as $c)
        <x-stat-card :label="$c['label']" :value="$c['value']" :tone="$c['tone']" class="[&_p:nth-child(2)]:!text-2xl" />
    @endforeach
</div>
