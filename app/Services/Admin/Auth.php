<?php

namespace App\Services\Admin;

use App\Exceptions;
use App\Models\Admin as AdminModel;
use App\Services\OtpService;
use Illuminate\Support\Facades\Hash;
use App\Jobs\SendEmails;

class Auth
{
    public static function register(
        string $name,
        string $email,
        string $password
    ) {}

    public static function login(
        string $email,
        string $password,
        ?string $fcm_token
    ) {
        $admin = AdminModel::where('email', $email)->firstOrFail();

        if (! Hash::check($password, $admin->password)) {
            throw new Exceptions\BaseException('Incorrect password', 400);
        }

        if ($fcm_token) {
            $admin->fcm_token = $fcm_token;
            $admin->save();
        }

        $admin->tokens()->delete();
        $token = $admin->createToken($admin->email)->plainTextToken;

        return [
            'message' => 'Login successful',
            'token' => $token,
            'admin_details' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'avatar' => $admin?->avatar,
            ],
        ];
    }

    public static function forget_password(string $email)
    {
        $admin = AdminModel::where('email', $email)->firstOrFail();

        OtpService::send(
            $admin->email,
            mt_rand(1000, 9999),
            function ($otp) use ($admin) {
                $subject = 'OTP | '.config('app.name');
                $content = "Hello {$admin->name},\n\nHere is your One-Time Password (OTP) for authentication:\n\n{$otp}.\n\nPlease use this code to complete your action.\n\nThank you,\n".config('app.name');

                SendEmails::dispatchAfterResponse(
                    $subject,
                    $content,
                    [$admin->email]
                );
            }
        );

        return [
            'message' => 'OTP has been successfully sent',
            'retry_duration' => config('otp.expiry_duration'),
        ];
    }

    public static function verify_otp(string $email, string $otp)
    {
        $admin = AdminModel::where('email', $email)->firstOrFail();

        $verified = OtpService::verify($otp);

        return ['is_verified' => $verified ? true : false];
    }

    public static function update_password(
        string $email,
        string $otp,
        string $new_password
    ) {
        $admin = AdminModel::where('email', $email)->firstOrFail();

        $verified = OtpService::verify($otp);

        if (! $verified) {
            throw new Exceptions\InvalidOtp;
        }

        $admin->password = Hash::make($new_password);
        $admin->save();

        OtpService::clear_otp($email);

        return [
            'message' => 'Password has been successfully reset'
        ];
    }
}
