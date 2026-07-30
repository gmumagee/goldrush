<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Plan;
use App\Models\User;
use App\Support\Demo;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View|RedirectResponse
    {
        if (Demo::isEnabled() || ! config('security.allow_self_registration', false) || Tenancy::isSingle()) {
            return redirect()->route('login');
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        if (Demo::isEnabled() || ! config('security.allow_self_registration', false) || Tenancy::isSingle()) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Self-registration is currently disabled.']);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:tbl_users,email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()],
        ];

        if (Tenancy::isMulti()) {
            $rules['account_name'] = ['required', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        [$user, $account] = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => Hash::make($data['password']),
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => null,
            ]);

            $account = Account::create([
                'plan_id' => Plan::FREE_ID,
                'account_name' => $data['account_name'],
                'slug' => $this->generateUniqueAccountSlug($data['account_name']),
                'status' => Account::STATUS_ACTIVE,
                'billing_email' => $data['email'],
            ]);

            AccountUser::create([
                'account_id' => $account->id,
                'user_id' => $user->id,
                'role' => AccountUser::ROLE_OWNER,
                'status' => AccountUser::STATUS_ACTIVE,
            ]);

            return [$user, $account];
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('current_account_id', $account->id);

        if (config('security.require_verified_email', true)) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice');
        }

        return redirect('/dashboard');
    }

    private function generateUniqueAccountSlug(string $accountName): string
    {
        $baseSlug = Str::slug($accountName) ?: 'account';
        $slug = $baseSlug;
        $counter = 2;

        while (Account::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
