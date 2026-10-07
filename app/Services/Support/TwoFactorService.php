<?php

namespace App\Services\Support;

use App\Models\User;
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

    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($index = 0; $index < $count; $index++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }

        return $codes;
    }

    public static function consumeRecoveryCode(User $user, string $code): bool
    {
        $code = strtoupper(trim($code));
        $codes = (array) ($user->two_factor_recovery_codes ?? []);

        $index = array_search($code, $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }
}
