<?php

namespace App\Services;

use App\Exceptions;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Factory;

class FirebaseAuthService
{
    protected $auth;

    protected $otp_service;

    protected $user_service;

    protected $notification;

    public function __construct(
        Factory $factory,
        OtpService $otp_service,
        UserService $user_service,
        FirebaseNotificationService $notification
    ) {
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $this->auth = $firebase->createAuth();
        $this->user_service = $user_service;
        $this->otp_service = $otp_service;
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
                'uid' => Str::random(28),
            ]
        );

        return $this->otp_service->send_otp($user, $email);
    }

    public function generate_token(User $user)
    {
        $user->tokens()->delete();

        return $user->createToken($user->uid)->plainTextToken;
    }

    public function verify_email(string $email, string $otp)
    {
        $verified = $this->otp_service->verify_otp($email, $otp);

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

        return $this->otp_service->resend_otp($email);
    }

    public function reset_password(string $email, string $password)
    {
        $user = $this->is_user_already_registered($email);

        if (! $user) {
            throw new Exceptions\UserNotFound;
        }

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
            $verifiedIdToken = $this->auth->verifyIdToken($token);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');
            $email_verified_at = now();

            $user = User::updateOrCreate(
                ['uid' => $firebaseUid],
                [
                    'email' => $verifiedIdToken->claims()->get('email'),
                    'first_name' => $verifiedIdToken->claims()->get('name') ?? '',
                    'last_name' => $verifiedIdToken->claims()->get('family_name') ?? '',
                ]
            );

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
                'token' => $token,
                'uid' => $user->uid,
                'user_details' => $user,
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
