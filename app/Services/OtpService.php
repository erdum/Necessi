<?php

namespace App\Services;

use App\Exceptions;
use App\Jobs\SendEmails;
use App\Models\Otp;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Cache;

class OtpService
{
    private function check_abuse(User $user, string $identifier, Closure $send)
    {
        $otp = Otp::where('user_id', $user->id)
            ->where('identifier', $identifier)
            ->orderBy('sent_at', 'desc')
            ->first();

        if ($otp) {

            if ($otp->retries >= config('otp.retries')) {

                $expiry = Carbon::parse($otp->sent_at)->addSeconds(
                    config('otp.retry_duration')
                );
                $is_cool_down = $expiry->isPast();

                if (! $is_cool_down) {
                    $diff_secs = $expiry->diffInSeconds(now());

                    throw new Exceptions\OtpCoolingDown($diff_secs);
                }

                $otp->retries = 0;
                $otp->sent_at = now();
                $otp->verified_at = null;
                $otp->save();

                return [
                    'message' => 'OTP has been successfully sent',
                    'ref' => $send(),
                    'retry_duration' => config('otp.retry_duration'),
                ];
            }

            // Check if user already requested an OTP and it is not expired yet
            $is_expired = Carbon::parse($otp->sent_at)->addSeconds(
                config('otp.expiry_duration')
            )->isPast();

            if (! $is_expired) {
                throw new Exceptions\OtpNotExpired;
            }

            $otp->retries += 1;
            $otp->sent_at = now();
            $otp->verified_at = null;
            $otp->save();
        } else {
            // No previous OTP found, create a new one
            $otp = new Otp;
            $otp->user_id = $user->id;
            $otp->identifier = $identifier;
            $otp->retries = 1;
            $otp->sent_at = now();
            $otp->save();
        }

        return [
            'message' => 'OTP has been successfully sent',
            'ref' => $send(),
            'retry_duration' => config('otp.expiry_duration'),
        ];
    }

    private function mark_verified(string $identifier)
    {
        $otp = Otp::where('identifier', $identifier)->first();

        if (! $otp) {
            return false;
        }
        $otp->verified_at = now();
        $otp->retries = 0;
        $otp->save();

        return true;
    }

    public function send_otp(User $user, string $identifier)
    {
        $receivers = [
            $user->email,
        ];

        return $this->check_abuse(
            $user,
            $identifier,
            function () use ($user, $identifier, $receivers) {
                return $this->send_through_mail(
                    $user,
                    $identifier,
                    $receivers
                );
            }
        );
    }

    private function send_through_mail(
        User $user,
        string $identifier,
        array $emails
    ) {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($identifier, $otp, now()->addSeconds(
            config('otp.expiry_duration')
        ));

        $subject = 'OTP | '.config('app.name');
        $content = "Hello {$user->name},\n\nHere is your One-Time Password (OTP) for authentication:\n\n{$otp}.\n\nPlease use this code to complete your action.\n\nThank you,\n".config('app.name');

        SendEmails::dispatchAfterResponse($subject, $content, $emails);

        return '';
    }

    public function verify_otp(string $identifier, string $otp)
    {
        $is_verified = Cache::get($identifier) == $otp;

        if ($is_verified) {
            $this->mark_verified($identifier);
        }

        return $is_verified;
    }

    public function resend_otp(string $identifier)
    {
        $user = Otp::where('identifier', $identifier)->first()?->user;

        if (! $user) {
            $user = User::where('email', $identifier)->firstOrFail();
        }

        return $this->send_otp($user, $identifier);
    }
}
