<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f6f7f9; color: #1f2933; }
        main { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        form { width: 100%; max-width: 420px; background: #fff; border: 1px solid #d9dee7; border-radius: 8px; padding: 24px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); }
        h1 { margin: 0 0 20px; font-size: 24px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; }
        input { width: 100%; box-sizing: border-box; border: 1px solid #b8c0cc; border-radius: 6px; padding: 10px 12px; font-size: 16px; }
        .field { margin-bottom: 16px; }
        button { width: 100%; border: 0; border-radius: 6px; background: #1f6feb; color: #fff; padding: 11px 14px; font-size: 16px; font-weight: 700; cursor: pointer; }
        .errors { margin-bottom: 16px; padding: 12px; border: 1px solid #e5a3a3; border-radius: 6px; background: #fff1f1; color: #8a1f1f; }
        .errors ul { margin: 0; padding-left: 18px; }
        .login-link { margin-top: 16px; text-align: center; color: #52606d; }
        .login-link a { color: #1f6feb; }
    </style>
</head>
<body>
<main>
    <form method="POST" action="/register">
        @csrf

        <h1>Create account</h1>

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name">
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
        </div>

        <div class="field">
            <label for="account_name">Business/account name</label>
            <input id="account_name" name="account_name" type="text" value="{{ old('account_name') }}" required autocomplete="organization">
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password">
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>

        <button type="submit">Register</button>

        <div class="login-link">
            Already have an account? <a href="/login">Login</a>
        </div>
    </form>
</main>
</body>
</html>
