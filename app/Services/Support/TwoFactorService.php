<?php

namespace App\Services\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(
        protected Google2FA $google = new Google2FA,
    ) {}

    public function generateSecret(): string
    {
        return $this->google->generateSecretKey(32);
    }

    public function provisioningUrl(string $company, string $email, string $secret): string
    {
        $this->google->setCompany($company);

        return $this->google->getQRCodeUrl($company, $email, $secret);
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->google->verifyKey($secret, preg_replace('/\s+/', '', $code));
    }

    /** @return array<int, string> Plain recovery codes (show once to the user). */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($index = 0; $index < $count; $index++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }

        return $codes;
    }

    /**
     * Hash plain recovery codes for storage. The plaintext is only shown once.
     *
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    public static function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => Hash::make(strtoupper(trim($code))), $codes);
    }

    public static function consumeRecoveryCode(User $user, string $code): bool
    {
        $code = strtoupper(trim($code));
        $hashes = (array) ($user->two_factor_recovery_codes ?? []);

        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && Hash::check($code, $hash)) {
                unset($hashes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }
}
