<?php

namespace App\Services;

use App\Exceptions;
use App\Jobs\SendEmails;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class FirebaseAuthService
{
    protected $auth;

    protected $user_service;

    protected $notification;

    public function __construct(
        UserService $user_service,
        FirebaseNotificationService $notification
    ) {
        $this->auth = app('firebase')->createAuth();
        $this->user_service = $user_service;
        $this->notification = $notification;
    }

    protected function is_user_already_registered(string $email)
    {
        return User::where('email', $email)->first();
    }

    public function register(
        string $first_name,
        string $last_name,
        string $phone_number,
        string $email,
        string $password
    ) {
        $user = $this->is_user_already_registered($email);

        if ($user && $user->email_verified_at != null) {
            throw new Exceptions\UserAlreadyRegistered;
        }

        $user = User::updateOrCreate(
            [
                'email' => $email,
            ],
            [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone_number' => preg_replace('/^\+1/', '', $phone_number),
                'password' => Hash::make($password),
                'uid' => $user->uid ?? str()->random(28),
            ]
        );

        OtpService::send(
            $user->email,
            mt_rand(100000, 999999),
            function ($otp) use ($user) {
                $subject = 'OTP | '.config('app.name');
                $content = "Hello {$user->full_name},\n\nHere is your One-Time Password (OTP) for authentication:\n\n{$otp}.\n\nPlease use this code to complete your action.\n\nThank you,\n".config('app.name');

                SendEmails::dispatchAfterResponse(
                    $subject,
                    $content,
                    [$user->email]
                );
            }
        );

        return [
            'message' => 'OTP has been successfully sent',
            'retry_duration' => config('otp.expiry_duration'),
        ];
    }

    public function generate_token(User $user)
    {
        $user->tokens()->delete();

        return $user->createToken($user->uid)->plainTextToken;
    }

    public function verify_email(string $email, string $otp)
    {
        $verified = OtpService::verify($otp);

        if (! $verified) {
            throw new Exceptions\InvalidOtp;
        }
        $user = User::where('email', $email)->first();

        if ($user->email_verified_at == null) {
            $user->email_verified_at = now();
            $user->save();
        }

        $this->user_service->update_preferences(
            $user,
            true,
            true,
            true,
            true,
            true,
            null,
            null
        );

        $token = $this->generate_token($user);

        return [
            'message' => 'Email is successfully verified',
            'token' => $token,
            'uid' => $user->uid,
            'user' => $user,
        ];
    }

    public function resend_otp(string $email)
    {
        $user = $this->is_user_already_registered($email);

        if (! $user) {
            throw new Exceptions\BaseException(
                'User is not registered', 400
            );
        }

        OtpService::send(
            $user->email,
            mt_rand(100000, 999999),
            function ($otp) use ($user) {
                $subject = 'OTP | '.config('app.name');
                $content = "Hello {$user->full_name},\n\nHere is your One-Time Password (OTP) for authentication:\n\n{$otp}.\n\nPlease use this code to complete your action.\n\nThank you,\n".config('app.name');

                SendEmails::dispatchAfterResponse(
                    $subject,
                    $content,
                    [$user->email]
                );
            }
        );

        return [
            'message' => 'OTP has been successfully resent',
            'retry_duration' => config('otp.expiry_duration'),
        ];
    }

    public function reset_password(string $email, string $password)
    {
        $user = $this->is_user_already_registered($email);

        if ($user->email_verified_at == null) {
            throw new Exceptions\EmailNotVerified;
        }

        if (! $user->user_otp || $user->user_otp->verified_at === null) {
            throw new Exceptions\InvalidOtp;
        }

        $user->update([
            'password' => Hash::make($password),
        ]);

        return [
            'message' => 'Password updated successfully.',
        ];
    }

    public function login(string $email, string $password, ?string $fcm_token)
    {
        $user = $this->is_user_already_registered($email);

        if (! $user) {
            throw new Exceptions\BaseException(
                'This email is not registered. Please sign up or use a registered email', 400
            );
        }

        if (! Hash::check($password, $user->password)) {
            throw new Exceptions\BaseException(
                'Incorrect password', 400
            );
        }

        if ($user->email_verified_at == null) {
            throw new Exceptions\EmailNotVerified;
        }

        if ($fcm_token != null) {
            $this->notification->store_fcm_token($user, $fcm_token);
        }

        $token = $this->generate_token($user);

        return [
            'message' => 'Login successful',
            'uid' => $user->uid,
            'token' => $token,
            'user_details' => $user,
        ];
    }

    public function social_auth(string $token)
    {
        try {
            $verified_id_token = $this->auth->verifyIdToken($token);
            $firebase_uid = $verified_id_token->claims()->get('sub');
            $email_verified_at = now();

            $user = User::firstOrNew(
                ['email' => $verified_id_token->claims()->get('email')],
                [
                    'uid' => $firebase_uid,
                    'first_name' => $verified_id_token->claims()->get('name') ?? 'Necessi User',
                    'last_name' => $verified_id_token->claims()->get('family_name') ?? '',
                ]
            );

            if ($firebase_uid != $user->uid) {
                $firebase_user = $this->auth->getUserByEmail($user->email);

                if ($firebase_user) {
                    $this->auth->deleteUser($firebase_uid);
                    $this->auth->createUser([
                        'email' => $user->email,
                        'uid' => $user->uid,
                    ]);
                }
            }

            if ($user->email_verified_at == null) {
                $user->email_verified_at = now();
                $user->save();
            }

            if (! $user->preferences) {
                $this->user_service->update_preferences(
                    $user,
                    true,
                    true,
                    true,
                    true,
                    true,
                    null,
                    null
                );
            }

            $token = $this->generate_token($user);

            return [
                'message' => 'Login successful',
                'uid' => $user->uid,
                'token' => $token,
                'user_details' => User::find($user->id),
            ];

        } catch (FailedToVerifyToken $e) {
            throw new Exceptions\InvalidIdToken;
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), 500);
        }
    }

    public function logout(User $user)
    {
        $user->tokens()->delete();
        $user->notification_device()->delete();
        $user->save();

        return ['message' => 'User successfully logged out'];
    }
}
