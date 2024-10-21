<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function update_user(
        Request $request,
        UserService $user_service
    ) {     
        $request->validate([
            'avatar' => 'nullable|image',
            'about' => 'nullable|string|max:500',
            'age' => 'nullable|integer',
            'phone_number' => 'nullable',
            'lat' => 'nullable|numeric',
            'long' => 'nullable|numeric',
        ]);

        $response = $user_service->update_profile(
            $request->user(),
            $request->about,
            $request->age,
            $request->avatar,
            $request->phone_number,
            $request->lat,
            $request->long,
        );

        return response()->json($response);
    }

    public function get_user(Request $request, UserService $user_service)
    {
        $user = $user_service->get_profile(
            $request->user(),
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
            'city' => 'required|string',
            'state' => 'required|string',
        ]);

        $response = $user_service->set_location(
            $request->user(),
            $request->lat,
            $request->long,
            $request->location,
            $request->city,
            $request->state,
        );

        return response()->json($response);
    }

    public function get_nearby_users(
        Request $request,
        UserService $user_service
    ) {
        $user = $request->user();

        $response = $user_service->get_nearby_users($user);

        return response()->json($response);
    }

    public function make_connections(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $response = $user_service->make_connections(
            $request->user(),
            $request->user_ids
        );

        return response()->json($response);
    }

    public function user_remove(
        Request $request, 
        UserService $user_service
    ){
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $response = $user_service->user_remove(
            $request->user(),
            $request->user_id
        );

        return response()->json($response);
    }

    public function get_connections(Request $request, UserService $user_service)
    {
        $response = $user_service->get_connections($request->user());

        return response()->json($response);
    }
}
