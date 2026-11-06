<?php

namespace App\Services\Support;

use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * Formats Sanctum plain-text tokens for API consumers. Sanctum prefixes a
 * token with its database id ("{id}|{token}"); we expose only the secret part
 * so clients receive a token without the pipe delimiter. Sanctum still
 * authenticates such tokens by hashing the given string.
 */
class ApiTokenService
{
    public static function plainText(NewAccessToken $token): string
    {
        return Str::after($token->plainTextToken, '|');
    }
}
