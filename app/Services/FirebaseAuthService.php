<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Auth;
use Illuminate\Support\Str;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\UserAlreadyRegisteredException;

class FirebaseAuthService
{
    protected $auth;

    protected $otp_service;

    protected $user_service;

    public function __construct(
        Factory $factory,
        OtpService $otp_service,
        UserService $user_service,
    ) {
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $this->auth = $firebase->createAuth();
        $this->user_service = $user_service;
        $this->otp_service = $otp_service;
    }

    protected function is_user_already_registered(string $phone_number)
    {
        return User::where('phone_number', $phone_number)
            ->whereNotNull('phone_number_verified_at')->first();
    }

    public function register(
        $first_name,
        $last_name,
        $phone_number,
        $email,
        $password
    ){
        // $user = $this->auth->createUser([
        //     'email' => $email,
        //     'password' => $password,
        // ]);

        if ($this->is_user_already_registered($phone_number)) {
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

        $this->user_service->update_firestore_profile($user);

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
}