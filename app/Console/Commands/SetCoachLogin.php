<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('coach:set-login {email : The email address to log in with} {--name= : Display name} {--password= : Password (prompted when omitted)}')]
#[Description('Turn the local auto-login coach into a regular account, keeping all of its data')]
class SetCoachLogin extends Command
{
    public function handle(): int
    {
        $user = User::where('email', config('coach.email'))->first();

        if (! $user) {
            $this->components->error('There is no local coach account to convert.');

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');

        if (User::where('email', $email)->whereKeyNot($user->id)->exists()) {
            $this->components->error("{$email} is already used by another account.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('Password (min 8 characters)');

        if (strlen((string) $password) < 8) {
            $this->components->error('The password needs at least 8 characters.');

            return self::FAILURE;
        }

        $user->forceFill([
            'email' => $email,
            'name' => $this->option('name') ?: $user->name,
            'password' => $password,
        ])->save();
        $user->markEmailAsVerified();

        $this->components->info("You can now log in as {$email}. Set COACH_AUTO_LOGIN=false in .env to require it.");

        return self::SUCCESS;
    }
}
