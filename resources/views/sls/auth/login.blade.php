<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>SLS Login</title>
        <link rel="icon" type="image/png" href="{{ asset('sls-favicon.png') }}?v={{ filemtime(public_path('sls-favicon.png')) }}">
        <link rel="stylesheet" href="{{ asset('sls-ui.css') }}?v={{ filemtime(public_path('sls-ui.css')) }}">
        <style>
            body {
                align-items: center;
                background: #f6f8fb;
                display: flex;
                min-height: 100vh;
                padding: 24px;
            }

            .login-panel {
                background: #fff;
                border: 1px solid #dfe5ee;
                border-radius: 8px;
                box-shadow: 0 18px 60px rgba(15, 23, 42, .08);
                margin: 0 auto;
                max-width: 420px;
                padding: 28px;
                width: 100%;
            }

            .login-logo {
                height: 46px;
                margin-bottom: 22px;
                object-fit: contain;
                object-position: left center;
                width: 160px;
            }

            .login-title {
                font-size: 26px;
                margin: 0 0 8px;
            }

            .login-copy {
                color: #536173;
                margin: 0 0 22px;
            }

            .login-field {
                display: grid;
                gap: 7px;
                margin-bottom: 14px;
            }

            .login-field label {
                color: #3e4a5c;
                font-size: 13px;
                font-weight: 700;
            }

            .login-field input {
                border: 1px solid #d9e0ea;
                border-radius: 8px;
                font: inherit;
                min-height: 44px;
                padding: 0 12px;
                width: 100%;
            }

            .login-row {
                align-items: center;
                display: flex;
                gap: 10px;
                justify-content: space-between;
                margin: 6px 0 18px;
            }

            .login-remember {
                align-items: center;
                color: #536173;
                display: inline-flex;
                font-size: 14px;
                gap: 8px;
                white-space: nowrap;
            }

            .login-error {
                color: #d11a2a;
                font-weight: 700;
                margin-bottom: 14px;
            }
        </style>
    </head>
    <body>
        <main class="login-panel">
            <img class="login-logo" src="{{ asset('sls-logo.png') }}?v={{ filemtime(public_path('sls-logo.png')) }}" alt="SLS">
            <h1 class="login-title">Sign in to SLS</h1>
            <p class="login-copy">Use your SLS user account to access the dashboard, intelligence monitor, CRM, and setup tools.</p>

            @if ($errors->any())
                <div class="login-error">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ route('sls.login.store') }}">
                @csrf
                <div class="login-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                </div>

                <div class="login-field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>

                <div class="login-row">
                    <label class="login-remember">
                        <input name="remember" type="checkbox" value="1">
                        Remember me
                    </label>
                </div>

                <button class="button primary" type="submit" style="width:100%;justify-content:center;">Sign in</button>
            </form>
        </main>
    </body>
</html>
