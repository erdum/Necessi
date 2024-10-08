<?php

namespace App\Services;

use App\Models\User;
use App\Models\Post;
use App\Models\PostBid;
use App\Models\PostLike;
use App\Models\PostImage;
use Kreait\Firebase\Factory;
use Illuminate\Http\UploadedFile;
use App\Jobs\StoreImages;
use Carbon\Carbon;

class PostService
{
    protected $db;

    public function __construct(
        Factory $factory,
    ) {
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config("firebase.projects.app.credentials")
        );
        $this->db = $firebase->createFirestore()->database();
    }

    public function create_post(
        User $user,
        string $title,
        string $description,
        float $lat,
        float $long,
        string $location,
        int $budget,
        string $start_date,
        string $end_date,
        int $delivery_requested,
        string $type,
        ?array $avatars
    ) {
        $post = new Post();
        $post->user_id = $user->id;
        $post->title = $title;
        $post->description = $description;
        $post->lat = $lat;
        $post->long = $long;
        $post->location = $location ?? null;
        $post->budget = $budget;
        $post->start_date = $start_date;
        $post->end_date = $end_date;
        $post->delivery_requested = $delivery_requested;
        $post->type = $type;
        $post->save();

        if ($avatars) 
        {
            foreach ($avatars as $avatar) 
            {
                $post_image = new PostImage();
                $avatar_name = str()->random(15);
                $post_image->post_id = $post->id;
                $post_image->url = "avatars/$avatar_name.webp";
                $post_image->save();
    
                StoreImages::dispatchAfterResponse(
                    $avatar->path(),
                    "avatars",
                    $avatar_name
                );
            }
        }

        return $post;
    }
    
    public function post_biding(User $user,
        $post_id,
        $amount,
    ) {
        $existing_bid = PostBid::where('user_id', $user->id)
           ->where('post_id', $post_id)->first();
           
        if ($existing_bid) {
            return [
                'message' => 'You have already placed a bid on this post'
            ];
        }

        $post_bid = new PostBid();
        $post_bid->user_id = $user->id;
        $post_bid->post_id = $post_id;
        $post_bid->amount = $amount;
        $post_bid->status = 'pending';
        $post_bid->save();

        return [
            'message' => 'Your bid has been placed successfully'
        ];
    }

    public function get_user_posts(User $user)
    {
        $posts = $user->posts()->orderBy('created_at', 'desc')->paginate(10);

        return $posts->map(function ($post) use  ($user)  {
            return [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                "post_id" => $post->id,
                "user_id" => $post->user_id,
                "type" => $post->type,
                "title" => $post->title,
                "description" => $post->description,
                "location" => $post->location,
                "lat" => $post->lat,
                "long" => $post->long,
                "budget" => $post->budget,
                "duration" => Carbon::parse($post->start_date)->format('d M') . ' - ' . 
                              Carbon::parse($post->end_date)->format('d M y'),
                "created_at" => $post->created_at->diffForHumans(),
                "delivery_requested" => $post->delivery_requested,
                'bids' => $post->bids->count(),
                'likes' => $post->likes->count(),
                "images" => $post->images->map(function ($image) {
                    return [
                        "url" => $image->url,
                    ];
                }),
            ];
        });
    }

    public function get_all_posts(User $current_user)
    {
        $posts = Post::orderBy('created_at', 'desc')->paginate(10);
    
        return $posts->map(function ($post) use ($current_user)
        {
            $distance = $this->calculateDistance(
                $current_user->lat,
                $current_user->long,
                $post->lat, $post->long
            );
            return [
                'first_name' => $post->user->first_name,
                'last_name' => $post->user->last_name,
                'avatar' => $post->user->avatar,
                "post_id" => $post->id,
                "user_id" => $post->user_id,
                "type" => $post->type,
                "title" => $post->title,
                "description" => $post->description,
                "location" => $post->location,
                "lat" => $post->lat,
                "long" => $post->long,
                "distance" => round($distance, 2) . ' miles away',
                "budget" => $post->budget,
                "duration" => Carbon::parse($post->start_date)->format('d M') . ' - ' . 
                              Carbon::parse($post->end_date)->format('d M y'),
                "delivery_requested" => $post->delivery_requested,
                "created_at" => $post->created_at->diffForHumans(),
                'likes'=> $post->likes->count(),
                'bids' => $post->bids->count(),
                "images" => $post->images->map(function ($image) {
                    return [
                        "url" => $image->url,
                    ];
                }),
            ];
        });
    } 
    
    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 3958.8;
    
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
    
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
    
        $a = sin($dlat / 2) * sin($dlat / 2) +
             cos($lat1) * cos($lat2) *
             sin($dlon / 2) * sin($dlon / 2);
    
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
        $distance = $earthRadius * $c;
    
        return $distance;
    }

    public function post_like(User $user, $post_id)
    {
        $post_like = PostLike::where('post_id', $post_id)->where('user_id', $user->id)->first();

        if($post_like){
            $post_like->post_id = $post_id;
            $post_like->user_id = $user->id;
            $post_like->save();

            return $post_like;
        }

        $post_like = new PostLike();
        $post_like->post_id = $post_id;
        $post_like->user_id = $user->id;
        $post_like->save();

        return $post_like;
    }
}