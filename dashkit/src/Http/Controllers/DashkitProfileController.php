<?php

namespace Dashkit\Http\Controllers;

use Dashkit\Models\DashkitAuditLog;
use Dashkit\Models\DashkitSetting;
use Dashkit\Models\DashkitUserPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashkitProfileController extends Controller
{
    public function show(Request $request): View
    {
        $view = view()->exists('dashkit.pages.profile')
            ? 'dashkit.pages.profile'
            : 'dashkit::pages.profile';

        return view($view, [
            'user' => $request->user(),
            'profileMeta' => $this->profileMeta($request),
            'profileActivity' => $this->profileActivity($request),
            'profileSecurityMeta' => $this->profileSecurityMeta($request),
            'mailSettings' => [
                'mailer' => DashkitSetting::get('mail_mailer', (string) env('MAIL_MAILER', 'smtp')),
                'host' => DashkitSetting::get('mail_host', (string) env('MAIL_HOST', '127.0.0.1')),
                'port' => DashkitSetting::get('mail_port', (string) env('MAIL_PORT', '2525')),
                'username' => DashkitSetting::get('mail_username', (string) env('MAIL_USERNAME', '')),
                'encryption' => DashkitSetting::get('mail_encryption', (string) env('MAIL_ENCRYPTION', 'tls')),
                'from_address' => DashkitSetting::get('mail_from_address', (string) env('MAIL_FROM_ADDRESS', 'hello@example.com')),
                'from_name' => DashkitSetting::get('mail_from_name', (string) env('MAIL_FROM_NAME', config('app.name', 'Dashkit'))),
            ],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getAuthIdentifier())],
            'phone' => ['nullable', 'string', 'max:40'],
            'designation' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:150'],
            'website' => ['nullable', 'url', 'max:255'],
            'github' => ['nullable', 'url', 'max:255'],
            'linkedin' => ['nullable', 'url', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'preferred_timezone' => ['nullable', 'string', 'max:120'],
            'preferred_locale' => ['nullable', 'string', 'max:12'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $changed = [];
        if ((string) $user->name !== (string) $validated['name']) {
            $changed[] = 'name';
        }
        if ((string) $user->email !== (string) $validated['email']) {
            $changed[] = 'email';
        }

        $user->fill([
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
        ]);
        $user->save();

        $userId = (int) $user->getAuthIdentifier();

        $profilePairs = [
            'profile_phone' => (string) ($validated['phone'] ?? ''),
            'profile_designation' => (string) ($validated['designation'] ?? ''),
            'profile_company' => (string) ($validated['company'] ?? ''),
            'profile_location' => (string) ($validated['location'] ?? ''),
            'profile_website' => (string) ($validated['website'] ?? ''),
            'profile_github' => (string) ($validated['github'] ?? ''),
            'profile_linkedin' => (string) ($validated['linkedin'] ?? ''),
            'profile_bio' => (string) ($validated['bio'] ?? ''),
            'profile_timezone' => (string) ($validated['preferred_timezone'] ?? ''),
            'profile_locale' => (string) ($validated['preferred_locale'] ?? ''),
        ];

        DashkitUserPreference::putMany($userId, $profilePairs);

        if ($request->hasFile('avatar')) {
            $oldAvatarPath = DashkitUserPreference::getValue($userId, 'profile_avatar_path', '');
            $newAvatarPath = (string) $request->file('avatar')?->store('dashkit/avatars', 'public');

            if ($newAvatarPath !== '') {
                DashkitUserPreference::putMany($userId, [
                    'profile_avatar_path' => $newAvatarPath,
                ]);

                if ($oldAvatarPath !== '' && $oldAvatarPath !== $newAvatarPath && Storage::disk('public')->exists($oldAvatarPath)) {
                    Storage::disk('public')->delete($oldAvatarPath);
                }

                $changed[] = 'avatar';
            }
        }

        foreach ($profilePairs as $key => $value) {
            if ($value !== '') {
                $changed[] = $key;
            }
        }

        DashkitAuditLog::record(
            $request,
            'profile.updated',
            'user',
            (string) $user->getAuthIdentifier(),
            ['changed_fields' => $changed]
        );

        return back()->with('status', 'profile-updated');
    }

    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $userId = (int) $user->getAuthIdentifier();
        $avatarPath = DashkitUserPreference::getValue($userId, 'profile_avatar_path', '');

        if ($avatarPath !== '' && Storage::disk('public')->exists($avatarPath)) {
            Storage::disk('public')->delete($avatarPath);
        }

        DashkitUserPreference::forgetKey($userId, 'profile_avatar_path');

        DashkitAuditLog::record(
            $request,
            'profile.avatar.removed',
            'user',
            (string) $userId,
            ['avatar_removed' => true]
        );

        return back()->with('status', 'profile-avatar-removed');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        if (! Hash::check($validated['current_password'], (string) $user->getAuthPassword())) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        DashkitAuditLog::record(
            $request,
            'profile.password.updated',
            'user',
            (string) $user->getAuthIdentifier(),
            ['password_changed' => true]
        );

        return back()->with('status', 'password-updated');
    }

    public function updateMailSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_mailer' => ['required', 'string', 'max:64'],
            'mail_host' => ['required', 'string', 'max:255'],
            'mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'max:32'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
        ]);

        DashkitSetting::put('mail_mailer', (string) $validated['mail_mailer']);
        DashkitSetting::put('mail_host', (string) $validated['mail_host']);
        DashkitSetting::put('mail_port', (string) $validated['mail_port']);
        DashkitSetting::put('mail_username', (string) ($validated['mail_username'] ?? ''));
        DashkitSetting::put('mail_encryption', (string) ($validated['mail_encryption'] ?? ''));
        DashkitSetting::put('mail_from_address', (string) $validated['mail_from_address']);
        DashkitSetting::put('mail_from_name', (string) $validated['mail_from_name']);

        if (array_key_exists('mail_password', $validated) && (string) $validated['mail_password'] !== '') {
            DashkitSetting::putSecret('mail_password', (string) $validated['mail_password']);
        }

        DashkitAuditLog::record(
            $request,
            'settings.mail.updated',
            'dashkit_settings',
            'mail',
            [
                'changed_fields' => [
                    'mail_mailer',
                    'mail_host',
                    'mail_port',
                    'mail_username',
                    'mail_encryption',
                    'mail_from_address',
                    'mail_from_name',
                ],
                'mail_password_updated' => array_key_exists('mail_password', $validated) && (string) $validated['mail_password'] !== '',
            ]
        );

        return back()->with('status', 'mail-settings-updated');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function profileActivity(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        $userId = (int) $user->getAuthIdentifier();

        return DashkitAuditLog::query()
            ->where('actor_id', $userId)
            ->whereIn('action', [
                'auth.login',
                'auth.logout',
                'profile.updated',
                'profile.password.updated',
                'profile.avatar.removed',
                'settings.mail.updated',
            ])
            ->latest('created_at')
            ->limit(12)
            ->get()
            ->map(function (DashkitAuditLog $log): array {
                $labels = [
                    'auth.login' => 'Signed in',
                    'auth.logout' => 'Signed out',
                    'profile.updated' => 'Updated profile details',
                    'profile.password.updated' => 'Changed account password',
                    'profile.avatar.removed' => 'Removed profile photo',
                    'settings.mail.updated' => 'Updated mail settings',
                ];

                return [
                    'action' => $labels[$log->action] ?? $log->action,
                    'created_at' => (string) optional($log->created_at)?->toDateTimeString(),
                    'ip_address' => (string) ($log->ip_address ?? ''),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function profileSecurityMeta(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [
                'last_login_at' => '',
                'last_password_change_at' => '',
            ];
        }

        $userId = (int) $user->getAuthIdentifier();

        $lastLoginLog = DashkitAuditLog::query()
            ->where('actor_id', $userId)
            ->where('action', 'auth.login')
            ->latest('created_at')
            ->first();

        $lastPasswordLog = DashkitAuditLog::query()
            ->where('actor_id', $userId)
            ->where('action', 'profile.password.updated')
            ->latest('created_at')
            ->first();

        $lastLoginAt = $lastLoginLog instanceof DashkitAuditLog && $lastLoginLog->created_at
            ? (string) $lastLoginLog->created_at->toDateTimeString()
            : '';

        $lastPasswordChangeAt = $lastPasswordLog instanceof DashkitAuditLog && $lastPasswordLog->created_at
            ? (string) $lastPasswordLog->created_at->toDateTimeString()
            : '';

        return [
            'last_login_at' => $lastLoginAt,
            'last_password_change_at' => $lastPasswordChangeAt,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function profileMeta(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        $userId = (int) $user->getAuthIdentifier();

        return [
            'avatar_path' => DashkitUserPreference::getValue($userId, 'profile_avatar_path', ''),
            'phone' => DashkitUserPreference::getValue($userId, 'profile_phone', ''),
            'designation' => DashkitUserPreference::getValue($userId, 'profile_designation', ''),
            'company' => DashkitUserPreference::getValue($userId, 'profile_company', ''),
            'location' => DashkitUserPreference::getValue($userId, 'profile_location', ''),
            'website' => DashkitUserPreference::getValue($userId, 'profile_website', ''),
            'github' => DashkitUserPreference::getValue($userId, 'profile_github', ''),
            'linkedin' => DashkitUserPreference::getValue($userId, 'profile_linkedin', ''),
            'bio' => DashkitUserPreference::getValue($userId, 'profile_bio', ''),
            'preferred_timezone' => DashkitUserPreference::getValue($userId, 'profile_timezone', ''),
            'preferred_locale' => DashkitUserPreference::getValue($userId, 'profile_locale', ''),
        ];
    }
}
