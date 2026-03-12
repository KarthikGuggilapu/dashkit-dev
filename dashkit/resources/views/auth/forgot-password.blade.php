{{-- filepath: e:\xampp\htdocs\dashkit-dev\packages\dashkit\resources\views\auth\forgot-password.blade.php --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('dashkit.name', 'Dashkit') }} | Forgot Password</title>
    <link rel="stylesheet" href="{{ asset('vendor/dashkit/css/dashkit.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWix+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkR4j8R2f0x1B3p6k9R/+qvOB0fOkHn84q0g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .dk-auth-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px;background:#f5f7fb}
        .dk-auth-card{width:100%;max-width:430px;background:#fff;border:1px solid #e7eaf3;border-radius:14px;padding:28px;box-shadow:0 8px 30px rgba(16,24,40,.06)}
        .dk-auth-title{margin:0 0 6px;font-size:24px;font-weight:700;color:#111827}
        .dk-auth-subtitle{margin:0 0 22px;font-size:14px;color:#6b7280}
        .dk-auth-form{display:grid;gap:14px}
        .dk-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
        .dk-input{width:100%;height:44px;border:1px solid #d1d5db;border-radius:10px;padding:0 12px;font-size:14px;outline:none}
        .dk-input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.14)}
        .dk-success{padding:10px 12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:10px;font-size:13px}
        .dk-error{padding:10px 12px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;border-radius:10px;font-size:13px}
        .dk-button{height:44px;border:0;border-radius:10px;background:#111827;color:#fff;font-weight:600;cursor:pointer}
        .dk-button:hover{background:#1f2937}
        .dk-button-full{width:100%}
        .dk-link{font-size:13px;color:#4f46e5;text-decoration:none}
        .dk-link:hover{text-decoration:underline}
    </style>
</head>
<body>
<div class="dk-auth-wrap">
    <main class="dk-auth-card" role="main" aria-labelledby="forgot-heading">
        <h1 id="forgot-heading" class="dk-auth-title">Forgot Password</h1>
        <p class="dk-auth-subtitle">Enter your email and we will send a password reset link.</p>

        @if (session('status'))
            <div class="dk-success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('dashkit.password.email') }}" class="dk-auth-form">
            @csrf

            <div>
                <label for="email" class="dk-label">Email Address</label>
                <input id="email" class="dk-input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            </div>

            @error('email')
                <div class="dk-error">{{ $message }}</div>
            @enderror

            <button type="submit" class="dk-button dk-button-full">Send Reset Link</button>
        </form>

        @if(Route::has('dashkit.login'))
            <p style="margin-top:14px">
                <a class="dk-link" href="{{ route('dashkit.login') }}">Back to sign in</a>
            </p>
        @endif
    </main>
</div>
</body>
</html>