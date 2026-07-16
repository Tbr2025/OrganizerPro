@if (session()->has('original_user_id'))
    @php
        $originalUser = \App\Models\User::find(session('original_user_id'));
    @endphp
    @if ($originalUser)
        {{-- Floating impersonation bubble — works in both admin (Tailwind) and frontend (Bootstrap) layouts --}}
        <div id="impersonation-bubble" style="position:fixed;bottom:20px;right:20px;z-index:1050;font-family:inherit;font-size:14px;">
            {{-- Expanded popover --}}
            <div id="impersonation-popover" style="display:none;position:absolute;bottom:60px;right:0;width:240px;background:#fffbeb;border:1px solid #f59e0b;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.18);padding:14px 16px;color:#451a03;">
                <div style="margin-bottom:10px;line-height:1.4;">
                    {{ __('Logged in as') }}<br>
                    <strong>{{ auth()->user()->name }}</strong>
                </div>
                <div style="display:flex;gap:8px;">
                    <a href="{{ route('admin.users.switch-back') }}"
                       style="flex:1;text-align:center;cursor:pointer;border:none;border-radius:6px;background:rgba(69,26,3,.12);color:#451a03;padding:7px 0;font-weight:600;font-size:13px;text-decoration:none;display:block;">
                        ← {{ __('Exit') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}" style="margin:0;flex:1;">
                        @csrf
                        <button type="submit"
                                style="width:100%;cursor:pointer;border:none;border-radius:6px;background:#451a03;color:#fff;padding:7px 0;font-weight:600;font-size:13px;">
                            {{ __('Logout') }}
                        </button>
                    </form>
                </div>
                {{-- Small arrow/triangle pointing down to bubble --}}
                <div style="position:absolute;bottom:-6px;right:16px;width:12px;height:12px;background:#fffbeb;border-right:1px solid #f59e0b;border-bottom:1px solid #f59e0b;transform:rotate(45deg);"></div>
            </div>

            {{-- Bubble button --}}
            <button id="impersonation-toggle" type="button"
                    style="width:48px;height:48px;border-radius:50%;border:none;background:#f59e0b;color:#451a03;cursor:pointer;box-shadow:0 2px 10px rgba(0,0,0,.2);display:flex;align-items:center;justify-content:center;animation:imp-pulse 2s ease-in-out infinite;">
                {{-- User-switch SVG icon --}}
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="8.5" cy="7" r="4"/>
                    <polyline points="17 11 19 13 23 9"/>
                </svg>
            </button>
        </div>

        <style>
            @keyframes imp-pulse {
                0%, 100% { box-shadow: 0 2px 10px rgba(0,0,0,.2); }
                50% { box-shadow: 0 2px 10px rgba(245,158,11,.5), 0 0 0 6px rgba(245,158,11,.15); }
            }
        </style>

        <script>
            (function() {
                var toggle = document.getElementById('impersonation-toggle');
                var popover = document.getElementById('impersonation-popover');
                var open = false;

                function show() { popover.style.display = 'block'; open = true; toggle.style.animation = 'none'; }
                function hide() { popover.style.display = 'none'; open = false; toggle.style.animation = 'imp-pulse 2s ease-in-out infinite'; }

                toggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    open ? hide() : show();
                });

                document.addEventListener('click', function(e) {
                    if (open && !document.getElementById('impersonation-bubble').contains(e.target)) {
                        hide();
                    }
                });
            })();
        </script>
    @endif
@endif
