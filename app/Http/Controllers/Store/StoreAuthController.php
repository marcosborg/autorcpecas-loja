<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StoreAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('store.account.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Credenciais invalidas.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(url('/loja/conta'));
    }

    public function showRegister(): View
    {
        return view('store.account.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'nif' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::query()->create([
            'name' => trim((string) $data['name']),
            'email' => mb_strtolower(trim((string) $data['email']), 'UTF-8'),
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'nif' => trim((string) ($data['nif'] ?? '')) ?: null,
            'password' => Hash::make((string) $data['password']),
            'is_admin' => false,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(url('/loja/conta'))->with('success', 'Conta criada com sucesso.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(url('/loja'))->with('success', 'Sessao terminada.');
    }
}

