<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use Responds;

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->fail('Invalid email or password.');
        }
        if ($user->status !== 'active') {
            return $this->fail('Your account is not active. Please contact your administrator.');
        }
        if (! $user->isSuperAdmin()) {
            $client = $user->client;
            if (! $client || ! $client->isActive()) {
                return $this->fail('Your organisation account is not active.');
            }
            $domainClient = request()->attributes->get('domainClient');
            if ($domainClient && $domainClient->id !== $client->id) {
                return $this->fail('This account does not belong to this site.');
            }
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('login', $user, $user->name.' signed in');

        return $this->ok('Welcome back, '.$user->name.'!', route('dashboard'));
    }

    public function logout(Request $request)
    {
        Audit::log('logout', $request->user(), $request->user()->name.' signed out');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->ok('You have been signed out.', route('login'));
    }
}
