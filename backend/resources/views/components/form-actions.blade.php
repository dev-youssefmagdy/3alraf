{{-- $draftable: a catalog model using HasDraftState. Adds "Save as draft" while the record is new or still a draft (a published record is hidden with "active" instead). --}}
@props(['cancel', 'draftable' => null])
@php($canDraft = $draftable && (! $draftable->exists || $draftable->is_draft))
<div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
    <button type="submit" class="btn btn-primary">{{ $draftable?->is_draft ? __('admin.drafts.publish') : __('admin.save') }}</button>
    @if ($canDraft)
        <button type="submit" name="save_as_draft" value="1" formnovalidate class="btn btn-secondary">{{ __('admin.drafts.save') }}</button>
    @endif
    <a href="{{ $cancel }}" class="btn btn-secondary">{{ __('admin.cancel') }}</a>
    @if ($canDraft)<p class="w-full text-xs text-slate-500">{{ __('admin.drafts.hint') }}</p>@endif
</div>
