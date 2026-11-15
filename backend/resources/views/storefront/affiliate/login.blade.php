<x-layouts.storefront :title="__('growth.affiliate_login')" :noindex="true">
    {{-- Affiliate login (design 30:11013 / 30:11208): centred 480 card inside the storefront layout (x-auth-shell is the full-screen
         customer shell without header / footer, so it is not used here): violet sparkle + H1, email, password, remember, Login, join link. --}}
    <div class="mx-auto max-w-[480px] rounded-[32px] border border-slate-200 bg-white p-5 shadow-card sm:p-8 lg:my-6">
        <div class="mb-5 flex items-center gap-3">
            <span class="grid size-12 shrink-0 place-items-center rounded-full bg-violet-500 text-sun-400" aria-hidden="true">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg>
            </span>
            <h1 class="min-w-0 font-display text-[28px]/9 font-extrabold text-slate-950">{{ __('growth.affiliate_login') }}</h1>
        </div>
        <form data-ajax-form data-validate-url="{{ route('storefront.affiliate.login.validate') }}" action="{{ route('storefront.affiliate.login.submit') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <x-input name="email" type="email" :label="__('admin.fields.email')" required autocomplete="username" class="space-y-1" />
            <x-input name="password" type="password" :label="__('admin.fields.password')" required autocomplete="current-password" class="space-y-1" />
            <label class="flex min-h-11 items-center gap-3 text-sm font-semibold text-slate-950">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-[22px] rounded-md border-slate-300 accent-brand-600">
                {{ __('admin.remember_me') }}
            </label>
            <button type="submit" class="btn btn-primary btn-lg w-full">{{ __('admin.login') }}</button>
        </form>
        <p class="mt-5 text-center text-sm text-slate-600">{{ __('growth.no_account') }} <a class="link !text-violet-700" href="{{ route('storefront.affiliate.register') }}">{{ __('growth.join_program') }}</a></p>
    </div>
</x-layouts.storefront>
