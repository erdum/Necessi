<?php

namespace App\Services;

use App\Exceptions;

class OtpService
{
    public static function send(
        string $identifier,
        string $value,
        callable $send
    ): mixed {
        $otp = cache($identifier);

        // OTP cool downed
        if (! $otp) {
            cache(
                [$identifier => [
                    'retries' => 0,
                    'sent_at' => now(),
                    'expires_at' => now()->addSeconds(config('otp.expiry_duration')),
                    'verified_at' => null,
                ]],
                now()->addSeconds(config('otp.retry_duration'))
            );

            cache(
                [$value => $identifier],
                now()->addSeconds(config('otp.expiry_duration'))
            );

            return $send($value);
        }

        if ($otp['retries'] >= config('otp.retries')) {
            throw new Exceptions\BaseException(
                'Too many OTP\'s requested, try again after: '
                .$otp['sent_at']->addSeconds(config('otp.retry_duration'))->diffInSeconds(now()),
                429
            );
        }

        if ($otp['expires_at']->isFuture()) {
            throw new Exceptions\BaseException(
                'Recently OTP requested, try again after: '.$otp['expires_at']->diffInSeconds(now()),
                429
            );
        }

        cache(
            [$identifier => [
                'retries' => $otp['retries'] + 1,
                'sent_at' => now(),
                'expires_at' => now()->addSeconds(config('otp.expiry_duration')),
                'verified_at' => null,
            ]],
            now()->addSeconds(config('otp.retry_duration'))
        );

        cache(
            [$value => $identifier],
            now()->addSeconds(config('otp.expiry_duration'))
        );

        return $send($value);
    }

    public static function verify(string $value): bool|string
    {
        $identifier = cache($value);

        if ($identifier) {
            $otp = cache($identifier);

            if ($otp) {

                if (self::is_expired($identifier)) return false;

                cache(
                    [$identifier => [
                        ...$otp,
                        'verified_at' => now(),
                        'retries' => 0,
                    ]],
                    now()->addSeconds(config('otp.retry_duration'))
                );

                return $identifier;
            }
        }

        return false;
    }

    public static function is_verified(string $identifier): bool
    {
        $otp = cache($identifier);

        return $otp ? $otp['verified_at'] ?? false : false;
    }

    public static function is_expired(string $identifier): bool
    {
        $otp = cache($identifier);

        return $otp ? $otp['expires_at']->isPast() : false;
    }

    public static function retries_left(string $identifier): bool|int
    {
        $otp = cache($identifier);

        return $otp ? (config('otp.retries') - $otp['retries']) : false;
    }
}