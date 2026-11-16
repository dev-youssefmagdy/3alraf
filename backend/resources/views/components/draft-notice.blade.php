{{-- Shown at the top of a catalog form while the record is a draft. --}}
@props(['model'])
@if ($model->exists && $model->is_draft)
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800']) }}>
        <strong>{{ __('admin.drafts.badge') }}</strong> — {{ __('admin.drafts.notice') }}
    </div>
@endif
