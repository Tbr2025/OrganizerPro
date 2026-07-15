<div class="rounded-md border border-gray-200 dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="px-5 py-4 sm:px-6 sm:py-5">
        <h3 class="text-base font-medium text-gray-700 dark:text-white/90">
            {{ __('Email Configuration') }}
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {{ __('Configure your mail provider settings. Changes will update both the database and the .env file.') }}
        </p>
    </div>
    <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">

        {{-- Provider Preset Selector --}}
        <div class="flex">
            <div class="md:basis-1/2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Mail Provider Preset') }}
                </label>
                <select id="mail_provider_preset" class="form-control">
                    <option value="">{{ __('-- Select a preset --') }}</option>
                    <option value="aws_ses">{{ __('AWS SES (SMTP)') }}</option>
                    <option value="hostinger">{{ __('Hostinger SMTP') }}</option>
                    <option value="custom">{{ __('Custom SMTP') }}</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">{{ __('Selecting a preset will auto-fill the host, port, and encryption fields.') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            {{-- MAIL_HOST --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('SMTP Host') }}
                </label>
                <input type="text" name="mail_host" id="mail_host" class="form-control"
                    placeholder="smtp.example.com"
                    value="{{ config('settings.mail_host', config('mail.mailers.smtp.host', '')) }}">
            </div>

            {{-- MAIL_PORT --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('SMTP Port') }}
                </label>
                <input type="text" name="mail_port" id="mail_port" class="form-control"
                    placeholder="587"
                    value="{{ config('settings.mail_port', config('mail.mailers.smtp.port', '')) }}">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            {{-- MAIL_USERNAME --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('SMTP Username') }}
                </label>
                <input type="text" name="mail_username" id="mail_username" class="form-control"
                    placeholder="{{ __('Username') }}"
                    value="{{ config('settings.mail_username', config('mail.mailers.smtp.username', '')) }}">
            </div>

            {{-- MAIL_PASSWORD --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('SMTP Password') }}
                </label>
                <input type="password" name="mail_password" id="mail_password" class="form-control"
                    placeholder="{{ __('Password') }}"
                    value="{{ config('settings.mail_password', config('mail.mailers.smtp.password', '')) }}">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            {{-- MAIL_ENCRYPTION --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Encryption') }}
                </label>
                <select name="mail_encryption" id="mail_encryption" class="form-control">
                    @php $currentEncryption = config('settings.mail_encryption', config('mail.mailers.smtp.encryption', 'tls')); @endphp
                    <option value="tls" {{ $currentEncryption === 'tls' ? 'selected' : '' }}>TLS</option>
                    <option value="ssl" {{ $currentEncryption === 'ssl' ? 'selected' : '' }}>SSL</option>
                    <option value="" {{ $currentEncryption === '' || $currentEncryption === null ? 'selected' : '' }}>{{ __('None') }}</option>
                </select>
            </div>

            {{-- MAIL_MAILER --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Mail Mailer') }}
                </label>
                <input type="text" name="mail_mailer" id="mail_mailer" class="form-control"
                    placeholder="smtp"
                    value="{{ config('settings.mail_mailer', config('mail.default', 'smtp')) }}">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            {{-- MAIL_FROM_ADDRESS --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('From Address') }}
                </label>
                <input type="email" name="mail_from_address" id="mail_from_address" class="form-control"
                    placeholder="noreply@example.com"
                    value="{{ config('settings.mail_from_address', config('mail.from.address', '')) }}">
            </div>

            {{-- MAIL_FROM_NAME --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('From Name') }}
                </label>
                <input type="text" name="mail_from_name" id="mail_from_name" class="form-control"
                    placeholder="{{ config('app.name') }}"
                    value="{{ config('settings.mail_from_name', config('mail.from.name', config('app.name'))) }}">
            </div>
        </div>

        {{-- Send Test Email --}}
        <div class="border-t border-gray-100 dark:border-gray-800 pt-6">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Send Test Email') }}</h4>
            <div class="flex items-end gap-3">
                <div class="flex-1 md:basis-1/3">
                    <input type="email" id="test_email_address" class="form-control"
                        placeholder="{{ __('recipient@example.com') }}"
                        value="{{ auth()->user()->email }}">
                </div>
                <button type="button" id="send_test_email_btn"
                    class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    {{ __('Send Test Email') }}
                </button>
            </div>
            <div id="test_email_result" class="mt-3 hidden">
                <div id="test_email_success" class="hidden p-3 text-sm text-green-700 bg-green-100 rounded-md dark:bg-green-900/30 dark:text-green-400"></div>
                <div id="test_email_error" class="hidden p-3 text-sm text-red-700 bg-red-100 rounded-md dark:bg-red-900/30 dark:text-red-400"></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const presets = {
        'aws_ses': {
            host: 'email-smtp.ap-south-1.amazonaws.com',
            port: '587',
            encryption: 'tls',
            mailer: 'smtp'
        },
        'hostinger': {
            host: 'smtp.hostinger.com',
            port: '465',
            encryption: 'ssl',
            mailer: 'smtp'
        },
        'custom': {
            host: '',
            port: '',
            encryption: '',
            mailer: 'smtp'
        }
    };

    const presetSelect = document.getElementById('mail_provider_preset');
    if (presetSelect) {
        presetSelect.addEventListener('change', function () {
            const preset = presets[this.value];
            if (!preset) return;

            document.getElementById('mail_host').value = preset.host;
            document.getElementById('mail_port').value = preset.port;
            document.getElementById('mail_mailer').value = preset.mailer;

            const encSelect = document.getElementById('mail_encryption');
            for (let i = 0; i < encSelect.options.length; i++) {
                if (encSelect.options[i].value === preset.encryption) {
                    encSelect.selectedIndex = i;
                    break;
                }
            }
        });
    }

    // Send Test Email
    const testBtn = document.getElementById('send_test_email_btn');
    if (testBtn) {
        testBtn.addEventListener('click', function () {
            const email = document.getElementById('test_email_address').value;
            if (!email) {
                alert('Please enter a recipient email address.');
                return;
            }

            const resultDiv = document.getElementById('test_email_result');
            const successDiv = document.getElementById('test_email_success');
            const errorDiv = document.getElementById('test_email_error');

            resultDiv.classList.remove('hidden');
            successDiv.classList.add('hidden');
            errorDiv.classList.add('hidden');

            testBtn.disabled = true;
            testBtn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Sending...';

            fetch('{{ route("admin.settings.test-email") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    test_email: email,
                    mail_host: document.getElementById('mail_host').value,
                    mail_port: document.getElementById('mail_port').value,
                    mail_username: document.getElementById('mail_username').value,
                    mail_password: document.getElementById('mail_password').value,
                    mail_encryption: document.getElementById('mail_encryption').value,
                    mail_from_address: document.getElementById('mail_from_address').value,
                    mail_from_name: document.getElementById('mail_from_name').value,
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successDiv.textContent = data.message;
                    successDiv.classList.remove('hidden');
                } else {
                    errorDiv.textContent = data.message;
                    errorDiv.classList.remove('hidden');
                }
            })
            .catch(error => {
                errorDiv.textContent = 'An unexpected error occurred: ' + error.message;
                errorDiv.classList.remove('hidden');
            })
            .finally(() => {
                testBtn.disabled = false;
                testBtn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg> Send Test Email';
            });
        });
    }
});
</script>
@endpush
