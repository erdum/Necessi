<?php

namespace App\Http\Controllers;

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
            'phone_number' => 'required',
            'email' => 'required',
            'password' => 'required|min:6',
        ]);

        $response = $this->auth_service->register(
            $request->first_name,
            $request->last_name,
            $request->phone_number,
            $request->email,
            $request->password,
        );

        return response()->json($response, 201);
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
            'password' => 'required',
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
            'email' => 'required',
            'password' => 'required',
        ]);
        $response = $this->auth_service->login(
            $request->email,
            $request->password,
            null,
        );

        return response()->json($response);
    }

    public function google_auth(Request $request)
    {
        $request->validate([
            'token' => 'required',
        ]);

        $response = $this->auth_service->google_auth($request->token);

        return response()->json($response);
    }
}
