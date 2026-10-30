<x-layouts.admin :title="__('whatsapp.chat.title')" :guard="$guard">
    <div id="wa-chat" class="relative flex h-[calc(100dvh-6.5rem)] min-h-[28rem] overflow-hidden rounded-xl border border-slate-200 bg-white"
        data-base="{{ $base }}" data-channel="{{ $channel }}" data-connected="{{ $connected ? 1 : 0 }}" data-me="{{ $meId }}" data-guard="{{ $guard }}"
        data-can-send="{{ $can['send'] ? 1 : 0 }}" data-can-manage="{{ $can['manage'] ? 1 : 0 }}" data-can-media="{{ $can['media'] ? 1 : 0 }}"
        data-connection-url="{{ $can['connection'] ? route("$guard.whatsapp.index") : '' }}" data-customer-url="{{ $customerUrl }}"
        data-ws-key="{{ config('broadcasting.default') === 'reverb' ? config('broadcasting.connections.reverb.key') : '' }}"
        data-ws-host="{{ config('broadcasting.connections.reverb.options.host') }}" data-ws-port="{{ config('broadcasting.connections.reverb.options.port') }}"
        data-ws-scheme="{{ config('broadcasting.connections.reverb.options.scheme') }}" data-auth-url="{{ route("$guard.broadcasting.auth") }}"
        data-admins='@json($admins)' data-i18n='@json(__('whatsapp.chat.ui'))' data-locale="{{ app()->getLocale() }}">

        {{-- conversation list --}}
        <section id="wa-list" class="flex w-full shrink-0 flex-col border-e border-slate-200 md:w-80 lg:w-96">
            <div class="space-y-2 border-b border-slate-100 p-3">
                <div class="flex items-center gap-2">
                    <h2 class="flex-1 truncate text-base font-semibold text-slate-800">{{ __('whatsapp.chat.title') }} <span id="wa-unread-total" class="ms-1 hidden rounded-full bg-emerald-600 px-2 py-0.5 text-xs font-bold text-white"></span></h2>
                    @if ($can['send'])
                        <button type="button" id="wa-new" class="btn btn-primary !px-3 !py-1.5 text-sm">{{ __('whatsapp.chat.ui.new_chat') }}</button>
                    @endif
                </div>
                <input id="wa-search" type="search" class="form-control" placeholder="{{ __('whatsapp.chat.ui.search') }}" autocomplete="off" dir="auto">
                <div id="wa-filters" class="flex flex-wrap gap-1.5 text-xs">
                    @foreach (['all', 'unread', 'mine', 'archived'] as $f)
                        <button type="button" data-filter="{{ $f }}" class="rounded-full border border-slate-200 px-3 py-1 font-medium text-slate-600 hover:bg-slate-50 [&.on]:border-emerald-600 [&.on]:bg-emerald-50 [&.on]:text-emerald-700">{{ __('whatsapp.chat.ui.'.$f) }}</button>
                    @endforeach
                </div>
            </div>
            <div id="wa-offline" class="hidden bg-amber-50 px-3 py-1.5 text-xs text-amber-700">{{ __('whatsapp.chat.ui.offline') }}</div>
            <div id="wa-disc" class="{{ $connected ? 'hidden' : '' }} border-b border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <strong>{{ __('whatsapp.chat.ui.not_connected_title') }}</strong>
                @if ($can['connection'])<a class="ms-1 underline" href="{{ route("$guard.whatsapp.index") }}">{{ __('whatsapp.chat.ui.go_connect') }}</a>@endif
            </div>
            <ul id="wa-convs" class="min-h-0 flex-1 divide-y divide-slate-50 overflow-y-auto"></ul>
            <p id="wa-convs-empty" class="hidden p-6 text-center text-sm text-slate-500"></p>
        </section>

        {{-- thread --}}
        <section id="wa-thread" class="hidden min-w-0 flex-1 flex-col bg-[#efeae2] md:flex">
            <div id="wa-placeholder" class="grid flex-1 place-items-center p-8 text-center">
                <div>
                    <div class="mx-auto mb-4 grid size-20 place-items-center rounded-full bg-white/70 text-emerald-600">
                        <svg class="size-10" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21l1.7-5A8.5 8.5 0 1112 20.5a8.4 8.4 0 01-4-1z"/></svg>
                    </div>
                    <p id="wa-placeholder-text" class="text-sm text-slate-600">{{ $connected ? __('whatsapp.chat.ui.select_chat') : __('whatsapp.chat.ui.not_connected_help') }}</p>
                    @if (! $connected && $can['connection'])
                        <a id="wa-placeholder-link" class="btn btn-primary mt-4 inline-block" href="{{ route("$guard.whatsapp.index") }}">{{ __('whatsapp.chat.ui.go_connect') }}</a>
                    @endif
                </div>
            </div>
            <div id="wa-active" class="hidden min-h-0 flex-1 flex-col">
                <header class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <button type="button" id="wa-back" class="md:hidden" aria-label="{{ __('whatsapp.chat.ui.back') }}"><svg class="size-6 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
                    <div id="wa-h-avatar" class="grid size-10 shrink-0 place-items-center rounded-full bg-emerald-100 font-bold text-emerald-700"></div>
                    <div class="min-w-0 flex-1">
                        <div id="wa-h-name" class="truncate font-semibold text-slate-800" dir="auto"></div>
                        <div class="flex flex-wrap items-center gap-x-2 text-xs text-slate-500"><span id="wa-h-sub" dir="ltr" class="truncate"></span><a id="wa-h-customer" class="hidden rounded bg-indigo-50 px-1.5 py-0.5 font-medium text-indigo-700"></a><span id="wa-h-assigned" class="hidden"></span></div>
                    </div>
                    @if ($can['manage'])
                        <button type="button" id="wa-pin" class="btn btn-secondary !px-2.5 !py-1.5 text-xs"></button>
                        <button type="button" id="wa-archive" class="btn btn-secondary !px-2.5 !py-1.5 text-xs"></button>
                        <select id="wa-assign" class="form-control !w-auto !py-1.5 text-xs" aria-label="{{ __('whatsapp.chat.ui.assign') }}"></select>
                        <button type="button" id="wa-delchat" class="btn btn-danger !px-2.5 !py-1.5 text-xs" title="{{ __('whatsapp.chat.ui.delete_chat') }}">&times;</button>
                    @endif
                </header>
                <div id="wa-msgs" class="relative flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-3 py-3 md:px-6"></div>
                <div id="wa-typing" class="hidden bg-transparent px-6 pb-1 text-xs italic text-emerald-700"></div>
                <div id="wa-dropzone" class="pointer-events-none absolute inset-0 z-20 hidden place-items-center bg-emerald-600/20 text-lg font-semibold text-emerald-800">{{ __('whatsapp.chat.ui.drop_files') }}</div>
                @if ($can['send'])
                    <footer id="wa-composer" class="border-t border-slate-200 bg-slate-50 p-2">
                        <div id="wa-reply" class="mb-2 hidden items-start gap-2 rounded-lg border-s-4 border-emerald-500 bg-white p-2 text-xs"></div>
                        <div id="wa-tray" class="mb-2 hidden flex-wrap gap-2"></div>
                        <div id="wa-recbar" class="hidden items-center gap-3 px-2 py-1.5 text-sm">
                            <button type="button" id="wa-rec-cancel" class="text-red-600" aria-label="{{ __('whatsapp.chat.ui.cancel') }}">&times;</button>
                            <span class="size-2.5 animate-pulse rounded-full bg-red-500"></span><span id="wa-rec-time" dir="ltr">0:00</span>
                            <span class="flex-1 text-slate-500">{{ __('whatsapp.chat.ui.recording') }}</span>
                            <button type="button" id="wa-rec-send" class="btn btn-primary !px-3 !py-1.5">{{ __('whatsapp.chat.ui.stop_send') }}</button>
                        </div>
                        <div id="wa-compose-row" class="relative flex items-end gap-1.5">
                            <button type="button" id="wa-emoji-btn" class="rounded-full p-2 text-slate-500 hover:bg-slate-200" title="{{ __('whatsapp.chat.ui.emoji') }}" aria-label="{{ __('whatsapp.chat.ui.emoji') }}">&#128578;</button>
                            <button type="button" id="wa-attach-btn" class="rounded-full p-2 text-slate-500 hover:bg-slate-200" title="{{ __('whatsapp.chat.ui.attach') }}" aria-label="{{ __('whatsapp.chat.ui.attach') }}"><svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11l-8.6 8.6a5 5 0 01-7-7L14 4a3.3 3.3 0 014.7 4.7L10 17.4a1.7 1.7 0 01-2.4-2.4L15 7.7"/></svg></button>
                            <div id="wa-emoji-pop" class="absolute bottom-12 start-0 z-30 hidden max-h-56 w-72 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 shadow-lg"></div>
                            <div id="wa-attach-pop" class="absolute bottom-12 start-10 z-30 hidden w-48 rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
                                <button type="button" data-attach="media" class="block w-full px-3 py-2 text-start hover:bg-slate-50">{{ __('whatsapp.chat.ui.photo_video') }}</button>
                                <button type="button" data-attach="document" class="block w-full px-3 py-2 text-start hover:bg-slate-50">{{ __('whatsapp.chat.ui.document') }}</button>
                                <button type="button" data-attach="location" class="block w-full px-3 py-2 text-start hover:bg-slate-50">{{ __('whatsapp.chat.ui.location') }}</button>
                                <button type="button" data-attach="contact" class="block w-full px-3 py-2 text-start hover:bg-slate-50">{{ __('whatsapp.chat.ui.contact') }}</button>
                            </div>
                            <input type="file" id="wa-file-media" class="hidden" multiple accept="image/*,video/*"><input type="file" id="wa-file-doc" class="hidden" multiple>
                            <textarea id="wa-input" rows="1" dir="auto" maxlength="4096" class="form-control max-h-32 min-h-10 flex-1 resize-none" placeholder="{{ __('whatsapp.chat.ui.type_message') }}"></textarea>
                            <button type="button" id="wa-mic" class="rounded-full p-2 text-slate-500 hover:bg-slate-200" title="{{ __('whatsapp.chat.ui.record') }}" aria-label="{{ __('whatsapp.chat.ui.record') }}"><svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3a3 3 0 00-3 3v6a3 3 0 006 0V6a3 3 0 00-3-3zM5 11a7 7 0 0014 0M12 18v3"/></svg></button>
                            <button type="button" id="wa-send" class="rounded-full bg-emerald-600 p-2.5 text-white hover:bg-emerald-700 disabled:opacity-50" title="{{ __('whatsapp.chat.ui.send') }}" aria-label="{{ __('whatsapp.chat.ui.send') }}"><svg class="size-5 rtl:-scale-x-100" fill="currentColor" viewBox="0 0 24 24"><path d="M3 20l18-8L3 4v6l11 2-11 2z"/></svg></button>
                        </div>
                    </footer>
                @endif
            </div>
        </section>

        {{-- dialogs (filled/handled by JS) --}}
        <div id="wa-dialog" class="absolute inset-0 z-40 hidden items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
                <h3 id="wa-dialog-title" class="mb-3 font-semibold text-slate-800"></h3>
                <form id="wa-dialog-form" class="space-y-3" novalidate>
                    <div id="wa-dialog-body" class="space-y-3"></div>
                    <p id="wa-dialog-err" class="hidden text-xs text-red-600"></p>
                    <div class="flex justify-end gap-2 pt-1"><button type="button" id="wa-dialog-cancel" class="btn">{{ __('whatsapp.chat.ui.cancel') }}</button><button type="submit" id="wa-dialog-ok" class="btn btn-primary"></button></div>
                </form>
            </div>
        </div>
        <div id="wa-lightbox" class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/90 p-4"></div>
    </div>
</x-layouts.admin>
