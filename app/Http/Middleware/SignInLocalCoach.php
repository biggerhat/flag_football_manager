<?php

namespace App\Http\Middleware;

use App\Actions\SeedStarterContent;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SignInLocalCoach
{
    /**
     * Sign every request in as the single local coach when `coach.auto_login` is on.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('coach.auto_login') && ! Auth::check()) {
            $user = User::firstOrCreate(
                ['email' => config('coach.email')],
                ['name' => 'Coach', 'password' => Str::random(40)],
            );

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            if ($user->wasRecentlyCreated) {
                app(SeedStarterContent::class)->handle($user);
            }

            Auth::login($user);
        }

        return $next($request);
    }
}
