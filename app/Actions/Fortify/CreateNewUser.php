<?php

namespace App\Actions\Fortify;

use App\Actions\SeedStarterContent;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Whether this email address may create an account right now.
     */
    public static function mayRegister(string $email): bool
    {
        if (config('coach.registration') === 'open' || ! User::query()->exists()) {
            return true;
        }

        return TeamInvitation::query()
            ->whereRaw('lower(email) = ?', [strtolower($email)])
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->after(function ($validator) use ($input) {
            if (! self::mayRegister((string) ($input['email'] ?? ''))) {
                $validator->errors()->add('email', __('Registration is by invitation only. Use the address your invitation was sent to.'));
            }
        })->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        if (! config('coach.verify_email')) {
            $user->markEmailAsVerified();
        }

        app(SeedStarterContent::class)->handle($user);

        return $user;
    }
}
