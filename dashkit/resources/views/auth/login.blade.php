<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('dashkit.name', 'Dashkit') }} Login</title>
    <link rel="stylesheet" href="{{ asset('vendor/dashkit/css/dashkit.css') }}">
</head>
<body class="dk-auth-body">
<main class="dk-auth-card">
    <h1>{{ config('dashkit.name', 'Dashkit') }}</h1>
    <p>Sign in to access your dashboard.</p>

    <form method="POST" action="{{ route('dashkit.login.attempt') }}" class="dk-auth-form">
        @csrf
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus>

        <label>Password</label>
        <input type="password" name="password" required>

        <label class="dk-checkbox">
            <input type="checkbox" name="remember" value="1">
            <span>Remember me</span>
        </label>

        @if($errors->any())
            <div class="dk-error">{{ $errors->first() }}</div>
        @endif

        <button type="submit" class="dk-button dk-button-full">Login</button>
    </form>
</main>
</body>
</html>
