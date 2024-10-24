<?php

namespace App\Services;

use App\Exceptions;
use App\Jobs\StoreImages;
use App\Models\Post;
use App\Models\PostBid;
use App\Models\PostComment;
use App\Models\PostImage;
use App\Models\PostLike;
use App\Models\User;
use Carbon\Carbon;
use Google\Cloud\Firestore\FieldValue;
use Kreait\Firebase\Factory;

class PostService
{
    protected $db;

    protected $notification_service;

    public function __construct(
        Factory $factory,
        FirebaseNotificationService $notification_service,
    ) {
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $this->db = $firebase->createFirestore()->database();
        $this->notification_service = $notification_service;
    }

    public function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
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
        ?string $start_time,
        ?string $end_time,
        ?int $delivery_requested,
        string $type,
        ?array $avatars
    ) {
        $post = new Post;
        $post->user_id = $user->id;
        $post->title = $title;
        $post->description = $description;
        $post->lat = $lat;
        $post->long = $long;
        $post->location = $location ?? null;
        $post->budget = $budget;
        $post->start_date = $start_date;
        $post->end_date = $end_date;
        $post->delivery_requested = $delivery_requested ?? false;
        $post->type = $type;

        if ($type === 'service') {
            $post->start_time = $start_time;
            $post->end_time = $end_time;
        }

        $post->save();

        if ($avatars) {
            foreach ($avatars as $avatar) {
                $post_image = new PostImage;
                $avatar_name = str()->random(15);
                $post_image->post_id = $post->id;
                $post_image->url = "avatars/$avatar_name.webp";
                $post_image->save();

                StoreImages::dispatchAfterResponse(
                    $avatar->path(),
                    'avatars',
                    $avatar_name
                );
            }
        }

        return $post;
    }

    public function place_bid(
        User $user,
        $post_id,
        $amount
    ) {
        $post = Post::find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

        $bids = $this->db->collection('bids')->document($user->uid);
        $existing_bid = PostBid::where('user_id', $user->id)
            ->where('post_id', $post_id)->first();

        if ($existing_bid) {
            if ($amount < $existing_bid->amount) {
                $bids->update([
                    ['path' => 'user_id', 'value' => $user->id],
                    ['path' => 'post_id', 'value' => $post_id],
                    ['path' => 'amount', 'value' => $amount],
                    ['path' => 'status', 'value' => 'pending'],
                    ['path' => 'created_at', 'value' => FieldValue::serverTimestamp()],
                ]);

                $existing_bid->amount = $amount;
                $existing_bid->status = 'pending';
                $existing_bid->save();

                return [
                    'message' => 'Your bid has been updated successfully',
                ];
            }

            return [
                'message' => 'New bid amount must be less than the previous bid',
            ];
        }

        $bids->set([
            'user_id' => $user->id,
            'post_id' => $post_id,
            'amount' => $amount,
            'status' => 'pending',
            'created_at' => FieldValue::serverTimestamp(),
        ]);

        $post_bid = new PostBid;
        $post_bid->user_id = $user->id;
        $post_bid->post_id = $post_id;
        $post_bid->amount = $amount;
        $post_bid->status = 'pending';
        $post_bid->save();

        return [
            'message' => 'Your bid has been placed successfully',
        ];
    }

    public function get_user_posts(User $user)
    {
        $posts = $user->posts()->orderBy('created_at', 'desc')->paginate(10);

        return $posts->map(function ($post) use ($user) {
            return [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                'post_id' => $post->id,
                'user_id' => $post->user_id,
                'type' => $post->type,
                'title' => $post->title,
                'description' => $post->description,
                'location' => $post->location,
                'lat' => $post->lat,
                'long' => $post->long,
                'budget' => $post->budget,
                'duration' => Carbon::parse($post->start_date)->format('d M').' - '.
                              Carbon::parse($post->end_date)->format('d M y'),
                'created_at' => $post->created_at->diffForHumans(),
                'delivery_requested' => $post->delivery_requested,
                'bids' => $post->bids->count(),
                'likes' => $post->likes->count(),
                'images' => $post->images->map(function ($image) {
                    return [
                        'url' => $image->url,
                    ];
                }),
            ];
        });
    }

    public function get_all_posts(User $current_user)
    {
        $posts = Post::orderBy('created_at', 'desc')->paginate(10);

        return $posts->map(function ($post) use ($current_user) {
            $distance = $this->calculateDistance(
                $current_user->lat,
                $current_user->long,
                $post->lat, $post->long
            );

            return [
                'first_name' => $post->user->first_name,
                'last_name' => $post->user->last_name,
                'avatar' => $post->user->avatar,
                'post_id' => $post->id,
                'user_id' => $post->user_id,
                'type' => $post->type,
                'title' => $post->title,
                'description' => $post->description,
                'location' => $post->location,
                'lat' => $post->lat,
                'long' => $post->long,
                'distance' => round($distance, 2).' miles away',
                'budget' => $post->budget,
                'duration' => Carbon::parse($post->start_date)->format('d M').' - '.
                              Carbon::parse($post->end_date)->format('d M y'),
                'delivery_requested' => $post->delivery_requested,
                'created_at' => $post->created_at->diffForHumans(),
                'likes' => $post->likes->count(),
                'bids' => $post->bids->count(),
                'images' => $post->images->map(function ($image) {
                    return [
                        'url' => $image->url,
                    ];
                }),
            ];
        });
    }

    public function post_like(User $user, int $post_id)
    {
        $post = Post::find($post_id);
        $post_like = PostLike::where('post_id', $post_id)->where('user_id', $user->id)->first();

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

        if ($post_like) {
            $post_like->post_id = $post_id;
            $post_like->user_id = $user->id;
            $post_like->save();

            return $post_like;
        }

        $post_like = new PostLike;
        $post_like->post_id = $post_id;
        $post_like->user_id = $user->id;
        $post_like->save();

        return $post_like;
    }

    public function get_post_details(int $post_id)
    {
        $post_details = Post::find($post_id);

        if (!$post_details) {
            throw new Exceptions\InvalidPostId;
        }

        $user = User::find($post_details->user_id);

        if (!$user) {
            throw new Exceptions\UserNotFound;
        }

        $bids_ref = $this->db->collection('bids');
        $bids_snapshot = $bids_ref->where('post_id', '=', $post_id)
            ->orderBy('amount', 'ASC')
            ->limit(4)
            ->documents();
        $comments = PostComment::where('post_id', $post_id)
            ->orderBy('created_at', 'DESC')
            ->get();
        $images = [];
        $comment_list = [];
        $bids = [];

        foreach ($comments->take(2) as $comment) {
            $comment_list[] = [
                'avatar' => $comment->user->avatar,
                'user_name' => $comment->user->first_name.' '.$comment->user->last_name,
                'comment' => $comment->data,
                'created_at' => $comment->created_at->diffForHumans(),
            ];
        }

        foreach ($bids_snapshot as $bid_doc) {
            $bid_data = $bid_doc->data();
            $bid_user = User::find($bid_data['user_id']);

            $bids[] = [
                'user_name' => $bid_user->first_name.' '.$bid_user->last_name,
                'avatar' => $bid_user->avatar,
                'amount' => $bid_data['amount'],
                'created_at' => Carbon::parse($bid_data['created_at'])->diffForHumans(),
                'status' => $bid_data['status'],
            ];
        }

        foreach ($post_details->images as $image) {
            $images[] = [
                'image' => $image->url,
            ];
        }

        $distance = $this->calculateDistance(
            $user->lat,
            $user->long,
            $post_details->lat,
            $post_details->long,
        );

        return [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'type' => $post_details->type,
            'created_at' => $post_details->created_at->diffForHumans(),
            'budget' => $post_details->budget,
            'duration' => Carbon::parse($post_details->start_date)->format('d M').' - '.
                          Carbon::parse($post_details->end_date)->format('d M y'),
            'location' => $post_details->location,
            'distance' => round($distance, 2).' miles away',
            'title' => $post_details->title,
            'description' => $post_details->description,
            'likes' => $post_details->likes->count(),
            'images' => $images,
            'bids' => $bids,
            'comments' => $comment_list,
        ];
    }

    public function get_post_bids(User $user, $post_id)
    {
        $post = Post::find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

        $bids_ref = $this->db->collection('bids');
        $bids_snapshot = $bids_ref->where('post_id', '=', $post_id)
            ->orderBy('amount', 'ASC')
            ->documents();
        $post_bid = [];

        foreach ($bids_snapshot as $bid_doc) {
            $bid_data = $bid_doc->data();
            $bid_user = User::find($bid_data['user_id']);

            $post_bid[] = [
                'user_name' => $bid_user->first_name.' '.$bid_user->last_name,
                'avatar' => $bid_user->avatar,
                'amount' => $bid_data['amount'],
                'created_at' => Carbon::parse($bid_data['created_at'])->diffForHumans(),
                'status' => $bid_data['status'],
            ];
        }

        return $post_bid;
    }

    public function get_post_reviews(int $post_id)
    {
        $post = Post::find($post_id);

        if(!$post){
            throw new Exceptions\InvalidPostId;
        }
        $post_reviews = [];

        foreach($post->reviews as $review)
        {
            $user = User::find($review->user_id);
            $post_reviews[] = [
                'user_id' => $review->user_id,
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'avatar' => $user->avatar,
                'rating' => $review->rating,
                'description' => $review->data,
                'created_at' => $review->created_at->diffForHumans(),
            ];
        }
        
        return $post_reviews;
    }

    public function get_post_comments(User $user, $post_id)
    {
        $post = Post::find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }
        $post_comments = [];

        foreach ($post->comments as $post_comment) {
            $user_comment = User::find($post_id);
            $post_comments[] = [
                'post_id' => $post_comment->post_id,
                'user_id' => $user_comment->id,
                'user_name' => $user_comment->first_name.' '.$user_comment->last_name,
                'avatar' => $user_comment->avatar,
                'comment' => $post_comment->data,
            ];
        }

        return $post_comments;
    }

    public function edit_post(
        User $user,
        int $post_id,
        ?string $title,
        ?string $description,
        ?string $location,
        ?int $budget,
        ?string $start_date,
        ?string $end_date,
        ?string $start_time,
        ?string $end_time,
        ?int $request_delivery,
        ?array $avatars
    ) {
        $post = Post::find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

        if (! $user->posts->contains('id', $post_id)) {
            throw new Exceptions\PostOwnership;
        }

        $post->title = $title ?? $post->title ?? null;
        $post->description = $description ?? $post->description ?? null;
        $post->location = $location ?? $post->location ?? null;
        $post->budget = $budget ?? $post->budget ?? null;
        $post->start_date = $start_date ?? $post->start_date ?? null;
        $post->end_date = $end_date ?? $post->end_date ?? null;
        $post->delivery_requested = $request_delivery ?? $post->delivery_requested ?? null;

        if ($post->type === 'service') {
            $post->start_time = $start_time ?? $post->start_time ?? null;
            $post->end_time = $end_time ?? $post->end_time ?? null;
        }

        $post->save();

        if ($avatars) {
            PostImage::where('post_id', $post->id)->delete();

            foreach ($avatars as $avatar) {
                $new_post_image = new PostImage;
                $new_post_image->post_id = $post->id;
                $avatar_name = str()->random(15);
                $new_post_image->url = "avatars/{$avatar_name}.webp";
                $new_post_image->save();

                StoreImages::dispatchAfterResponse(
                    $avatar->path(),
                    'avatars',
                    $avatar_name
                );
            }
        }

        return $post;
    }

    public function delete_post(User $user, $post_id)
    {
        $post = Post::find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

        if (! $user->posts->contains('id', $post_id)) {
            throw new Exceptions\PostOwnership;
        }

        $post->delete();

        return [
            'message' => 'Post successfully deleted',
        ];
    }

    public function search_all_posts(User $user, string $search_txt)
    {
        $search_terms = explode(' ', $search_txt);
    
        $posts = Post::where(function ($query) use ($search_txt, $search_terms) 
        {
            $query->where('title', 'like', '%' . $search_txt . '%');
            foreach ($search_terms as $term) 
            {
                $query->orWhere('description', 'like', '%' . $term . '%');
            }
        })->orderBy('created_at', 'desc')->get();
    
        if ($posts->isEmpty()) 
        {
            $user = User::where(function ($query) use ($search_terms) 
            {
                foreach ($search_terms as $term) {
                    $query->orWhere('first_name', 'like', '%' . $term . '%')
                        ->orWhere('last_name', 'like', '%' . $term . '%');
                }
            })->first();
    
            if ($user) {
                $posts = $user->posts()->orderBy('created_at', 'desc')->get();
            }
        }
    
        $all_items = [];
        $user_ids_added = [];
    
        foreach ($posts as $post) 
        {
            $user = User::find($post->user_id);
            $distance = $this->calculateDistance(
                $user->lat,
                $user->long,
                $post->lat,
                $post->long,
            );
    
            $all_items[] = [
                'type' => 'posts',
                'post_id' => $post->id,
                'user_id' => $post->user_id,
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'avatar' => $user->avatar,
                'post_type' => $post->type,
                'created_at' => $post->created_at->diffForHumans(),
                'budget' => $post->budget,
                'duration' => Carbon::parse($post->start_date)->format('d M').' - '.
                              Carbon::parse($post->end_date)->format('d M y'),
                'location' => $post->location,
                'distance' => round($distance, 2).' miles away',
                'title' => $post->title,
                'description' => $post->description,
            ];
        }
    
        foreach ($posts as $post) 
        {
            if (!in_array($post->user_id, $user_ids_added)) 
            {
                $user = User::find($post->user_id);
                if ($user) {
                    $all_items[] = [
                        'type' => 'peoples',
                        'user_id' => $post->user_id,
                        'user_name' => $user->first_name . ' ' . $user->last_name,
                        'avatar' => $user->avatar,
                    ];
    
                    $user_ids_added[] = $post->user_id;
                }
            }
        }
    
        return $all_items;
    }    

    public function search_people(User $current_user, string $search_txt)
    {
        $search_terms = explode(' ', $search_txt);
        $user = User::where(function ($query) use ($search_terms, $search_txt) 
        {
            foreach ($search_terms as $term) {
                $query->orWhere('first_name', 'like', '%' . $search_txt . '%')
                    ->orWhere('last_name', 'like', '%' . $term . '%');
            }
        })->get();

        return $user;
    }
}
