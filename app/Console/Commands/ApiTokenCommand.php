<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ApiTokenCommand extends Command
{
    protected $signature = 'api:token {email : User email} {--name=api-token : Token name} {--abilities=* : Permission slugs; defaults to user permissions} {--expires= : Expiry in days, empty for never}';

    protected $description = 'Create a Sanctum personal access token for a user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("User {$this->argument('email')} not found.");
            return 1;
        }

        $abilities = array_filter((array) $this->option('abilities'));

        if ($abilities === []) {
            $abilities = $user->permissionSlugs();
        }

        $expiresAt = $this->option('expires') !== null
            ? now()->addDays((int) $this->option('expires'))
            : null;

        $name = (string) $this->option('name');

        if (Str::length($name) < 1) {
            $name = 'api-token';
        }

        $token = $user->createToken($name, $abilities, $expiresAt);

        $this->info("Token for {$user->email}:");
        $this->line($token->plainTextToken);
        $this->info('Abilities: '.implode(', ', $abilities ?: ['*']));

        if ($expiresAt) {
            $this->info('Expires at: '.$expiresAt->toDateTimeString());
        }

        return 0;
    }
}
