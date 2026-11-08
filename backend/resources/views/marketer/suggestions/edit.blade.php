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
    <div class="space-y-4">
        <a href="{{ route('marketer.suggestions.show', $s->id) }}" class="text-sm text-brand-600 hover:underline">← #{{ $s->id }} {{ $s->title }}</a>
        <x-card :title="$t('edit_title')">
            <p class="mb-4 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $t('edit_hint') }}</p>
            <form data-ajax-form data-method="PUT" data-validate-url="{{ route('marketer.suggestions.validate', $s->id) }}" action="{{ route('marketer.suggestions.update', $s->id) }}" method="POST" class="space-y-4" novalidate data-edit-form>
                @csrf
                <div class="grid gap-3 md:grid-cols-2">
                    <x-input name="title" :label="$t('cols.title')" :value="$s->title" required maxlength="200" />
                    <div data-field>
                        <label class="form-label" for="priority">{{ $t('fields.priority') }}</label>
                        <select id="priority" name="priority" class="form-control">@foreach (\App\Enums\Marketing\SuggestionPriority::cases() as $pr)<option value="{{ $pr->value }}" @selected($s->priority === $pr)>{{ $pr->label() }}</option>@endforeach</select>
                    </div>
                    <div data-field class="md:col-span-2"><label class="form-label" for="description">{{ $t('fields.description') }}</label><textarea id="description" name="description" rows="2" class="form-control">{{ $s->description }}</textarea></div>
                    <div data-field class="md:col-span-2"><label class="form-label" for="reasoning">{{ $t('fields.reasoning') }}</label><textarea id="reasoning" name="reasoning" rows="3" class="form-control">{{ $s->reasoning }}</textarea></div>
                    <div data-field><label class="form-label" for="expires_at">{{ $t('fields.expires_at') }}</label><input id="expires_at" type="datetime-local" name="expires_at" class="form-control" dir="ltr" value="{{ $s->expires_at?->format('Y-m-d\TH:i') }}"></div>
                </div>

                <fieldset class="space-y-3 rounded-lg border border-slate-200 p-3">
                    <legend class="px-1 text-sm font-semibold text-slate-700">{{ $s->type->label() }}</legend>
                    @foreach ($fields as $f)
                        @php $name = $nameOf($f['name']); $val = $get($f['name']); $id = 'f_'.md5($f['name']); @endphp
                        <div data-field data-edit-field="{{ $f['name'] }}">
                            @switch($f['kind'])
                                @case('locales-text')
                                @case('locales-textarea')
                                @case('locales-html')
                                    <span class="form-label">{{ $f['label'] }}</span>
                                    <div class="grid gap-2 md:grid-cols-2">
                                        @foreach ($locales as $l)
                                            <div>
                                                <span class="text-xs text-slate-500">{{ strtoupper($l) }}</span>
                                                @if ($f['kind'] === 'locales-text')
                                                    <input type="text" name="{{ $name }}[{{ $l }}]" value="{{ is_array($val) ? ($val[$l] ?? '') : '' }}" class="form-control" dir="{{ $l === 'ar' ? 'rtl' : 'ltr' }}">
                                                @else
                                                    <textarea name="{{ $name }}[{{ $l }}]" rows="{{ $f['kind'] === 'locales-html' ? 8 : 3 }}" class="form-control {{ $f['kind'] === 'locales-html' ? 'font-mono text-xs' : '' }}" dir="{{ $l === 'ar' ? 'rtl' : 'ltr' }}">{{ is_array($val) ? ($val[$l] ?? '') : '' }}</textarea>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    @break
                                @case('textarea')
                                    <label class="form-label" for="{{ $id }}">{{ $f['label'] }}</label>
                                    <textarea id="{{ $id }}" name="{{ $name }}" rows="3" class="form-control">{{ is_scalar($val) ? $val : '' }}</textarea>
                                    @break
                                @case('number')
                                    <label class="form-label" for="{{ $id }}">{{ $f['label'] }}</label>
                                    <input id="{{ $id }}" type="number" step="{{ $f['step'] ?? '1' }}" name="{{ $name }}" value="{{ is_scalar($val) ? $val : '' }}" class="form-control" dir="ltr">
                                    @break
                                @case('datetime')
                                    <label class="form-label" for="{{ $id }}">{{ $f['label'] }}</label>
                                    <input id="{{ $id }}" type="datetime-local" name="{{ $name }}" value="{{ $val ? \Carbon\Carbon::parse($val)->format('Y-m-d\TH:i') : '' }}" class="form-control" dir="ltr">
                                    @break
                                @case('bool')
                                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" @checked($val)> {{ $f['label'] }}</label>
                                    @break
                                @case('select')
                                    <label class="form-label" for="{{ $id }}">{{ $f['label'] }}</label>
                                    <select id="{{ $id }}" name="{{ $name }}" class="form-control">
                                        <option value="">—</option>
                                        @foreach ($f['options'] as $o)<option value="{{ $o }}" @selected($val === $o)>{{ $o }}</option>@endforeach
                                    </select>
                                    @break
                                @case('multi')
                                    <span class="form-label">{{ $f['label'] }}</span>
                                    <div class="flex flex-wrap gap-3">@foreach ($f['options'] as $o)<label class="flex items-center gap-1 text-sm"><input type="checkbox" name="{{ $name }}[]" value="{{ $o }}" @checked(in_array($o, (array) $val, true))> {{ $o }}</label>@endforeach</div>
                                    @break
                                @case('list')
                                    <label class="form-label" for="{{ $id }}">{{ $f['label'] }} <span class="text-xs text-slate-400">({{ $t('list_hint') }})</span></label>
                                    <textarea id="{{ $id }}" name="{{ $name }}" rows="4" class="form-control" dir="{{ $f['dir'] ?? 'auto' }}">{{ implode("\n", (array) $val) }}</textarea>
                                    @break
                                @case('id-list')
                                    <label class="form-label" for="{{ $id }}">{{ $f['label'] }}</label>
                                    <input id="{{ $id }}" type="text" name="{{ $name }}" value="{{ implode(',', (array) $val) }}" class="form-control" dir="ltr" placeholder="1,2,3">
                                    @break
                                @case('json')
                                    <label class="form-label" for="{{ $id }}">{{ $f['label'] }} <span class="text-xs text-slate-400">({{ $t('json_hint') }})</span></label>
                                    <textarea id="{{ $id }}" name="{{ $name }}" rows="6" class="form-control font-mono text-xs" dir="ltr" placeholder="{{ $hint($f) }}">{{ $val ? json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '' }}</textarea>
                                    @break
                                @case('colors')
                                    <span class="form-label">{{ $f['label'] }}</span>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                        @foreach ($f['options'] as $tok)
                                            @php $cv = is_array($val) ? ($val[$tok] ?? '') : ''; @endphp
                                            <label class="flex items-center gap-2 text-xs" dir="ltr">
                                                <span class="inline-block size-6 shrink-0 rounded border border-slate-300" style="background: {{ \App\Support\Marketing\Contrast::normalizeHex($cv) ?? 'transparent' }}" data-swatch></span>
                                                <span class="w-28 shrink-0 truncate">{{ str_replace('color-', '', $tok) }}</span>
                                                <input type="text" name="{{ $name }}[{{ $tok }}]" value="{{ $cv }}" maxlength="7" placeholder="#rrggbb" class="form-control !py-1" data-color-input>
                                            </label>
                                        @endforeach
                                    </div>
                                    @break
                                @default
                                    <label class="form-label" for="{{ $id }}">{{ $f['label'] }}</label>
                                    <input id="{{ $id }}" type="text" name="{{ $name }}" value="{{ is_scalar($val) ? $val : '' }}" class="form-control" dir="{{ $f['dir'] ?? 'auto' }}">
                            @endswitch
                        </div>
                    @endforeach
                </fieldset>
                <x-input name="note" :label="$t('fields.note')" maxlength="500" />
                <div class="flex gap-2">
                    <x-button type="submit">{{ $t('btn.save') }}</x-button>
                    <a href="{{ route('marketer.suggestions.show', $s->id) }}" class="btn btn-secondary">{{ $t('btn.cancel') }}</a>
                </div>
            </form>
        </x-card>
    </div>
    <script>
    document.addEventListener('input', (e) => {
        if (!e.target.matches('[data-color-input]')) return;
        const v = e.target.value.trim();
        e.target.closest('label').querySelector('[data-swatch]').style.background = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.test(v) ? (v.startsWith('#') ? v : '#' + v) : 'transparent';
    });
    </script>
</x-layouts.marketer>
