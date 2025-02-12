<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostBid;
use App\Models\PostComment;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class PostController extends Controller
{
    public function create_post(
        Request $request,
        PostService $post_service
    ) {
        $request->validate([
            'title' => 'required|string',
            'description' => 'required|string|max:1000',
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'location' => 'nullable|string',
            'budget' => 'required|gte:5|lte:1000',
            'type' => 'required|string|in:item,service',
            'start_date' => [
                'required',
                'date_format:m-d-Y',
                function ($attribute, $value, $fail) use ($request) {
                    try {
                        $start_date = Carbon::createFromFormat(
                            'm-d-Y',
                            $value
                        );

                        if ($start_date->lessThan(Carbon::today())) {
                            $fail('The ' . $attribute . ' must be after or equal to the today date.');
                        }
                    } catch (\Exception $e) {
                        $fail('Invalid date format.');
                    }
                },
            ],
            'end_date' => [
                'required',
                'date_format:m-d-Y',
                function ($attribute, $value, $fail) use ($request) {
                    try {
                        $start_date = Carbon::createFromFormat(
                            'm-d-Y',
                            $request->start_date
                        );
                        $end_date = Carbon::createFromFormat('m-d-Y', $value);

                        if ($request->type === 'service') {
                            if ($end_date->lessThan($start_date)) {
                                $fail('The ' . $attribute . ' must be after or equal to the start date.');
                            }
                        } else {
                            if ($end_date->lessThanOrEqualTo($start_date)) {
                                $fail('The ' . $attribute . ' must be after to the start date.');
                            }
                        }
                    } catch (\Exception $e) {
                        $fail('Invalid date format.');
                    }
                },
            ],
            'end_date' => [
                'required',
                'date_format:m-d-Y',
                Rule::when($request->type === 'service', 'after_or_equal:start_date', 'after:start_date'),
            ],
            'start_time' => [
                'nullable',
                'date_format:H:i:s',
                'required_if:type,service',
                function ($attribute, $value, $fail) {
                    $c_time_1 = Carbon::now();
                    $c_time_2 = Carbon::parse($value);

                    if ($c_time_1->greaterThan($c_time_2)) {
                        $fail('The ' . $attribute . ' must be equal to or after the current time.');
                    }
                },
            ],
            'end_time' => [
                'nullable',
                'date_format:H:i:s',
                'required_if:type,service',
                function ($attribute, $value, $fail) use ($request) {
                    $c_time_1 = Carbon::parse($request->start_time);
                    $c_time_2 = Carbon::parse($value);

                    if ($c_time_1->greaterThanOrEqualTo($c_time_2)) {
                        $fail('The ' . $attribute . ' must be after the start_time.');
                    }
                },
            ],
            'request_delivery' => 'nullable',
            'avatar[]' => 'nullable|array',
            'avatar[].*' => 'file',
            'use_account_address' => 'nullable|in:true,false',
        ]);

        $user = $request->user();

        $avatars = $request->file('avatar');

        if ($avatars && ! is_array($avatars)) {
            $avatars = [$avatars];
        }

        if (filter_var($request->use_account_address, FILTER_VALIDATE_BOOL)) {
            $location['state'] = $user->state;
            $location['city'] = $user->city;
            $location['location'] = $user->location;
            $location['lat'] = $user->lat;
            $location['long'] = $user->long;
        } else {
            $location['state'] = $request->state;
            $location['city'] = $request->city;
            $location['location'] = $request->location;
            $location['lat'] = $request->lat;
            $location['long'] = $request->long;
        }

        $response = $post_service->create_post(
            $request->user(),
            $request->title,
            $request->description,
            $location['lat'],
            $location['long'],
            $location['city'],
            $location['state'],
            $location['location'],
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
            'amount' => 'required|integer|gte:5|lte:1000',
        ]);

        $post = Post::findOrFail($post_id);

        $response = $post_service->place_bid(
            $request->user(),
            $post,
            $request->amount,
        );

        return response()->json($response);
    }

    public function accept_post_bid(
        int $bid_id,
        Request $request,
        PostService $post_service
    ) {
        $bid = PostBid::findOrFail($bid_id);

        $response = $post_service->accept_post_bid(
            $request->user(),
            $bid
        );

        return response()->json($response);
    }

    public function decline_post_bid(
        int $bid_id,
        Request $request,
        PostService $post_service
    ) {
        $bid = PostBid::findOrFail($bid_id);

        $response = $post_service->decline_post_bid(
            $request->user(),
            $bid
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
            $user = $post_service->get_user_posts($user_model);
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
        int $post_id,
        Request $request,
        PostService $post_service
    ) {
        $post = Post::findOrFail($post_id);

        $response = $post_service->get_user_review(
            $request->user(),
            $post,
        );

        return response()->json($response);
    }

    public function place_post_review(
        Request $request,
        PostService $post_service,
    ) {
        $request->validate([
            'post_id' => 'required|exists:posts,id',
            'description' => 'required|string',
            'rating' => 'required|numeric',
        ]);

        $post = Post::findOrFail($request->post_id);

        $response = $post_service->place_post_review(
            $request->user(),
            $post,
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
            auth('sanctum')->user() ?? null,
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
        $post = Post::findOrFail($post_id);

        $response = $post_service->toggle_like(
            $request->user(),
            $post,
        );

        return response()->json($response);
    }

    public function place_comment(
        Request $request,
        PostService $post_service,
    ) {
        $request->validate([
            'post_id' => 'required|exists:posts,id',
            'post_comment' => 'required|string',
        ]);

        $post = Post::findOrFail($request->post_id);

        $response = $post_service->place_comment(
            $request->user(),
            $post,
            $request->post_comment,
        );

        return response()->json($response);
    }

    public function delete_post_comment(
        int $comment_id,
        Request $request,
        PostService $post_service
    ) {
        $comment = PostComment::findOrFail($comment_id);

        $response = $post_service->delete_post_comment(
            $request->user(),
            $comment
        );

        return response()->json($response);
    }

    public function report_post_comment(
        int $comment_id,
        Request $request,
        PostService $post_service
    ) {
        $request->validate([
            'reason_type' => 'required|in:offensive language,harassment or bullying,spam or irrelevance,misleading or false information,violation of community guidelines,other',
            'other_reason' => 'required_if:reason_type,other',
        ]);

        $comment = PostComment::findOrFail($comment_id);

        $response = $post_service->report_post_comment(
            $request->user(),
            $comment,
            $request->reason_type,
            $request->other_reason,
        );

        return response()->json($response);
    }

    public function report_post(
        int $post_id,
        Request $request,
        PostService $post_service
    ) {
        $request->validate([
            'reason_type' => 'required|in:inappropriate content,misleading or fraudulent,prohibited items or services,spam or irrelevance,harassment or harmful behavior,other',
            'other_reason' => 'required_if:reason_type,other',
        ]);

        $post = Post::findOrFail($post_id);

        $response = $post_service->report_post(
            $request->user(),
            $post,
            $request->reason_type,
            $request->other_reason
        );

        return response()->json($response);
    }

    public function get_post_details(
        int $post_id,
        Request $request,
        PostService $post_service
    ) {
        $post = Post::findOrFail($post_id);

        $response = $post_service->get_post_details(
            $request->user(),
            $post,
        );

        return response()->json($response);
    }

    public function get_post_bids(
        int $post_id,
        Request $request,
        PostService $post_service
    ) {
        $post = Post::findOrFail($post_id);

        $response = $post_service->get_post_bids(
            $request->user(),
            $post,
        );

        return response()->json($response);
    }

    public function get_post_preview(
        int $post_id,
        Request $request,
        PostService $post_service
    ) {
        $post = Post::findOrFail($post_id);

        $response = $post_service->get_post_preview(
            $request->user(),
            $post
        );

        return response()->json($response);
    }

    public function get_post_reviews(
        int $post_id,
        PostService $post_service
    ) {
        $post = Post::findOrFail($post_id);

        $response = $post_service->get_post_reviews($post);

        return response()->json($response);
    }

    public function get_post_comments(
        int $post_id,
        Request $request,
        PostService $post_service
    ) {
        $post = Post::findOrFail($post_id);

        $response = $post_service->get_post_comments(
            $request->user(),
            $post,
        );

        return response()->json($response);
    }

    public function edit_post(
        int $post_id,
        Request $request,
        PostService $post_service
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

        $post = Post::findOrFail($post_id);

        $response = $post_service->edit_post(
            $request->user(),
            $post,
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
        int $post_id,
        Request $request,
        PostService $post_service
    ) {
        $post = Post::findOrFail($post_id);

        $response = $post_service->delete_post(
            $request->user(),
            $post,
        );

        return response()->json($response);
    }

    public function get_placed_bids(
        Request $request,
        PostService $post_service,
        ?int $bid_id = null
    ) {
        $response = $post_service->get_placed_bids(
            $request->user(),
            $bid_id,
        );

        return response()->json($response);
    }

    public function remove_rejected_bid(
        int $bid_id,
        Request $request,
        PostService $post_service,
    ) {
        $bid = PostBid::findOrFail($bid_id);

        $response = $post_service->remove_rejected_bid(
            $request->user(),
            $bid,
        );

        return response()->json($response);
    }

    public function get_placed_bid_status(
        int $post_id,
        Request $request,
        PostService $post_service,
    ) {
        $post = Post::findOrFail($post_id);

        $response = $post_service->get_placed_bid_status(
            $request->user(),
            $post
        );

        return response()->json($response);
    }

    public function cancel_placed_bid(
        int $post_id,
        Request $request,
        PostService $post_service
    ) {
        $post = Post::findOrFail($post_id);

        $response = $post_service->cancel_placed_bid(
            $request->user(),
            $post
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
