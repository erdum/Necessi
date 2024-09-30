<?php

namespace App\Services;

use App\Exceptions\UserAlreadyRegisteredException;
use App\Exceptions\UserNotFoundException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Kreait\Firebase\Auth;
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
        $first_name,
        $last_name,
        $phone_number,
        $email,
        $password
    ) {
        // $user = $this->auth->createUser([
        //     'email' => $email,
        //     'password' => $password,
        // ]);

        if ($this->is_user_already_registered($email)) {
            throw new UserAlreadyRegisteredException;
        }

        $user = User::updateOrCreate(
            [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone_number' => $phone_number,
                'email' => $email,
                'password' => Hash::make($password),
                'uid' => Str::random(28),
            ]
        );

        $this->auth->createUser([
            'email' => $email,
            'password' => $password,
            'uid' => $user->uid,
        ]);

        // $this->user_service->update_firestore_profile($user);

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
            throw new \Exception("Invalid or expired OTP $otp", 400);
        }
        $user = User::where('email', $email)->first();

        if ($user->email_verified_at == null) {
            $user->email_verified_at = now();
            $user->save();
        }

        $token = $this->generate_token($user);
        $this->user_service->update_firestore_profile($user);

        return [
            'message' => 'Email is successfully verified',
            'token' => $token,
            'uid' => $user->uid,
        ];
    }

    public function resend_otp(string $phone_number)
    {
        return $this->otp_service->resend_otp($phone_number);
    }

    public function reset_password(string $email, string $password)
    {
        $user = $this->is_user_already_registered($email);

        if (! $user) {
            throw new \Exception('User not found', 404);
        }

        if ($user->email_verified_at == null) {
            throw new \Exception('Email is not verified', 400);
        }

        if (!$user->user_otp || $user->user_otp->verified_at === null) {
            throw new \Exception('OTP not found or not verified', 400);
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
            throw new UserNotFoundException;
        }

        if ($user->email_verified_at == null) {
            throw new \Exception('Email is not verified', 400);
        }

        if (!$user->user_otp || $user->user_otp->verified_at === null) {
            throw new \Exception('OTP not found or not verified', 400);
        }

        if (! \Hash::check($password, $user->password)) {
            throw new \Exception('Invalid credentials', 401);
        }

        if ($fcm_token != null) {
            $this->notification->store_fcm_token($user, $fcm_token);
        }

        $token = $this->generate_token($user);

        return [
            'message' => 'Login successful',
            'uid' => $user->uid,
            'token' => $token,
        ];
    }

    public function google_auth($token)
    {
        try {
            $verifiedIdToken = $this->auth->verifyIdToken($token);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');
            $email_verified_at = now();

            $user = User::firstOrCreate(
                ['uid' => $firebaseUid],
                [
                    'first_name' => $verifiedIdToken->claims()->get('name'),
                    'last_name' => $verifiedIdToken->claims()->get('family_name'),
                    'email' => $verifiedIdToken->claims()->get('email'),
                    'email_verified_at' => $email_verified_at,
                    'avatar' => $verifiedIdToken->claims()->get('picture'),
                    'uid' => $firebaseUid,
                ]
            );

            $token = $this->generate_token($user);
            // $this->user_service->update_firestore_profile($user);

            return [
                'token' => $token,
                'uid' => $user->uid,
            ];

        } catch (\Kreait\Firebase\Exception\Auth\InvalidToken $e) {
            return response()->json(['error' => 'Invalid ID token.'], 401);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
