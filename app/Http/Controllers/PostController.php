<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PostService;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function create_post(Request $request,
        PostService $post_service
    ) {
        $request->validate([
            'title' => 'required|string',
            'description' => 'required|string|max:1000',
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'city' => 'required|string',
            'state' => 'required|string',
            'location' => 'required',
            'budget' => 'required|gt:5',
            'type' => 'required|string|in:item,service',
            'start_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'end_date' => 'required|date_format:Y-m-d|after:start_date',
            'start_time' => 'nullable|date_format:H:i:s|required_if:type,service',
            'end_time' => 'nullable|date_format:H:i:s|required_if:type,service',
            'request_delivery' => 'nullable',
            'avatar.*' => 'nullable',
        ]);

        $avatars = $request->file('avatar');

        if ($avatars && ! is_array($avatars)) {
            $avatars = [$avatars];
        }

        $response = $post_service->create_post(
            $request->user(),
            $request->title,
            $request->description,
            $request->lat,
            $request->long,
            $request->city,
            $request->state,
            $request->location,
            $request->budget,
            $request->start_date,
            $request->end_date,
            $request->start_time,
            $request->end_time,
            $request->request_delivery,
            $request->type,
            $avatars
        );

        return response()->json($response);
    }

    public function place_bid(
        int $post_id,
        Request $request,
        PostService $post_service
    ) {
        $request->validate([
            'amount' => 'required|integer',
        ]);

        $response = $post_service->place_bid(
            $request->user(),
            $post_id,
            $request->amount,
        );

        return response()->json($response);
    }

    public function accept_post_bid(
        int $bid_id,
        Request $request,
        PostService $post_service
    ) {
        $response = $post_service->accept_post_bid(
            $request->user(),
            $bid_id
        );

        return response()->json($response);
    }

    public function decline_post_bid(
        int $bid_id,
        Request $request,
        PostService $post_service
    ) {
        $response = $post_service->decline_post_bid(
            $request->user(),
            $bid_id
        );

        return response()->json($response);
    }

    public function get_user_posts(
        Request $request,
        PostService $post_service,
    ) {
        $user = $post_service->get_user_posts(
            $request->user(),
        );

        if ($request->user_id) {
            $user_model = User::findOrFail($request->user_id);
            $user = $post_service->get_user_posts(
                $user_model
            );
        }

        return response()->json($user);
    }

    public function get_user_posts_reviews(
        Request $request,
        PostService $post_service
    ) {
        $response = $post_service->get_user_posts_reviews(
            $request->user(),
            $request?->user_id,
            $request?->filter_rating
        );

        return response()->json($response);
    }

    public function get_user_review(
        Request $request,
        PostService $post_service,
        int $post_id
    ) {
        $response = $post_service->get_user_review(
            $request->user(),
            $post_id,
        );

        return response()->json($response);
    }

    public function place_post_review(
        Request $request,
        PostService $post_service,
    ) {
        $request->validate([
            'post_id' => 'required|integer',
            'description' => 'required|string',
            'rating' => 'required|integer',
        ]);
        $response = $post_service->place_post_review(
            $request->user(),
            $request->post_id,
            $request->description,
            $request->rating
        );

        return response()->json($response);
    }

    public function get_all_posts(
        Request $request,
        PostService $post_service,
    ) {
        $response = $post_service->get_all_posts(
            $request->user(),
        );

        return response()->json($response);
    }

    public function search_all(
        Request $request,
        PostService $post_service
    ) {
        $request->validate([
            'search_query' => 'required|string',
        ]);

        $response = $post_service->search_all(
            $request->user(),
            $request->search_query,
        );

        return response()->json($response);
    }

    public function toggle_like(
        int $post_id,
        Request $request,
        PostService $post_service,
    ) {
        $response = $post_service->toggle_like(
            $request->user(),
            $post_id,
        );

        return response()->json($response);
    }

    public function place_comment(
        Request $request,
        PostService $post_service,
    ) {
        $request->validate([
            'post_id' => 'required|integer',
            'post_comment' => 'required|string',
        ]);
        $response = $post_service->place_comment(
            $request->user(),
            $request->post_id,
            $request->post_comment,
        );

        return response()->json($response);
    }

    public function delete_post_comment(
        int $comment_id,
        Request $request,
        PostService $post_service
    ) {
        $response = $post_service->delete_post_comment(
            $request->user(),
            $comment_id
        );

        return response()->json($response);
    }

    public function report_post_comment(
        Request $request,
        PostService $post_service,
        int $comment_id,
    ) {
        $request->validate([
            'reason_type' => 'required|in:offensive language,harassment or bullying,spam or irrelevance,
                 misleading or false information,violation of community guidelines,other',
            'other_reason' => 'required_if:reason_type,other',
        ]);

        $response = $post_service->report_post_comment(
            $request->user(),
            $comment_id,
            $request->reason_type,
            $request->other_reason,
        );

        return response()->json($response);
    }

    public function report_post(
        Request $request,
        PostService $post_service,
        int $post_id
    ) {
        $request->validate([
            'reason_type' => 'required|in:inappropriate content,misleading or fraudulent,prohibited items or services,spam or irrelevance,harassment or harmful behavior,other',
            'other_reason' => 'required_if:reason_type,other',
        ]);

        $response = $post_service->report_post(
            $request->user(),
            $request->reason_type,
            $request->other_reason,
            $post_id,
        );

        return response()->json($response);
    }

    public function get_post_details(
        Request $request,
        int $post_id,
        PostService $post_service,
    ) {
        $response = $post_service->get_post_details(
            $request->user(),
            $post_id,
        );

        return response()->json($response);
    }

    public function get_post_bids(
        int $post_id,
        Request $request,
        PostService $post_service
    ) {
        $response = $post_service->get_post_bids(
            $request->user(),
            $post_id,
        );

        return response()->json($response);
    }

    public function get_post_preview(
        int $post_id,
        Request $request,
        PostService $post_service
    ) {
        $response = $post_service->get_post_preview(
            $post_id,
            $request->user()
        );

        return response()->json($response);
    }

    public function get_post_reviews(
        int $post_id,
        PostService $post_service
    ) {
        $response = $post_service->get_post_reviews($post_id);

        return response()->json($response);
    }

    public function get_post_comments(
        int $post_id,
        Request $request,
        PostService $post_service
    ) {
        $response = $post_service->get_post_comments(
            $request->user(),
            $post_id,
        );

        return response()->json($response);
    }

    public function edit_post(
        Request $request,
        PostService $post_service,
        int $post_id,
    ) {
        $request->validate([
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'long' => 'nullable|numeric',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'location' => 'nullable|string',
            'budget' => 'nullable|integer',
            'start_date' => 'nullable',
            'end_date' => 'nullable',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'request_delivery' => 'nullable',
            'avatar.*' => 'nullable',
        ]);

        $avatars = $request->file('avatar');

        if ($avatars && ! is_array($avatars)) {
            $avatars = [$avatars];
        }

        $response = $post_service->edit_post(
            $request->user(),
            $post_id,
            $request->title,
            $request->description,
            $request->lat,
            $request->long,
            $request->city,
            $request->state,
            $request->location,
            $request->budget,
            $request->start_date,
            $request->end_date,
            $request->start_time,
            $request->end_time,
            $request->request_delivery,
            $avatars
        );

        return response()->json($response);
    }

    public function delete_post(
        string $post_id,
        Request $request,
        PostService $post_service
    ) {
        $response = $post_service->delete_post(
            $request->user(),
            $post_id,
        );

        return response()->json($response);
    }

    public function get_placed_bids(
        Request $request,
        PostService $post_service,
        ?string $bid_id = null
    ) {
        $response = $post_service->get_placed_bids(
            $request->user(),
            $bid_id,
        );

        return response()->json($response);
    }

    public function remove_rejected_bid(
        Request $request,
        PostService $post_service,
        string $bid_id,
    ) {
        $response = $post_service->remove_rejected_bid(
            $request->user(),
            $bid_id,
        );

        return response()->json($response);
    }

    public function get_placed_bid_status(
        Request $request,
        PostService $post_service,
        $post_id,
    ) {
        $response = $post_service->get_placed_bid_status(
            $request->user(),
            $post_id
        );

        return response()->json($response);
    }

    public function cancel_placed_bid(
        Request $request,
        PostService $post_service,
        $post_id
    ) {
        $response = $post_service->cancel_placed_bid(
            $request->user(),
            $post_id
        );

        return response()->json($response);
    }

    public function get_received_bids(
        Request $request,
        PostService $post_service
    ) {
        $response = $post_service->get_received_bids(
            $request->user(),
        );

        return response()->json($response);
    }
}
