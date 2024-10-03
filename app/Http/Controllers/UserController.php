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

    public function get_nearby_users(Request $request)
    {
        $current_user = $request->user();
        $users = User::select(
            'id',
            'first_name',
            'last_name',
            'uid',
            'email',
            'phone_number',
            'avatar',
            'gender',
            'age',
            'about',
            'lat',
            'long',
            'location'
        )->whereNot('id', $current_user->id)->get();
        $nearby_users = [];

        foreach ($users as $user) {
            $distance = $this->haversineDistance(
                $current_user->lat,
                $current_user->long,
                $user->lat,
                $user->long
            );
            
            if ($distance <= 50) {
                $nearby_users[] = $user;
            }
        }

        array_slice($nearby_users, 0, 9);
        return response()->json($nearby_users);
    }

    private function haversineDistance($lat1, $long1, $lat2, $long2) {
        $earth_radius = 6371;

        $lat1 = deg2rad($lat1);
        $long1 = deg2rad($long1);
        $lat2 = deg2rad($lat2);
        $long2 = deg2rad($long2);

        // Haversine formula
        $d_lat = $lat2 - $lat1;
        $d_long = $long2 - $long1;

        $a = sin($d_lat / 2) * sin($d_lat / 2) + cos($lat1) * cos($lat2) * sin($d_long / 2) * sin($d_long / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earth_radius * $c;

        return $distance;
    }
}
