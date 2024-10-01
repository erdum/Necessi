<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserService;

class UserController extends Controller
{
    public function update_user(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'about' => 'nullable|string|max:500',
            'gender' => 'required|in:male,female,non-binary',
            'age' => 'required|integer|between:18,60',
        ]);

        $response = $user_service->update_profile(
            $request->user(),
            $request->about,
            $request->gender,
            $request->age,
            $request->avatar,
        );

        return response()->json($response);
    }
}
