<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:tbl_users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'account_name' => ['required', 'string', 'max:255'],
        ]);

        [$user, $account] = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => 'active',
            ]);

            $account = Account::create([
                'account_name' => $data['account_name'],
                'slug' => $this->generateUniqueAccountSlug($data['account_name']),
                'status' => 'active',
                'billing_email' => $data['email'],
            ]);

            AccountUser::create([
                'account_id' => $account->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
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
