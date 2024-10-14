<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseAuthService;
use Illuminate\Http\Request;

class FirebaseAuthController extends Controller
{
    protected $auth_service;

    public function __construct(FirebaseAuthService $auth_service)
    {
        $this->auth_service = $auth_service;
    }

    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'phone_number' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $response = $this->auth_service->register(
            $request->first_name,
            $request->last_name,
            $request->phone_number,
            $request->email,
            $request->password,
        );

        return response()->json($response, 200);
    }

    public function verify_email(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'otp' => 'required|size:6',
        ]);

        $response = $this->auth_service->verify_email(
            $request->email,
            $request->otp
        );

        return response()->json($response);
    }

    public function resend_otp(Request $request)
    {
        $request->validate([
            'email' => 'required|exists:users,email',
        ]);

        $response = $this->auth_service->resend_otp($request->email);

        return response()->json($response);
    }

    public function reset_password(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|confirmed',
        ]);

        $response = $this->auth_service->reset_password(
            $request->email,
            $request->password
        );

        return response()->json($response);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $response = $this->auth_service->login(
            $request->email,
            $request->password,
            null,
        );

        return response()->json($response);
    }

    public function social_auth(Request $request)
    {
        $request->validate([
            'token' => 'required',
        ]);

        $response = $this->auth_service->social_auth($request->token);

        return response()->json($response);
    }

    public function dev_login(Request $request)
    {
        $user = User::find($request->id);
        $token = $user->createToken($user->email)->plainTextToken;

        return response()->json(['token' => $token]);
    }
}
