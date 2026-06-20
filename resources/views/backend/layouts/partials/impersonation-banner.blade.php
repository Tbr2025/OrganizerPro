@if (session()->has('original_user_id'))
    @php
        $originalUser = \App\Models\User::find(session('original_user_id'));
    @endphp
    @if ($originalUser)
        {{-- Layout-agnostic inline styles so this works in both the admin (Tailwind)
             and frontend (Bootstrap) layouts, and for any impersonated role. --}}
        <div style="position:sticky;top:0;z-index:1050;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;background:#f59e0b;color:#451a03;padding:8px 16px;font-size:14px;box-shadow:0 1px 4px rgba(0,0,0,.2);font-family:inherit;">
            <span style="min-width:0;">
                ⚠️ {{ __('You are') }} <strong>{{ $originalUser->name }}</strong>@if ($originalUser->hasRole('Superadmin')) ({{ __('Superadmin') }})@endif
                — {{ __('logged in as') }} <strong>{{ auth()->user()->name }}</strong>
            </span>
            <span style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                {{-- Exit impersonation: return to the original (superadmin) account.
                     GET link so it can never fail CSRF and works for any role. --}}
                <a href="{{ route('admin.users.switch-back') }}"
                    style="cursor:pointer;border:none;border-radius:6px;background:rgba(69,26,3,.12);color:#451a03;padding:6px 12px;font-weight:600;font-size:13px;text-decoration:none;display:inline-block;">
                    ← {{ __('Exit') }}
                </a>
                {{-- Full logout: end the session entirely --}}
                <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                    @csrf
                    <button type="submit"
                        style="cursor:pointer;border:none;border-radius:6px;background:#451a03;color:#fff;padding:6px 12px;font-weight:600;font-size:13px;">
                        {{ __('Logout') }}
                    </button>
                </form>
            </span>
        </div>
    @endif
@endif
