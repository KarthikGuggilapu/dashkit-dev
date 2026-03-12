<?php

namespace Dashkit\Http\Controllers;

use Dashkit\Models\DashkitAuditLog;
use Dashkit\Models\DashkitSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
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
        ]);

        $changed = [];
        if ((string) $user->name !== (string) $validated['name']) {
            $changed[] = 'name';
        }
        if ((string) $user->email !== (string) $validated['email']) {
            $changed[] = 'email';
        }

        $user->fill($validated);
        $user->save();

        DashkitAuditLog::record(
            $request,
            'profile.updated',
            'user',
            (string) $user->getAuthIdentifier(),
            ['changed_fields' => $changed]
        );

        return back()->with('status', 'profile-updated');
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
}
