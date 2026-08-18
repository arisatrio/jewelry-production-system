<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\QuickLoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

class QuickLoginController extends Controller
{
    /**
     * Authenticate using a saved user_id profile without a password.
     */
    public function store(QuickLoginRequest $request): Response
    {
        $user = $request->loginUser();

        if (! $user instanceof User) {
            return redirect()->route('login')->withErrors([
                'user_id' => __('auth.failed'),
            ]);
        }

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return $request->wantsJson()
                ? response()->json(['two_factor' => true])
                : redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return app(LoginResponse::class)->toResponse($request);
    }
}
