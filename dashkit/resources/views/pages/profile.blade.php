<x-dashkit-layout title="Profile">
    @php
        $user = $user ?? auth()->user();
        $mailSettings = $mailSettings ?? [
            'mailer' => env('MAIL_MAILER', 'smtp'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', '2525'),
            'username' => env('MAIL_USERNAME', ''),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'from_address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'from_name' => env('MAIL_FROM_NAME', config('app.name', 'Dashkit')),
        ];
        $name = (string) ($user?->name ?? 'Dashkit User');
        $email = (string) ($user?->email ?? 'not-available@example.com');
        $initials = collect(explode(' ', trim($name)))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
        $initials = $initials !== '' ? $initials : 'DU';
    @endphp

    @if (session('status') === 'profile-updated')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Profile updated successfully.</div>
    @endif

    @if (session('status') === 'password-updated')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Password updated successfully.</div>
    @endif

    @if (session('status') === 'mail-settings-updated')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Mail settings saved.</div>
    @endif

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center gap-4">
            <div class="grid h-16 w-16 place-content-center rounded-2xl bg-gradient-to-br from-teal-500 to-cyan-500 text-xl font-bold text-white">{{ $initials }}</div>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ $name }}</h2>
                <p class="text-sm text-slate-500">{{ $email }}</p>
            </div>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-bold text-slate-900">Edit Profile</h3>
            <form method="POST" action="{{ route('dashkit.profile.update') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="profile_name">Name</label>
                    <input id="profile_name" type="text" name="name" value="{{ old('name', $name) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                    @error('name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="profile_email">Email</label>
                    <input id="profile_email" type="email" name="email" value="{{ old('email', $email) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                    @error('email')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Save Profile</button>
            </form>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-bold text-slate-900">Change Password</h3>
            <form method="POST" action="{{ route('dashkit.profile.password.update') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="current_password">Current Password</label>
                    <input id="current_password" type="password" name="current_password" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                    @error('current_password')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="new_password">New Password</label>
                    <input id="new_password" type="password" name="password" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                    @error('password')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="new_password_confirmation">Confirm Password</label>
                    <input id="new_password_confirmation" type="password" name="password_confirmation" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Update Password</button>
            </form>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <h3 class="mb-4 text-lg font-bold text-slate-900">Mail Settings</h3>
            <form method="POST" action="{{ route('dashkit.settings.mail.update') }}" class="grid gap-4 md:grid-cols-2">
                @csrf

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_mailer">Mailer</label>
                    <input id="mail_mailer" type="text" name="mail_mailer" value="{{ old('mail_mailer', (string) ($mailSettings['mailer'] ?? 'smtp')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_host">Host</label>
                    <input id="mail_host" type="text" name="mail_host" value="{{ old('mail_host', (string) ($mailSettings['host'] ?? '127.0.0.1')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_port">Port</label>
                    <input id="mail_port" type="number" name="mail_port" value="{{ old('mail_port', (string) ($mailSettings['port'] ?? '2525')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_username">Username</label>
                    <input id="mail_username" type="text" name="mail_username" value="{{ old('mail_username', (string) ($mailSettings['username'] ?? '')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_password">Password (leave blank to keep current)</label>
                    <input id="mail_password" type="password" name="mail_password" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_encryption">Encryption</label>
                    <input id="mail_encryption" type="text" name="mail_encryption" value="{{ old('mail_encryption', (string) ($mailSettings['encryption'] ?? 'tls')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_from_address">From Address</label>
                    <input id="mail_from_address" type="email" name="mail_from_address" value="{{ old('mail_from_address', (string) ($mailSettings['from_address'] ?? 'hello@example.com')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_from_name">From Name</label>
                    <input id="mail_from_name" type="text" name="mail_from_name" value="{{ old('mail_from_name', (string) ($mailSettings['from_name'] ?? config('app.name', 'Dashkit'))) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div class="md:col-span-2">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">Save Mail Settings</button>
                </div>
            </form>
        </article>
    </section>
</x-dashkit-layout>
