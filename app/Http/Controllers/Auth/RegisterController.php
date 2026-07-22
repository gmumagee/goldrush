<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View|RedirectResponse
    {
        if (Tenancy::isSingle() && Tenancy::hasSingleAccount()) {
            return redirect()->route('login');
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        if (Tenancy::isSingle() && Tenancy::hasSingleAccount()) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Self-registration is disabled in single-tenant mode.']);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:tbl_users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
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
            ]);

            if (Tenancy::isSingle()) {
                $account = Tenancy::ensureSingleAccount(config('app.name', 'GoldRush'), $data['email']);
            } else {
                $account = Account::create([
                    'account_name' => $data['account_name'],
                    'slug' => $this->generateUniqueAccountSlug($data['account_name']),
                    'status' => Account::STATUS_ACTIVE,
                    'billing_email' => $data['email'],
                ]);
            }

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
