<?php

namespace Dashkit\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashkitAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('dashkit::auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guard = (string) config('dashkit.auth.guard', 'web');

        if (! Auth::guard($guard)->attempt($credentials, (bool) $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        $request->session()->regenerate();

        $redirect = trim((string) config('dashkit.auth.redirect_after_login', '/'));
        $prefix = trim((string) config('dashkit.route_prefix', 'dashboard'), '/');

        $target = $redirect === '' || $redirect === '/'
            ? '/'
            : '/'.$prefix.'/'.ltrim($redirect, '/');

        return redirect()->intended($target);
    }

    public function logout(Request $request): RedirectResponse
    {
        $guard = (string) config('dashkit.auth.guard', 'web');

        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $loginPath = '/'.ltrim((string) config('dashkit.auth.login_route', 'login'), '/');

        return redirect($loginPath);
    }

    public function showForgotPassword(): View
    {
        return view('dashkit::auth.forgot-password');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::broker($this->passwordBroker())->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    public function showResetPassword(string $token, Request $request): View
    {
        return view('dashkit::auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $status = Password::broker($this->passwordBroker())->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('dashkit.login')->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    private function passwordBroker(): string
    {
        return (string) config('dashkit.auth.password_broker', config('auth.defaults.passwords', 'users'));
    }
}
