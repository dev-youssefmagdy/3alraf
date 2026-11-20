{{-- Radio group: options = [value => label]. --}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'inline' => false, 'help' => null])
<fieldset data-field {{ $attributes->only('class') }}>
    @if ($label)<legend class="form-label">{{ $label }}</legend>@endif
    <div class="{{ $inline ? 'flex flex-wrap gap-x-6' : 'flex flex-col' }}">@foreach ($options as $k => $v)<x-ui.radio :name="$name" :value="$k" :label="$v" :checked="(string) $value === (string) $k" />@endforeach</div>
    @if ($help)<p class="field-help">{{ $help }}</p>@endif
</fieldset>
