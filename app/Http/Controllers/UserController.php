<?php

namespace App\Http\Controllers;

use App\Models\User;
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

    public function update_preferences(
        Request $request,
        UserService $user_service
    ) {
        $response = $user_service->update_preferences(
            $request->user(),
            $request->general_notification,
            $request->biding_notification,
            $request->transaction_notification,
            $request->activity_notification,
            $request->receive_message_notification,
            $request->who_can_see_connection,
        );

        return response()->json($response);
    }

    public function get_user(
        Request $request,
        UserService $user_service
    ) {
        if ($request->user_id) {
            $user_model = User::findOrFail($request->user_id);
            $user = $user_service->get_profile(
                $user_model
            );
        }
        
        $user = $user_service->get_profile(
            $request->user(),
        );

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

    public function make_connection(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'user_id' => 'exists:users,id',
        ]);

        $response = $user_service->make_connection(
            $request->user(),
            $request->user_id
        );

        return response()->json($response);
    }

    public function request_decline(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $response = $user_service->request_decline(
            $request->user(),
            $request->user_id,
        );

        return response()->json($response);
    }

    public function user_remove(
        Request $request,
        UserService $user_service
    ) {
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

    public function send_connection_request(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'receiver_ids.*' => 'required|exists:users,id',
        ]);

        $response = $user_service->send_connection_request(
            $request->user(),
            $request->receiver_ids
        );

        return response()->json($response);
    }

    public function cancel_connection_request(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
        ]);

        $response = $user_service->cancel_connection_request(
            $request->user(),
            $request->receiver_id
        );

        return response()->json($response);
    }

    public function update_user_fcm(Request $request, UserService $user_service)
    {
        $request->validate([
            'fcm_token' => 'required|unique:user_notification_devices,fcm_token',
        ]);

        $response = $user_service->store_fcm(
            $request->fcm_token,
            $request->user()
        );

        return response()->json($response);
    }

    public function get_connection_requests(
        Request $request,
        UserService $user_service
    ) {
        $response = $user_service->get_connection_requests(
            $request->user(),
        );

        return response()->json($response);
    }

    public function update_password(
        Request $request, 
        UserService $user_service
    ){
        $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required',
        ]);

        $response = $user_service->update_password(
            $request->user(),
            $request->old_password,
            $request->new_password,
        );

        return response()->json($response);
    }
}
