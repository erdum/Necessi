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
            'start_date' => 'required|date_format:m/d/Y|after_or_equal:today',
            'end_date' => 'required|date_format:m/d/Y|after:start_date',
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
            $request->request_delivery,
            $request->type,
            $avatars
        );

        return response()->json($response);
    }

    public function post_biding(Request $request,
        PostService $post_service
    ) {
        $request->validate([
            'post_id' => 'required|integer',
            'amount' => 'required|integer',
        ]);

        $response = $post_service->post_biding(
            $request->user(),
            $request->post_id,
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
        Request $request, 
        PostService $post_service
    ) {
        $request->validate([
            'post_id' => 'required',
        ]);
        
        $response = $post_service->post_bids(
            $request->user(),
            $request->post_id,
        );

        return response()->json($response);
    }

    public function post_comments(
        Request $request, 
        PostService $post_service
    ) {
        $request->validate([
            'post_id' => 'required',
        ]);
        
        $response = $post_service->post_comments(
            $request->user(),
            $request->post_id,
        );

        return response()->json($response);
    }
}
