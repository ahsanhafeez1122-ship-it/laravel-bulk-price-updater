<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('prices:token {email : The user the token belongs to} {--name=api : A label for the token}')]
#[Description('Create a Sanctum API token for the price import API')]
class CreateApiToken extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $token = $user->createToken((string) $this->option('name'))->plainTextToken;

        $this->line('Token (shown once, store it safely):');
        $this->info($token);

        return self::SUCCESS;
    }
}
