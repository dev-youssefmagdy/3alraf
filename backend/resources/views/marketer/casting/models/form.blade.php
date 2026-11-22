@php($t = fn ($k) => __('casting.'.$k))
<x-layouts.marketer :title="$m->exists ? $m->name : $t('models.new')">
    <x-slot:header><x-ui.page-header :title="$m->exists ? $m->name : $t('models.new')" :breadcrumbs="[[$t('models.title'), route('marketer.casting.models.index')]]" /></x-slot:header>
    <form method="POST" action="{{ $m->exists ? route('marketer.casting.models.update', $m->id) : route('marketer.casting.models.store') }}" class="grid max-w-3xl gap-3 sm:grid-cols-2">
        @csrf @if ($m->exists)@method('PUT')@endif
        <x-ui.input name="name" class="sm:col-span-2" :label="$t('fields.name')" :value="$m->name" maxlength="120" required :error="$errors->first('name')" />
        <x-ui.select name="gender" :label="$t('fields.gender')" :options="['female' => $t('fields.female'), 'male' => $t('fields.male')]" :value="$m->gender" />
        <x-ui.input name="age_range" :label="$t('fields.age_range')" :value="$m->age_range" maxlength="20" />
        <x-ui.textarea name="appearance" class="sm:col-span-2" :label="$t('fields.appearance')" :value="$m->appearance" rows="2" />
        <x-ui.input name="hairstyle" :label="$t('fields.hairstyle')" :value="$m->hairstyle" />
        <x-ui.input name="clothing_style" :label="$t('fields.clothing_style')" :value="$m->clothing_style" />
        <x-ui.input name="personality" :label="$t('fields.personality')" :value="$m->personality" />
        <x-ui.input name="ad_role" :label="$t('fields.ad_role')" :value="$m->ad_role" />
        <div class="sm:col-span-2"><button class="btn btn-primary btn-md" type="submit">{{ $t('models.save') }}</button></div>
    </form>
</x-layouts.marketer>
