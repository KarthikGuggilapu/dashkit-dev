{{-- filepath: e:\xampp\htdocs\dashkit-dev\packages\dashkit\resources\views\auth\login.blade.php --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('dashkit.name', 'Dashkit') }} | Sign In</title>
    <link rel="stylesheet" href="{{ asset('vendor/dashkit/css/dashkit.css') }}">
    <style>
        .dk-auth-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px;background:#f5f7fb}
        .dk-auth-card{width:100%;max-width:430px;background:#fff;border:1px solid #e7eaf3;border-radius:14px;padding:28px;box-shadow:0 8px 30px rgba(16,24,40,.06)}
        .dk-auth-title{margin:0 0 6px;font-size:24px;font-weight:700;color:#111827}
        .dk-auth-subtitle{margin:0 0 22px;font-size:14px;color:#6b7280}
        .dk-auth-form{display:grid;gap:14px}
        .dk-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
        .dk-input{width:100%;height:44px;border:1px solid #d1d5db;border-radius:10px;padding:0 12px;font-size:14px;outline:none}
        .dk-input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.14)}
        .dk-row{display:flex;justify-content:space-between;align-items:center;gap:10px}
        .dk-checkbox{display:flex;align-items:center;gap:8px;font-size:13px;color:#4b5563}
        .dk-link{font-size:13px;color:#4f46e5;text-decoration:none}
        .dk-link:hover{text-decoration:underline}
        .dk-error{padding:10px 12px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;border-radius:10px;font-size:13px}
        .dk-success{padding:10px 12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:10px;font-size:13px}
        .dk-toast{position:fixed;top:18px;right:18px;max-width:360px;padding:12px 14px;border-radius:10px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;box-shadow:0 10px 28px rgba(16,24,40,.12);font-size:13px;line-height:1.45;z-index:9999;opacity:0;transform:translateY(-8px);transition:opacity .2s ease,transform .2s ease}
        .dk-toast.dk-toast-show{opacity:1;transform:translateY(0)}
        .dk-button{height:44px;border:0;border-radius:10px;background:#111827;color:#fff;font-weight:600;cursor:pointer}
        .dk-button:hover{background:#1f2937}
        .dk-button-full{width:100%}
    </style>
</head>
<body>
@if (session('dashkit_toast_error'))
    <div id="dk-toast" class="dk-toast" role="status" aria-live="polite">{{ session('dashkit_toast_error') }}</div>
@endif
<div class="dk-auth-wrap">
    <main class="dk-auth-card" role="main" aria-labelledby="login-heading">
        <h1 id="login-heading" class="dk-auth-title">{{ config('dashkit.name', 'Dashkit') }}</h1>
        <p class="dk-auth-subtitle">Please sign in with your account credentials.</p>

        @if (session('status'))
            <div class="dk-success" style="margin-bottom:12px">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('dashkit.login.attempt') }}" class="dk-auth-form">
            @csrf

            <div>
                <label for="email" class="dk-label">Email Address</label>
                <input id="email" class="dk-input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            </div>

            <div>
                <label for="password" class="dk-label">Password</label>
                <input id="password" class="dk-input" type="password" name="password" required autocomplete="current-password">
            </div>

            <div class="dk-row">
                <label class="dk-checkbox">
                    <input type="checkbox" name="remember" value="1">
                    <span>Remember me</span>
                </label>

                @if(Route::has('dashkit.password.request'))
                    <a class="dk-link" href="{{ route('dashkit.password.request') }}">Forgot password?</a>
                @endif
            </div>

            @if($errors->any())
                <div class="dk-error">{{ $errors->first() }}</div>
            @endif

            <button type="submit" class="dk-button dk-button-full">Sign In</button>
        </form>
    </main>
</div>
@if (session('dashkit_toast_error'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var toast = document.getElementById('dk-toast');
        if (!toast) {
            return;
        }

        requestAnimationFrame(function () {
            toast.classList.add('dk-toast-show');
        });

        setTimeout(function () {
            toast.classList.remove('dk-toast-show');
        }, 4200);

        setTimeout(function () {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 4500);
    });
</script>
@endif
</body>
</html>