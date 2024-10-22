<?php

namespace App\Http\Controllers;

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
            'location' => 'nullable',
            'budget' => 'required',
            'start_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'end_date' => 'required|date_format:Y-m-d|after:start_date',
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s|after:start_time',
            'request_delivery' => 'required',
            'type' => 'required|string',
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
        string $post_id,
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

    public function get_user_posts(
        Request $request,
        PostService $post_service,
    ) {
        $user = $request->user();

        $response = $post_service->get_user_posts($user);

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

    public function post_like(
        Request $request,
        PostService $post_service,
    ) {
        $request->validate([
            'post_id' => 'required|integer',
        ]);

        $response = $post_service->post_like(
            $request->user(),
            $request->post_id,
        );

        return response()->json($response);
    }

    public function post_details(
        Request $request,
        PostService $post_service,
    ) {
        $request->validate([
            'post_id' => 'required|integer',
        ]);

        $response = $post_service->post_details(
            $request->user(),
            $request->post_id,
        );

        return response()->json($response);
    }

    public function post_bids(
        string $post_id,
        Request $request, 
        PostService $post_service
    ) {
        $response = $post_service->post_bids(
            $request->user(),
            $post_id,
        );

        return response()->json($response);
    }

    public function post_comments(
        string $post_id,
        Request $request,
        PostService $post_service
    ) {
        $response = $post_service->post_comments(
            $request->user(),
            $post_id,
        );

        return response()->json($response);
    }

    public function edit_post(
        Request $request,
        PostService $post_service
    ) {
        $request->validate([
            'post_id' => 'required|integer',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'location' => 'nullable',
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
            $request->post_id,
            $request->title,
            $request->description,
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

    public function delete_post(Request $request, PostService $post_service)
    {
        $request->validate([
            'post_id' => 'required|integer',
        ]);

        $response = $post_service->delete_post(
            $request->user(),
            $request->post_id,
        );

        return response()->json($response);
    }
}
