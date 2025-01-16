<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseStorageService;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function update_user(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'avatar' => 'nullable',
            'about' => 'nullable|string|max:500',
            'age' => 'nullable|integer|between:10,110',
            'phone_number' => 'nullable|min:10|max:15',
            'lat' => 'nullable|numeric',
            'long' => 'nullable|numeric',
            'location' => 'nullable|string',
        ]);

        $response = $user_service->update_profile(
            $request->user(),
            $request->first_name,
            $request->last_name,
            $request->about,
            $request->age,
            $request->avatar,
            $request->phone_number,
            $request->lat,
            $request->long,
            $request->location,
        );

        return response()->json($response);
    }

    public function delete_user_account(
        Request $request,
        UserService $user_service
    ) {
        $response = $user_service->delete_user_account($request->user());

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
            $user = $user_service->get_profile($user_model);
        }

        return response()->json($user);
    }

    public function set_location(Request $request, UserService $user_service)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'location' => 'required|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
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

        $other_user = User::findOrFail($request->user_id);

        $response = $user_service->accept_connection_request(
            $request->user(),
            $other_user
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

        $other_user = User::findOrFail($request->user_id);

        $response = $user_service->decline_connection_request(
            $request->user(),
            $other_user,
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

        $other_user = User::findOrFail($request->user_id);

        $response = $user_service->remove_connection(
            $request->user(),
            $other_user
        );

        return response()->json($response);
    }

    public function get_connections(Request $request, UserService $user_service)
    {

        if ($request->user_id) {
            $user = User::findOrFail($request->user_id);
        }

        $response = $user_service->get_connections(
            $user ?? $request->user()
        );

        return response()->json($response);
    }

    public function get_chat_users(Request $request, UserService $user_service)
    {
        $response = $user_service->get_chat_users($request->user());

        return response()->json($response);
    }

    public function initiate_chat(
        string $uid,
        Request $request,
        UserService $user_service
    ) {
        $other_user = User::where('uid', $uid)->firstOrFail();

        $response = $user_service->initiate_chat(
            $request->user(),
            $other_user
        );

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

        $other_user = User::findOrFail($request->user_id);

        $response = $user_service->cancel_connection_request(
            $request->user(),
            $other_user
        );

        return response()->json($response);
    }

    public function update_user_fcm(Request $request, UserService $user_service)
    {
        $request->validate([
            'fcm_token' => 'required',
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

    public function create_chat(
        Request $request,
        UserService $user_service
    ){
        $request->validate([
            'other_party_uids' => 'required|array',
            'other_party_uids.*' => 'exists:users,uid',
        ]);

        $response = $user_service->initiate_chats(
            $request->user(),
            $request->other_party_uids,
        );

        return response()->json($response);
    }

    public function handle_uploads(
        Request $request,
        FirebaseStorageService $storage_service
    ) {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file',
        ]);
        $response = $storage_service->handle_uploads($request->file('files'));

        return response()->json($response);
    }

    public function send_message_notificatfion(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'chat_id' => 'required',
            'receiver_uid' => 'required|exists:users,uid',
        ]);

        $other_user = User::where('uid', $request->receiver_uid)->firstOrFail();

        $response = $user_service->send_message_notificatfion(
            $request->user(),
            $other_user,
            $request->chat_id,
            $request?->type ?? 'text'
        );

        return response()->json($response);
    }

    public function block_user(
        string $user_uid,
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'reason_type' => 'required|in:harassment,spam,scam,privacy violation,inappropriate content,unwanted content,uncomfortable interaction,other',
            'other_reason' => 'required_if:reason_type,other',
        ]);

        $other_user = User::where('uid', $user_uid)->firstOrFail();

        $response = $user_service->block_user(
            $request->user(),
            $other_user,
            $request->reason_type,
            $request->other_reason
        );

        return response()->json($response);
    }

    public function unblock_user(
        string $user_uid,
        Request $request,
        UserService $user_service
    ) {
        $other_user = User::where('uid', $user_uid)->firstOrFail();

        $response = $user_service->unblock_user(
            $request->user(),
            $other_user
        );

        return response()->json($response);
    }

    public function report_chat(
        string $chat_id,
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'reason_type' => 'required|in:harassment,spam,fraudulent activity,fake profile,inappropriate content,violation of terms,hate speech,other',
            'other_reason' => 'required_if:reason_type,other',
        ]);

        $response = $user_service->report_chat(
            $request->user(),
            $chat_id,
            $request->reason_type,
            $request->other_reason
        );

        return response()->json($response);
    }

    public function report_user(
        int $user_id,
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'reason_type' => 'required|in:inappropriate behavior,fraudulent activity,harassment or abuse,spam or scamming,violation of platform rules,other',
            'other_reason' => 'required_if:reason_type,other',
        ]);

        $other_user = User::findOrFail($user_id);

        $response = $user_service->report_user(
            $request->user(),
            $other_user,
            $request->reason_type,
            $request->other_reason
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

    public function get_payment_details(
        Request $request,
        UserService $user_service,
    ) {
        $response = $user_service->get_payment_details(
            $request->user(),
        );

        return response()->json($response);
    }

    public function add_payment_card(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'card_id' => 'required|unique:user_cards,id',
            'last_digits' => 'required',
            'expiry_month' => 'required',
            'expiry_year' => 'required',
            'brand_name' => 'required',
        ]);

        $response = $user_service->add_payment_card(
            $request->user(),
            $request->card_id,
            $request->last_digits,
            $request->expiry_month,
            $request->expiry_year,
            $request->brand_name
        );

        return response()->json($response);
    }

    public function update_payment_card(
        string $card_id,
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'expiry_month' => 'nullable|size:2',
            'expiry_year' => 'nullable|size:4',
        ]);

        $response = $user_service->update_payment_card(
            $request->user(),
            $card_id,
            $request->expiry_month,
            $request->expiry_year
        );

        return response()->json($response);
    }

    public function delete_payment_card(
        string $card_id,
        Request $request,
        UserService $user_service
    ) {
        $response = $user_service->delete_payment_card(
            $request->user(),
            $card_id,
        );

        return response()->json($response);
    }

    public function add_bank_details(
        Request $request,
        UserService $user_service
    ) {
        $request->validate([
            'account_number' => 'required',
            'routing_number' => 'required',
            'bank_name' => 'required',
            'holder_name' => 'required',
        ]);

        $response = $user_service->add_bank(
            $request->user(),
            $request->account_number,
            $request->routing_number,
            $request->bank_name,
            $request->holder_name
        );

        return response()->json($response);
    }

    public function update_bank_details(
        string $bank_id,
        Request $request,
        UserService $user_service
    ) {}

    public function delete_bank_account(
        string $bank_id,
        Request $request,
        UserService $user_service
    ) {
        $response = $user_service->delete_bank(
            $request->user(),
            $bank_id,
        );

        return response()->json($response);
    }

    public function get_onboarding_link(
        Request $request,
        UserService $user_service
    ) {
        $response = $user_service->get_onboarding_link($request->user());

        return response()->json($response);
    }

    public function withdraw_funds(Request $request, UserService $user_service)
    {
        $request->validate([
            'bank_id' => 'required|exists:user_banks,id',
            'amount' => 'required',
        ]);

        $response = $user_service->withdraw_funds(
            $request->user(),
            $request->bank_id,
            $request->amount
        );

        return response()->json($response);
    }

    public function get_user_funds(Request $request, UserService $user_service)
    {
        $request->validate([
            'year' => 'nullable|date_format:"Y"',
            'month' => 'nullable|date_format:"n"',
        ]);

        $response = $user_service->get_user_funds(
            $request->user(),
            $request->year ?? date('Y'),
            $request->month
        );

        return response()->json($response);
    }
}
