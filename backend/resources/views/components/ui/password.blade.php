{{-- Password input with show / hide toggle (ui.js [data-toggle-password]). Never echoes a value back. --}}
@props(['name' => 'password', 'label' => null, 'required' => false, 'help' => null, 'error' => null])
<x-ui.field :name="$name" :label="$label" :help="$help" :error="$error" :required="$required" {{ $attributes->only('class') }}>
    <div class="relative">
        <input id="f-{{ $name }}" type="password" name="{{ $name }}" @required($required) {{ $attributes->except('class')->merge(['class' => 'form-control !pe-12'.($error ? ' is-invalid' : '')]) }}>
        <button type="button" data-toggle-password="f-{{ $name }}" class="absolute end-1 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-lg text-panel-text-subtle hover:text-panel-text" aria-label="{{ __('ui.show_password') }}" data-label-show="{{ __('ui.show_password') }}" data-label-hide="{{ __('ui.hide_password') }}">
            <x-ui.icon name="eye" data-eye-on class="size-[18px]" /><x-ui.icon name="eye-off" data-eye-off class="hidden size-[18px]" />
        </button>
    </div>
</x-ui.field>
