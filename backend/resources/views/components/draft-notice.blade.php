{{-- Shown at the top of a catalog form while the record is a draft. --}}
@props(['model'])
@if ($model->exists && $model->is_draft)
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-panel-warning-solid/30 bg-panel-warning-bg px-4 py-3 text-sm text-panel-warning-fg']) }}>
        <strong>{{ __('admin.drafts.badge') }}</strong> — {{ __('admin.drafts.notice') }}
    </div>
@endif
