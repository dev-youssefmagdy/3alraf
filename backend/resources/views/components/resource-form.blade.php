{{-- Admin form shell: layout + card + AJAX form (validate -> submit). Slot = fields. --}}
@props(['route', 'editing' => false, 'model' => null, 'title' => null, 'width' => 'max-w-2xl', 'back' => null])
<x-layouts.admin guard="tenant" :title="$title ?? ($editing ? __('admin.edit') : __('admin.add_new'))">
    <x-card :class="$width">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif
            data-validate-url="{{ route($route.'.validate', $editing ? [$model->id] : []) }}"
            action="{{ $editing ? route($route.'.update', $model->id) : route($route.'.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4" novalidate
            {{ $attributes }}>
            @csrf
            {{ $slot }}
            <x-form-actions :cancel="$back ?? route($route.'.index')" />
        </form>
    </x-card>
</x-layouts.admin>
