{{-- Edit a suggestion (new version; nothing is applied). Fields come from the type handler's editFields(). --}}
@php
    $t = fn ($k, $r = []) => __('marketing.suggestions.'.$k, $r);
    $p = (array) $s->payload;
    $locales = \App\Support\Marketing\StoreText::locales();
    $nameOf = fn (string $path) => collect(explode('.', $path))->map(fn ($seg, $i) => $i === 0 ? $seg : '['.$seg.']')->implode('');
    $get = fn (string $field) => data_get(['payload' => $p], $field);
    $hint = fn ($f) => isset($f['hint']) ? $t('hints.'.$f['hint']) : null;
@endphp
<x-layouts.marketer :title="$t('edit_title').' #'.$s->id">
    <x-slot:header><x-ui.page-header :title="$t('edit_title')" :subtitle="'#'.$s->id.' '.$s->title" :breadcrumbs="[['#'.$s->id.' '.$s->title, route('marketer.suggestions.show', $s->id)]]" /></x-slot:header>
    <form data-ajax-form data-method="PUT" data-validate-url="{{ route('marketer.suggestions.validate', $s->id) }}" action="{{ route('marketer.suggestions.update', $s->id) }}" method="POST" class="space-y-4" novalidate data-edit-form>
        @csrf
        <x-ui.alert tone="info">{{ $t('edit_hint') }}</x-ui.alert>
        <x-ui.form-section :title="$t('edit_title')">
            <x-ui.input name="title" :label="$t('cols.title')" :value="$s->title" required maxlength="200" />
            <x-ui.select name="priority" :label="$t('fields.priority')" :value="$s->priority->value" :options="collect(\App\Enums\Marketing\SuggestionPriority::cases())->mapWithKeys(fn ($pr) => [$pr->value => $pr->label()])->all()" />
            <x-ui.textarea name="description" :label="$t('fields.description')" :value="$s->description" rows="2" class="md:col-span-2" />
            <x-ui.textarea name="reasoning" :label="$t('fields.reasoning')" :value="$s->reasoning" rows="3" class="md:col-span-2" />
            <x-ui.input name="expires_at" type="datetime-local" :label="$t('fields.expires_at')" :value="$s->expires_at?->format('Y-m-d\TH:i')" dir="ltr" />
        </x-ui.form-section>

        <x-ui.form-section :title="$s->type->label()" :cols="1">
            @foreach ($fields as $f)
                @php $name = $nameOf($f['name']); $val = $get($f['name']); $id = 'f_'.md5($f['name']); @endphp
                @switch($f['kind'])
                    @case('locales-text')
                    @case('locales-textarea')
                    @case('locales-html')
                        <div data-field data-edit-field="{{ $f['name'] }}">
                            <span class="form-label">{{ $f['label'] }}</span>
                            <div class="grid gap-2 md:grid-cols-2">
                                @foreach ($locales as $l)
                                    <div>
                                        <span class="text-xs text-panel-text-muted">{{ strtoupper($l) }}</span>
                                        @if ($f['kind'] === 'locales-text')
                                            <input type="text" name="{{ $name }}[{{ $l }}]" value="{{ is_array($val) ? ($val[$l] ?? '') : '' }}" class="form-control" dir="{{ $l === 'ar' ? 'rtl' : 'ltr' }}">
                                        @else
                                            <textarea name="{{ $name }}[{{ $l }}]" rows="{{ $f['kind'] === 'locales-html' ? 8 : 3 }}" class="form-control {{ $f['kind'] === 'locales-html' ? 'font-mono text-xs' : '' }}" dir="{{ $l === 'ar' ? 'rtl' : 'ltr' }}">{{ is_array($val) ? ($val[$l] ?? '') : '' }}</textarea>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @break
                    @case('textarea')
                        <x-ui.field :name="$id" :label="$f['label']" data-edit-field="{{ $f['name'] }}"><textarea id="f-{{ $id }}" name="{{ $name }}" rows="3" class="form-control">{{ is_scalar($val) ? $val : '' }}</textarea></x-ui.field>
                        @break
                    @case('number')
                        <x-ui.field :name="$id" :label="$f['label']" data-edit-field="{{ $f['name'] }}"><input id="f-{{ $id }}" type="number" step="{{ $f['step'] ?? '1' }}" name="{{ $name }}" value="{{ is_scalar($val) ? $val : '' }}" class="form-control" dir="ltr"></x-ui.field>
                        @break
                    @case('datetime')
                        <x-ui.field :name="$id" :label="$f['label']" data-edit-field="{{ $f['name'] }}"><input id="f-{{ $id }}" type="datetime-local" name="{{ $name }}" value="{{ $val ? \Carbon\Carbon::parse($val)->format('Y-m-d\TH:i') : '' }}" class="form-control" dir="ltr"></x-ui.field>
                        @break
                    @case('bool')
                        <div data-field data-edit-field="{{ $f['name'] }}"><input type="hidden" name="{{ $name }}" value="0"><x-ui.checkbox :name="$name" :label="$f['label']" :bool="false" :checked="(bool) $val" /></div>
                        @break
                    @case('select')
                        <x-ui.field :name="$id" :label="$f['label']" data-edit-field="{{ $f['name'] }}">
                            <select id="f-{{ $id }}" name="{{ $name }}" class="form-control">
                                <option value="">—</option>
                                @foreach ($f['options'] as $o)<option value="{{ $o }}" @selected($val === $o)>{{ $o }}</option>@endforeach
                            </select>
                        </x-ui.field>
                        @break
                    @case('multi')
                        <div data-field data-edit-field="{{ $f['name'] }}">
                            <span class="form-label">{{ $f['label'] }}</span>
                            <div class="flex flex-wrap gap-x-4">@foreach ($f['options'] as $o)<x-ui.checkbox :name="$name.'[]'" :label="$o" :value="$o" :bool="false" :checked="in_array($o, (array) $val, true)" />@endforeach</div>
                        </div>
                        @break
                    @case('list')
                        <x-ui.field :name="$id" :label="$f['label'].' ('.$t('list_hint').')'" data-edit-field="{{ $f['name'] }}"><textarea id="f-{{ $id }}" name="{{ $name }}" rows="4" class="form-control" dir="{{ $f['dir'] ?? 'auto' }}">{{ implode("\n", (array) $val) }}</textarea></x-ui.field>
                        @break
                    @case('id-list')
                        <x-ui.field :name="$id" :label="$f['label']" data-edit-field="{{ $f['name'] }}"><input id="f-{{ $id }}" type="text" name="{{ $name }}" value="{{ implode(',', (array) $val) }}" class="form-control" dir="ltr" placeholder="1,2,3"></x-ui.field>
                        @break
                    @case('json')
                        <x-ui.field :name="$id" :label="$f['label'].' ('.$t('json_hint').')'" data-edit-field="{{ $f['name'] }}"><textarea id="f-{{ $id }}" name="{{ $name }}" rows="6" class="form-control font-mono text-xs" dir="ltr" placeholder="{{ $hint($f) }}">{{ $val ? json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '' }}</textarea></x-ui.field>
                        @break
                    @case('colors')
                        <div data-field data-edit-field="{{ $f['name'] }}">
                            <span class="form-label">{{ $f['label'] }}</span>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach ($f['options'] as $tok)
                                    @php $cv = is_array($val) ? ($val[$tok] ?? '') : ''; @endphp
                                    <label class="flex items-center gap-2 text-xs" dir="ltr">
                                        <span class="inline-block size-6 shrink-0 rounded border border-panel-border-strong" style="background: {{ \App\Support\Marketing\Contrast::normalizeHex($cv) ?? 'transparent' }}" data-swatch></span>
                                        <span class="w-28 shrink-0 truncate">{{ str_replace('color-', '', $tok) }}</span>
                                        <input type="text" name="{{ $name }}[{{ $tok }}]" value="{{ $cv }}" maxlength="7" placeholder="#rrggbb" class="form-control !py-1" data-color-input>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @break
                    @default
                        <x-ui.field :name="$id" :label="$f['label']" data-edit-field="{{ $f['name'] }}"><input id="f-{{ $id }}" type="text" name="{{ $name }}" value="{{ is_scalar($val) ? $val : '' }}" class="form-control" dir="{{ $f['dir'] ?? 'auto' }}"></x-ui.field>
                @endswitch
            @endforeach
            <x-ui.input name="note" :label="$t('fields.note')" maxlength="500" />
        </x-ui.form-section>
        <x-ui.save-bar :cancel="route('marketer.suggestions.show', $s->id)" />
    </form>
    <script>
    document.addEventListener('input', (e) => {
        if (!e.target.matches('[data-color-input]')) return;
        const v = e.target.value.trim();
        e.target.closest('label').querySelector('[data-swatch]').style.background = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.test(v) ? (v.startsWith('#') ? v : '#' + v) : 'transparent';
    });
    </script>
</x-layouts.marketer>
