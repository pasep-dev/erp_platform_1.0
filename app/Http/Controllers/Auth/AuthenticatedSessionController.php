<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(
        LoginRequest $request,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $credentials = $request->credentials();

        $throttleKey = Str::transliterate(
            Str::lower($request->string('email')->toString())
                .'|'
                .$request->ip(),
        );

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        if (! Auth::attempt(
            [
                ...$credentials,
                'is_active' => true,
            ],
            $request->boolean('remember'),
        )) {
            RateLimiter::hit($throttleKey, 60);

            $activityLogger->log(
                action: 'LOGIN_FAILED',
                module: 'Authentication',
                description: 'Login attempt failed.',
                ignoreAuthenticatedUser: true,
            );

            throw ValidationException::withMessages([
                'email' => 'Email atau password tidak valid.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        $user = $request->user();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        $activityLogger->log(
            action: 'LOGIN_SUCCESS',
            module: 'Authentication',
            description: 'User logged in successfully.',
            subjectType: $user::class,
            subjectId: $user->id,
        );

        return redirect()->intended('/');
    }

    public function destroy(
        Request $request,
        ActivityLogger $activityLogger,
    ): SymfonyResponse {
        $user = $request->user();

        if ($user) {
            $activityLogger->log(
                action: 'LOGOUT',
                module: 'Authentication',
                description: 'User logged out.',
                subjectType: $user::class,
                subjectId: $user->id,
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location('/login');
    }
}
