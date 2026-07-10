<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Select Account</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f6f7f9; color: #1f2933; }
        main { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        section { width: 100%; max-width: 520px; background: #fff; border: 1px solid #d9dee7; border-radius: 8px; padding: 24px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); }
        h1 { margin: 0 0 18px; font-size: 24px; }
        .account { display: flex; align-items: center; gap: 10px; padding: 12px; border: 1px solid #d9dee7; border-radius: 6px; margin-bottom: 10px; }
        button { border: 0; border-radius: 6px; background: #1f6feb; color: #fff; padding: 10px 14px; font-size: 16px; font-weight: 700; cursor: pointer; }
        .actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 18px; }
        .logout { background: transparent; color: #52606d; border: 1px solid #b8c0cc; }
        .errors { margin-bottom: 16px; padding: 12px; border: 1px solid #e5a3a3; border-radius: 6px; background: #fff1f1; color: #8a1f1f; }
    </style>
</head>
<body>
<main>
    <section>
        <h1>Select account</h1>

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="/accounts/select">
            @csrf

            @foreach ($accounts as $account)
                <label class="account">
                    <input name="account_id" type="radio" value="{{ $account->id }}" required>
                    <span>{{ $account->account_name }}</span>
                </label>
            @endforeach

            <div class="actions">
                <button type="submit">Continue</button>
            </div>
        </form>

        <form method="POST" action="/logout" class="actions">
            @csrf
            <button class="logout" type="submit">Logout</button>
        </form>
    </section>
</main>
</body>
</html>
