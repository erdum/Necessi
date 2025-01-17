<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AdminService;

class AdminController extends Controller
{
    public function login(Request $request, AdminService $admin)
    {
        $request->validate([
            'email' => 'required|exists:admins,email',
            'password' => 'required'
        ]);

        $response = $admin->login(
            $request->email,
            $request->password,
            $request->fcm_token
        );

        return $response;
    }

    public function forget_password(Request $request, AdminService $admin)
    {
        $request->validate([
            'email' => 'required|exists:admins,email',
        ]);

        $response = $admin->forget_password($request->email);

        return $response;
    }

    public function verify_otp(Request $request, AdminService $admin)
    {
        $request->validate([
            'email' => 'required|exists:admins,email',
            'otp' => 'required|size:4',
        ]);

        $response = $admin->verify_otp($request->email, $request->otp);

        return $response;
    }

    public function update_password(Request $request, AdminService $admin)
    {
        $request->validate([
            'email' => 'required|exists:admins,email',
            'otp' => 'required|size:4',
            'new_password' => 'required',
        ]);

        $response = $admin->update_password(
            $request->email,
            $request->otp,
            $request->new_password
        );

        return $response;
    }

    public function get_dashboard(Request $request, AdminService $admin)
    {
        $response = $admin->get_dashboard();

        return $response;
    }
}
