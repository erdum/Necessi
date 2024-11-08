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
            'age' => 'nullable|integer|between:10,110',
            'phone_number' => 'nullable|min:10|max:15',
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

    public function get_user_preferences(
        Request $request,
        UserService $user_service
    ) {
        $response = $user_service->get_user_preferences(
            $request->user()
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
            $request->who_can_send_message,
        );

        return response()->json($response);
    }

    public function get_user(
        Request $request,
        UserService $user_service
    ) {
        $user = $user_service->get_profile(
            $request->user(),
        );

        if ($request->user_id) {
            $user_model = User::findOrFail($request->user_id);
            $user = $user_service->get_profile(
                $user_model,
            );
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

    public function accept_connection_request(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $response = $user_service->accept_connection_request(
            $request->user(),
            $request->user_id
        );

        return response()->json($response);
    }

    public function decline_connection_request(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $response = $user_service->decline_connection_request(
            $request->user(),
            $request->user_id,
        );

        return response()->json($response);
    }

    public function remove_connection(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $response = $user_service->remove_connection(
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

    public function send_connection_requests(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $response = $user_service->send_connection_requests(
            $request->user(),
            $request->user_ids
        );

        return response()->json($response);
    }

    public function cancel_connection_request(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $response = $user_service->cancel_connection_request(
            $request->user(),
            $request->user_id
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

    public function get_notifications(
        Request $request,
        UserService $user_service
    ) {
        $response = $user_service->get_notifications($request->user());

        return response()->json($response);
    }

    public function clear_user_notifications(
        Request $request,
        UserService $user_service
    ) {
        $response = $user_service->clear_user_notifications(
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
    ) {
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

    public function block_user(
        string $uid,
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'reason_type' => 'required|in:harassment,spam,scam,privacy violation,inappropriate content,unwanted content,uncomfortable interaction,other',
            'other_reason' => 'required_if:reason_type,other'
        ]);

        $response = $user_service->block_user(
            $request->user(),
            $uid,
            $request->reason_type,
            $request->other_reason
        );

        return response()->json($response);
    }

    public function unblock_user(
        string $uid,
        Request $request,
        UserService $user_service
    ) {
        $response = $user_service->unblock_user(
            $request->user(),
            $uid
        );

        return response()->json($response);
    }

    public function get_blocked_users(
        Request $request,
        UserService $user_service
    ) {
        $response = $user_service->get_blocked_users($request->user());

        return response()->json($response);
    }
}
