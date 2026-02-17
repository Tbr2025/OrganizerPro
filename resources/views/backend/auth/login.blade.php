@extends('backend.auth.layouts.app')

@section('title')
    {{ __('Sign In') }} | {{ config('app.name') }}
@endsection

@section('admin-content')
<div class="w-full">
    <!-- Mobile Logo (shown on small screens) -->
    <div class="flex justify-center mb-6 lg:hidden">
        <img src="{{ config('settings.site_logo') ?? asset('images/logo/lara-dashboard.png') }}"
             alt="{{ config('app.name') }}" class="h-12 w-auto">
    </div>

    <!-- Welcome Section -->
    <div class="mb-6 sm:mb-8">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-brand-500 to-brand-600 flex items-center justify-center shadow-lg">
          <iconify-icon icon="lucide:shield-check" class="text-xl text-white"></iconify-icon>
        </div>
        <h1 class="font-bold text-gray-800 text-2xl sm:text-3xl dark:text-white">
          {{ __('Welcome Back') }}
        </h1>
      </div>
      <p class="text-gray-500 dark:text-gray-400">
        {{ __('Sign in to continue to your dashboard') }}
      </p>
    </div>

    <!-- Login Form -->
    <div>
      <form action="{{ route('admin.login.submit') }}" method="POST">
        @csrf
        <div class="space-y-5">
          <x-messages />

          <!-- Email Field -->
          <div>
            <label class="form-label flex items-center gap-2 mb-2">
              <iconify-icon icon="lucide:mail" class="text-gray-400"></iconify-icon>
              {{ __('Email Address') }}
            </label>
            <input autofocus type="text" id="email" name="email" autocomplete="username"
                   placeholder="{{ __('Enter your email') }}"
                   class="dark:bg-dark-900 h-12 w-full rounded-lg border border-gray-200 bg-gray-50/50 px-4 py-3 text-sm text-gray-700 transition-all duration-200 placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:outline-hidden focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-500 dark:focus:bg-gray-800"
                   value="{{ old('email') ?? config('app.demo_mode', false) ? 'superadmin@example.com' : '' }}" required>
          </div>

          <!-- Password Field -->
          <div>
            <label class="form-label flex items-center gap-2 mb-2">
              <iconify-icon icon="lucide:lock" class="text-gray-400"></iconify-icon>
              {{ __('Password') }}
            </label>
            <x-inputs.password
              name="password"
              label=""
              placeholder="{{ __('Enter your password') }}"
              value="{{ (config('app.demo_mode', false) ? '12345678' : '') }}"
              required
            />
          </div>

          <!-- Remember & Forgot -->
          <div class="flex items-center justify-between pt-1">
            <label for="remember" class="flex items-center justify-center gap-2 text-sm font-medium cursor-pointer has-checked:text-gray-900 dark:has-checked:text-white">
                <span class="relative flex items-center">
                    <input id="remember" name="remember" type="checkbox" class="before:content[''] peer relative size-5 appearance-none overflow-hidden rounded border-2 border-gray-300 bg-white before:absolute before:inset-0 checked:border-brand-500 checked:before:bg-brand-500 focus:outline-2 focus:outline-offset-2 focus:outline-brand-500 active:outline-offset-0 disabled:cursor-not-allowed transition-colors dark:border-gray-600 dark:bg-gray-800 dark:checked:border-brand-500 dark:checked:before:bg-brand-500" checked/>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" stroke="currentColor" fill="none" stroke-width="4" class="pointer-events-none invisible absolute left-1/2 top-1/2 size-3 -translate-x-1/2 -translate-y-1/2 text-white peer-checked:visible">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                </span>
                <span class="text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
            </label>
            <a href="{{ route('admin.password.request') }}" class="text-sm font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400 transition-colors hover:underline">
              {{ __('Forgot password?') }}
            </a>
          </div>

          <!-- Sign In Button -->
          <div class="pt-2">
            <button type="submit" class="btn-primary w-full h-12 text-base font-semibold shadow-lg shadow-brand-500/30 hover:shadow-xl hover:shadow-brand-500/40 transition-all duration-200">
              {{ __('Sign In') }}
              <iconify-icon icon="lucide:arrow-right" class="ml-2 text-lg"></iconify-icon>
            </button>
          </div>

          <!-- Divider -->
          @if (config('app.demo_mode', false))
          <div class="relative my-6">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-4 text-gray-400 bg-white dark:bg-gray-900">{{ __('Demo Access') }}</span>
            </div>
          </div>

          <!-- Demo Credentials Card -->
          <div x-data="{ showDemoCredentials: true }" class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-gradient-to-br from-gray-50 to-gray-100/50 dark:from-gray-800/50 dark:to-gray-800/30">
            <button
              type="button"
              @click="showDemoCredentials = !showDemoCredentials"
              class="flex justify-between items-center w-full px-4 py-3 text-sm font-medium text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100/50 dark:hover:bg-gray-700/50 transition-colors"
            >
              <span class="flex items-center gap-2">
                <iconify-icon icon="lucide:key" class="text-brand-500"></iconify-icon>
                {{ __('Demo Credentials') }}
              </span>
              <iconify-icon :icon="showDemoCredentials ? 'lucide:chevron-up' : 'lucide:chevron-down'" class="text-gray-400"></iconify-icon>
            </button>

            <div x-show="showDemoCredentials" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-4 py-4 border-t border-gray-200 dark:border-gray-700 bg-white/50 dark:bg-gray-900/50">
              <div class="space-y-3 mb-4">
                <div class="flex items-center gap-3 p-2 rounded-lg bg-gray-100/80 dark:bg-gray-800/80">
                  <iconify-icon icon="lucide:mail" class="text-gray-400"></iconify-icon>
                  <code class="text-sm font-mono text-gray-700 dark:text-gray-300">superadmin@example.com</code>
                </div>
                <div class="flex items-center gap-3 p-2 rounded-lg bg-gray-100/80 dark:bg-gray-800/80">
                  <iconify-icon icon="lucide:lock" class="text-gray-400"></iconify-icon>
                  <code class="text-sm font-mono text-gray-700 dark:text-gray-300">12345678</code>
                </div>
              </div>
              <button
                type="button"
                id="fill-demo-credentials"
                class="w-full btn-default h-10 text-sm font-medium"
              >
                <iconify-icon icon="lucide:zap" class="mr-2 text-yellow-500"></iconify-icon>
                {{ __('Quick Login with Demo') }}
              </button>
            </div>
          </div>
          @endif
        </div>
      </form>
    </div>

    <!-- Footer -->
    <div class="mt-8 pt-6 border-t border-gray-100 dark:border-gray-800">
      <p class="text-center text-sm text-gray-400">
        {{ config('app.name') }} &copy; {{ date('Y') }}
      </p>
    </div>
</div>
@endsection

@if (config('app.demo_mode', false))
    @push('scripts')
        <script>
            document.getElementById('fill-demo-credentials').addEventListener('click', function() {
              console.log('clicked');
                document.getElementById('email').value = 'superadmin@example.com';
                document.querySelector('input[name="password"]').value = '12345678';
                document.querySelector('form').submit();
            });
        </script>
    @endpush
@endif
