<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PostService;

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

        if ($avatars && !is_array($avatars)) {
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
}
