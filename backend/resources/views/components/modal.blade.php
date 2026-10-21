@props(['id', 'title' => null])
<div id="{{ $id }}" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
    <div {{ $attributes->merge(['class' => 'w-full max-w-lg rounded-xl bg-white shadow-xl']) }}>
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h3 class="font-semibold text-slate-800">{{ $title }}</h3>
            <button type="button" data-modal-close class="text-slate-400 hover:text-slate-700" aria-label="Close">&times;</button>
        </div>
        <div class="p-5">{{ $slot }}</div>
        @isset($footer)<div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">{{ $footer }}</div>@endisset
    </div>
</div>
