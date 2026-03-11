<?php

namespace Dashkit\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
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
}
