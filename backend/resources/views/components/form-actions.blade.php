@props(['cancel'])
<div class="flex items-center gap-2 border-t border-slate-100 pt-4">
    <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
    <a href="{{ $cancel }}" class="btn btn-secondary">{{ __('admin.cancel') }}</a>
</div>
