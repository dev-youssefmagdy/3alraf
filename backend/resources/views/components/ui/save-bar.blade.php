{{-- Save bar (Figma Form actions): sticky bottom bar with Save / draft / Cancel and an "unsaved changes" hint. Slots: default = extra actions. Same behaviour as the
     old <x-form-actions> ($draftable adds "Save as draft"). Mobile: two full-width stacked buttons. --}}
@props(['cancel', 'draftable' => null, 'dirty' => true])
@php($canDraft = $draftable && (! $draftable->exists || $draftable->is_draft))
<div data-save-bar class="sticky bottom-0 z-20 col-span-full -mx-4 mt-6 border-t border-panel-border bg-panel-surface/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6 max-md:bottom-[calc(var(--size-bottom-nav)+env(safe-area-inset-bottom))]">
    <div class="mx-auto flex max-w-[var(--size-content-max)] flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p class="hidden items-center gap-2 text-panel-small text-panel-text-muted sm:flex" data-unsaved hidden><span class="size-2 rounded-full bg-panel-warning-solid"></span>{{ __('ui.unsaved_changes') }}</p>
        <div class="flex flex-col gap-2 sm:ms-auto sm:flex-row sm:items-center">
            <a href="{{ $cancel }}" class="btn btn-secondary btn-md max-sm:w-full">{{ __('admin.cancel') }}</a>
            @if ($canDraft)<button type="submit" name="save_as_draft" value="1" formnovalidate class="btn btn-secondary btn-md max-sm:w-full">{{ __('admin.drafts.save') }}</button>@endif
            {{ $slot }}
            <button type="submit" {{ $attributes->only('data-action')->merge(['class' => 'btn btn-primary btn-md max-sm:w-full']) }}>{{ $draftable?->is_draft ? __('admin.drafts.publish') : __('admin.save') }}</button>
        </div>
    </div>
    @if ($canDraft)<p class="mt-2 text-xs text-panel-text-subtle">{{ __('admin.drafts.hint') }}</p>@endif
</div>
