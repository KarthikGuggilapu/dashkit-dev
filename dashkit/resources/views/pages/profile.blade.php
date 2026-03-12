<x-dashkit-layout title="Profile">
    @php
        $user = $user ?? auth()->user();
        $profileMeta = $profileMeta ?? [];
        $profileActivity = $profileActivity ?? [];
        $profileSecurityMeta = $profileSecurityMeta ?? [];
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

        $initials = collect(explode(' ', trim($name)))
            ->filter()
            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
            ->take(2)
            ->implode('');
        $initials = $initials !== '' ? $initials : 'DU';

        $avatarPath = (string) ($profileMeta['avatar_path'] ?? '');
        $avatarUrl = $avatarPath !== '' ? \Illuminate\Support\Facades\Storage::disk('public')->url($avatarPath) : null;

        $statusMap = [
            'profile-updated' => ['tone' => 'success', 'text' => 'Profile updated successfully.'],
            'password-updated' => ['tone' => 'success', 'text' => 'Password updated successfully.'],
            'mail-settings-updated' => ['tone' => 'success', 'text' => 'Mail settings saved.'],
            'profile-avatar-removed' => ['tone' => 'success', 'text' => 'Profile photo removed.'],
        ];
        $flash = $statusMap[(string) session('status', '')] ?? null;

        $completionFields = [
            $name,
            $email,
            (string) ($profileMeta['phone'] ?? ''),
            (string) ($profileMeta['designation'] ?? ''),
            (string) ($profileMeta['company'] ?? ''),
            (string) ($profileMeta['location'] ?? ''),
            (string) ($profileMeta['website'] ?? ''),
            (string) ($profileMeta['github'] ?? ''),
            (string) ($profileMeta['linkedin'] ?? ''),
            (string) ($profileMeta['bio'] ?? ''),
            (string) ($profileMeta['preferred_timezone'] ?? ''),
            (string) ($profileMeta['preferred_locale'] ?? ''),
            $avatarPath,
        ];
        $completedCount = collect($completionFields)->filter(fn ($value) => trim((string) $value) !== '')->count();
        $completionPercentage = (int) round(($completedCount / max(count($completionFields), 1)) * 100);
    @endphp

    <style>
        .dk-profile-banner {
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            background: linear-gradient(140deg, #f8fafc 0%, #ecfeff 48%, #eef2ff 100%);
            padding: 1.25rem;
        }

        .dk-tabs-wrap {
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            padding: .75rem;
            display: flex;
            flex-wrap: wrap;
            gap: .55rem;
        }

        .dk-tab {
            border: 1px solid #e2e8f0;
            border-radius: .7rem;
            padding: .45rem .8rem;
            font-size: .85rem;
            color: #334155;
            background: #f8fafc;
            transition: all .2s ease;
        }

        .dk-tab[data-active="1"] {
            color: #0f172a;
            border-color: #22d3ee;
            background: linear-gradient(135deg, #ccfbf1 0%, #cffafe 100%);
            box-shadow: 0 8px 20px rgba(8, 145, 178, .15);
        }

        .dk-tab-panel {
            display: none;
        }

        .dk-tab-panel[data-active="1"] {
            display: block;
        }
    </style>

    @if ($flash)
        <x-dashkit::ui.alert class="mb-4" :tone="$flash['tone']" :message="$flash['text']" />
        <x-dashkit::ui.toast :tone="$flash['tone']" :message="$flash['text']" />
    @endif

    @if ($errors->any())
        <x-dashkit::ui.alert class="mb-4" tone="error" :message="$errors->first()" />
    @endif

    <section class="mb-6 dk-profile-banner">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="Profile picture" class="h-20 w-20 rounded-2xl border border-white object-cover shadow-md">
                @else
                    <div class="grid h-20 w-20 place-content-center rounded-2xl bg-gradient-to-br from-teal-500 to-cyan-500 text-2xl font-bold text-white">{{ $initials }}</div>
                @endif

                <div>
                    <h2 class="text-2xl font-bold text-slate-900">{{ $name }}</h2>
                    <p class="text-sm text-slate-600">{{ $email }}</p>
                    <p class="mt-1 text-xs text-slate-500">Complete profile score: <span class="font-semibold text-cyan-700">{{ $completionPercentage }}%</span></p>
                </div>
            </div>

            <div class="w-full max-w-xs">
                <div class="mb-1 flex items-center justify-between text-xs text-slate-500">
                    <span>Profile Completion</span>
                    <span>{{ $completedCount }}/{{ count($completionFields) }}</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200">
                    <div class="h-2 rounded-full bg-gradient-to-r from-cyan-500 to-emerald-500" style="width: {{ $completionPercentage }}%"></div>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-4 dk-tabs-wrap" role="tablist" aria-label="Profile tabs">
            <button type="button" class="dk-tab flex items-center gap-2" data-tab="personal" data-active="1">
                <x-dashkit::ui.icon name="profile" size="sm" /> Personal
            </button>
            <button type="button" class="dk-tab flex items-center gap-2" data-tab="security">
                <x-dashkit::ui.icon name="shield" size="sm" /> Security
            </button>
            <button type="button" class="dk-tab flex items-center gap-2" data-tab="mail">
                <x-dashkit::ui.icon name="mail" size="sm" /> Mail
            </button>
            <button type="button" class="dk-tab flex items-center gap-2" data-tab="activity">
                <x-dashkit::ui.icon name="activities" size="sm" /> Activity
            </button>
        </div>

        <div class="dk-tab-panel" data-panel="personal" data-active="1">
            <x-dashkit::ui.card title="Personal Information" description="Update profile details used across dashboard modules.">
                <form method="POST" action="{{ route('dashkit.profile.update') }}" class="grid gap-4 md:grid-cols-2" enctype="multipart/form-data">
                    @csrf

                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-slate-700" for="profile_avatar">Profile Photo</label>
                        <div class="flex flex-wrap items-center gap-3">
                            <input id="profile_avatar" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="block w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm">

                            @if ($avatarUrl)
                                <x-dashkit::ui.button type="submit" form="remove-avatar-form" variant="danger" size="sm" icon="trash" icon-lib="fa">Remove Photo</x-dashkit::ui.button>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Accepted formats: JPG, PNG, WEBP (max 2MB)</p>
                    </div>

                    <x-dashkit::ui.input label="Full Name" id="profile_name" name="name" :value="old('name', $name)" required />
                    <x-dashkit::ui.input label="Email" id="profile_email" name="email" type="email" :value="old('email', $email)" required />

                    <x-dashkit::ui.input label="Phone" id="profile_phone" name="phone" :value="old('phone', (string) ($profileMeta['phone'] ?? ''))" />
                    <x-dashkit::ui.input label="Designation" id="profile_designation" name="designation" :value="old('designation', (string) ($profileMeta['designation'] ?? ''))" />

                    <x-dashkit::ui.input label="Company" id="profile_company" name="company" :value="old('company', (string) ($profileMeta['company'] ?? ''))" />
                    <x-dashkit::ui.input label="Location" id="profile_location" name="location" :value="old('location', (string) ($profileMeta['location'] ?? ''))" />

                    <x-dashkit::ui.input label="Website" id="profile_website" name="website" :value="old('website', (string) ($profileMeta['website'] ?? ''))" placeholder="https://example.com" />
                    <x-dashkit::ui.input label="GitHub URL" id="profile_github" name="github" :value="old('github', (string) ($profileMeta['github'] ?? ''))" placeholder="https://github.com/username" />

                    <x-dashkit::ui.input label="LinkedIn URL" id="profile_linkedin" name="linkedin" :value="old('linkedin', (string) ($profileMeta['linkedin'] ?? ''))" placeholder="https://linkedin.com/in/username" />
                    <x-dashkit::ui.input label="Preferred Timezone" id="profile_preferred_timezone" name="preferred_timezone" :value="old('preferred_timezone', (string) ($profileMeta['preferred_timezone'] ?? config('app.timezone', 'UTC')))" />

                    <x-dashkit::ui.input label="Preferred Locale" id="profile_preferred_locale" name="preferred_locale" :value="old('preferred_locale', (string) ($profileMeta['preferred_locale'] ?? config('app.locale', 'en')))" />

                    <div class="space-y-2 md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700" for="profile_bio">Bio</label>
                        <textarea id="profile_bio" name="bio" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none" placeholder="Short intro about you...">{{ old('bio', (string) ($profileMeta['bio'] ?? '')) }}</textarea>
                    </div>

                    <div class="md:col-span-2">
                        <x-dashkit::ui.button type="submit" icon="floppy-disk" icon-lib="fa">Save Profile</x-dashkit::ui.button>
                    </div>
                </form>

                <form id="remove-avatar-form" method="POST" action="{{ route('dashkit.profile.avatar.remove') }}" class="hidden">
                    @csrf
                </form>
            </x-dashkit::ui.card>
        </div>

        <div class="dk-tab-panel" data-panel="security">
            <x-dashkit::ui.card title="Account Security" description="Regular password updates help keep your account safe.">
                <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 md:grid-cols-2">
                    <p>Last login: <span class="font-semibold text-slate-800">{{ (string) ($profileSecurityMeta['last_login_at'] ?? '') !== '' ? (string) ($profileSecurityMeta['last_login_at'] ?? '') : 'No records yet' }}</span></p>
                    <p>Last password change: <span class="font-semibold text-slate-800">{{ (string) ($profileSecurityMeta['last_password_change_at'] ?? '') !== '' ? (string) ($profileSecurityMeta['last_password_change_at'] ?? '') : 'No records yet' }}</span></p>
                </div>

                <form method="POST" action="{{ route('dashkit.profile.password.update') }}" class="grid gap-4 md:grid-cols-2">
                    @csrf

                    <x-dashkit::ui.input label="Current Password" id="current_password" name="current_password" type="password" required />
                    <div></div>
                    <x-dashkit::ui.input label="New Password" id="new_password" name="password" type="password" required />
                    <x-dashkit::ui.input label="Confirm New Password" id="new_password_confirmation" name="password_confirmation" type="password" required />

                    <div class="md:col-span-2">
                        <x-dashkit::ui.button type="submit" variant="warning" icon="key" icon-lib="fa">Update Password</x-dashkit::ui.button>
                    </div>
                </form>
            </x-dashkit::ui.card>
        </div>

        <div class="dk-tab-panel" data-panel="activity">
            <x-dashkit::ui.card title="Recent Activity" description="Track important profile and account actions.">
                <div class="space-y-3">
                    @forelse ($profileActivity as $event)
                        <div class="flex items-start justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ (string) ($event['action'] ?? 'Activity') }}</p>
                                <p class="text-xs text-slate-500">{{ (string) ($event['created_at'] ?? '') !== '' ? (string) ($event['created_at'] ?? '') : 'Unknown time' }}</p>
                            </div>
                            <div class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600">IP: {{ (string) ($event['ip_address'] ?? '') !== '' ? (string) ($event['ip_address'] ?? '') : '-' }}</div>
                        </div>
                    @empty
                        <x-dashkit::ui.alert tone="info" message="No activity yet. Actions like profile updates and sign-ins will appear here." />
                    @endforelse
                </div>
            </x-dashkit::ui.card>
        </div>

        <div class="dk-tab-panel" data-panel="mail">
            <x-dashkit::ui.card title="Mail Settings" description="Manage outgoing mail used for notifications, password reset and alerts.">
                <form method="POST" action="{{ route('dashkit.settings.mail.update') }}" class="grid gap-4 md:grid-cols-2">
                    @csrf

                    <x-dashkit::ui.input label="Mailer" id="mail_mailer" name="mail_mailer" :value="old('mail_mailer', (string) ($mailSettings['mailer'] ?? 'smtp'))" required />
                    <x-dashkit::ui.input label="Host" id="mail_host" name="mail_host" :value="old('mail_host', (string) ($mailSettings['host'] ?? '127.0.0.1'))" required />
                    <x-dashkit::ui.input label="Port" id="mail_port" name="mail_port" type="number" :value="old('mail_port', (string) ($mailSettings['port'] ?? '2525'))" required />
                    <x-dashkit::ui.input label="Username" id="mail_username" name="mail_username" :value="old('mail_username', (string) ($mailSettings['username'] ?? ''))" />
                    <x-dashkit::ui.input label="Password (leave blank to keep current)" id="mail_password" name="mail_password" type="password" />
                    <x-dashkit::ui.input label="Encryption" id="mail_encryption" name="mail_encryption" :value="old('mail_encryption', (string) ($mailSettings['encryption'] ?? 'tls'))" />
                    <x-dashkit::ui.input label="From Address" id="mail_from_address" name="mail_from_address" type="email" :value="old('mail_from_address', (string) ($mailSettings['from_address'] ?? 'hello@example.com'))" required />
                    <x-dashkit::ui.input label="From Name" id="mail_from_name" name="mail_from_name" :value="old('mail_from_name', (string) ($mailSettings['from_name'] ?? config('app.name', 'Dashkit')))" required />

                    <div class="md:col-span-2">
                        <x-dashkit::ui.button type="submit" variant="success" icon="paper-plane" icon-lib="fa">Save Mail Settings</x-dashkit::ui.button>
                    </div>
                </form>
            </x-dashkit::ui.card>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-tab]'));
            var panels = Array.prototype.slice.call(document.querySelectorAll('[data-panel]'));
            var defaultTab = localStorage.getItem('dashkit.profile.activeTab') || 'personal';

            function activateTab(name) {
                tabs.forEach(function (tab) {
                    var isActive = tab.getAttribute('data-tab') === name;
                    tab.setAttribute('data-active', isActive ? '1' : '0');
                });

                panels.forEach(function (panel) {
                    var isActive = panel.getAttribute('data-panel') === name;
                    panel.setAttribute('data-active', isActive ? '1' : '0');
                });

                localStorage.setItem('dashkit.profile.activeTab', name);
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activateTab(tab.getAttribute('data-tab') || 'personal');
                });
            });

            activateTab(defaultTab);
        });
    </script>
</x-dashkit-layout>
