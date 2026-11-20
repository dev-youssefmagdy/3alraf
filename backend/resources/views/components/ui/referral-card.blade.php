{{-- ReferralCard (Figma ReferralCard, affiliate): referral code + link with Copy and WhatsApp share as the top actions (copy: [data-copy], ui.js). --}}
@props(['code' => null, 'url', 'title' => null])
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-panel-border bg-panel-surface p-5 shadow-panel-xs']) }}>
    <h2 class="text-panel-h4 font-bold text-panel-text">{{ $title ?? __('ui.referral.title') }}</h2>
    @if ($code)<p class="mt-3 inline-flex rounded-xl bg-panel-accent-soft px-3 py-1.5 text-lg font-bold tracking-wide text-panel-accent-text" dir="ltr">{{ $code }}</p>@endif
    <p class="mt-3 break-all rounded-xl bg-panel-surface-sunken px-3 py-2 text-sm text-panel-text-muted" dir="ltr">{{ $url }}</p>
    <div class="mt-3 flex flex-wrap gap-2">
        <button type="button" data-copy="{{ $url }}" data-copied="{{ __('ui.copied') }}" class="btn btn-primary btn-md"><x-ui.icon name="copy" class="size-[18px]" /><span data-copy-label>{{ __('ui.copy_link') }}</span></button>
        <a href="https://wa.me/?text={{ rawurlencode($url) }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-md"><x-ui.icon name="whatsapp" class="size-[18px]" />{{ __('ui.share_whatsapp') }}</a>
    </div>
</div>
