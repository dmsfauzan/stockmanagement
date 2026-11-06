<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * Formats Sanctum plain-text tokens for API consumers and manages the
 * short-lived challenge token used between the password step and the
 * two-factor step of an API login.
 *
 * Sanctum prefixes a token with its database id ("{id}|{token}"); we expose
 * only the secret part so clients receive a token without the pipe
 * delimiter. Sanctum still authenticates such tokens by hashing the given
 * string.
 */
class ApiTokenService
{
    private const CHALLENGE_TTL_SECONDS = 300;

    private const CHALLENGE_MAX_ATTEMPTS = 5;

    public static function plainText(NewAccessToken $token): string
    {
        return Str::after($token->plainTextToken, '|');
    }

    /**
     * Issue a single-use, short-lived token that proves the password step
     * already succeeded. The raw token is never stored (only its hash).
     */
    public static function issueTwoFactorChallenge(int $userId, int $ttlSeconds = self::CHALLENGE_TTL_SECONDS): string
    {
        $token = Str::random(64);

        Cache::put(static::challengeKey($token), [
            'user_id' => $userId,
            'attempts' => 0,
        ], now()->addSeconds($ttlSeconds));

        return $token;
    }

    /**
     * @return array{user_id:int, attempts:int}|null
     */
    public static function resolveTwoFactorChallenge(string $token): ?array
    {
        $record = Cache::get(static::challengeKey($token));

        if (! is_array($record) || ! isset($record['user_id'])) {
            return null;
        }

        return [
            'user_id' => (int) $record['user_id'],
            'attempts' => (int) ($record['attempts'] ?? 0),
        ];
    }

    /**
     * Count a failed verification attempt and return the new total.
     */
    public static function registerTwoFactorAttempt(string $token): int
    {
        $key = static::challengeKey($token);
        $record = Cache::get($key);

        if (! is_array($record)) {
            return 0;
        }

        $attempts = (int) ($record['attempts'] ?? 0) + 1;
        $record['attempts'] = $attempts;

        Cache::put($key, $record, now()->addSeconds(self::CHALLENGE_TTL_SECONDS));

        return $attempts;
    }

    public static function forgetTwoFactorChallenge(string $token): void
    {
        Cache::forget(static::challengeKey($token));
    }

    public static function maxTwoFactorAttempts(): int
    {
        return self::CHALLENGE_MAX_ATTEMPTS;
    }

    private static function challengeKey(string $token): string
    {
        return 'api:two-factor:'.hash('sha256', $token);
    }
}
