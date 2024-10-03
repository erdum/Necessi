<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\UserService;

class UserController extends Controller
{
    public function update_user(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'avatar' => 'nullable|image|max:2048',
            'about' => 'nullable|string|max:500',
            'gender' => 'nullable|in:male,female,non-binary',
            'age' => 'nullable|integer',
            'phone_number' => 'nullable',
        ]);

        $response = $user_service->update_profile(
            $request->user(),
            $request->about,
            $request->gender,
            $request->age,
            $request->avatar,
            $request->phone_number,
        );

        return response()->json($response);
    }

    public function get_user(Request $request, UserService $user_service)
    {
        $user = $user_service->get_profile(
            $request->user()->uid
        );

        if ($request->user_uid) {
            $user = $user_service->get_profile($request->user_uid);
        }

        return response()->json($user);
    }

    public function set_location(Request $request, UserService $user_service)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'location' => 'required|string',
        ]);

        $response = $user_service->set_location(
            $request->user(),
            $request->lat,
            $request->long,
            $request->location,
        );

        return response()->json($response);
    }

    public function get_nearby_users(
        Request $request,
        UserService $user_service
    )
    {
        $user = $request->user();

        $response = $user_service->get_nearby_users($user);

        return response()->json($response);
    }

    public function make_connections(
        Request $request,
        UserService $user_service
    )
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        $response = $user_service->make_connections(
            $request->user(),
            $request->user_ids
        );

        return response()->json($response);
    }

    public function get_connections(Request $request, UserService $user_service)
    {
        $response = $user_service->get_connections($request->user());

        return response()->json($response);
    }
}
