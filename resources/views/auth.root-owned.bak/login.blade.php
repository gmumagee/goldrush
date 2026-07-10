<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f6f7f9; color: #1f2933; }
        main { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        form { width: 100%; max-width: 380px; background: #fff; border: 1px solid #d9dee7; border-radius: 8px; padding: 24px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); }
        h1 { margin: 0 0 20px; font-size: 24px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; }
        input[type="email"], input[type="password"] { width: 100%; box-sizing: border-box; border: 1px solid #b8c0cc; border-radius: 6px; padding: 10px 12px; font-size: 16px; }
        .field { margin-bottom: 16px; }
        .checkbox { display: flex; align-items: center; gap: 8px; margin-bottom: 18px; }
        .checkbox label { margin: 0; font-weight: 400; }
        button { width: 100%; border: 0; border-radius: 6px; background: #1f6feb; color: #fff; padding: 11px 14px; font-size: 16px; font-weight: 700; cursor: pointer; }
        .errors { margin-bottom: 16px; padding: 12px; border: 1px solid #e5a3a3; border-radius: 6px; background: #fff1f1; color: #8a1f1f; }
        .errors ul { margin: 0; padding-left: 18px; }
    </style>
</head>
<body>
<main>
    <form method="POST" action="/login">
        @csrf

        <h1>Login</h1>

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
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email">
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>

        <div class="checkbox">
            <input id="remember" name="remember" type="checkbox" value="1">
            <label for="remember">Remember me</label>
        </div>

        <button type="submit">Login</button>
    </form>
</main>
</body>
</html>
