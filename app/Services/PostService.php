<?php

namespace App\Services;

use App\Exceptions;
use App\Jobs\StoreImages;
use App\Models\Post;
use App\Models\PostBid;
use App\Models\PostImage;
use App\Models\PostLike;
use App\Models\PostComment;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Google\Cloud\Firestore\FieldValue;
use Illuminate\Pagination\LengthAwarePaginator;
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

    public function push_new_message_notification(
        string $title,
        string $image,
        ?User $receiver_user,
        string $type,
        ?array $additional_data = null
    ) {
        if (!$receiver_user) {
            return;
        }

        $body = '';

        switch ($type) {
            case 'post_bidding':
                if (!($receiver_user->preferences?->biding_notifications ?? true)) {
                    return;
                }
                $body = $title . ' has bid on your post';
                break;

            case 'bid_accepted':
                if (!($receiver_user->preferences?->biding_notifications ?? true)) {
                    return;
                }
                $body = $title . ' has accepted your bid request';
                break;

            case 'bid_rejected':
                if (!($receiver_user->preferences?->biding_notifications ?? true)) {
                        return;
                }
                $body = $title . ' has rejected your bid request';
                break;

            case 'placed_comment':
                if (!($receiver_user->preferences?->activity_notifications ?? true)) {
                        return;
                }
                $body = $title . ' has commented on your post';
                break;

            case 'send_connection_request':
                if (!($receiver_user->preferences?->activity_notifications ?? true)) {
                    return;
                }
                $body = $title . ' has sent you a connection request';
                break;
            
            case 'accept_connection_request':
                if (!($receiver_user->preferences?->activity_notifications ?? true)) {
                    return;
                }
                $body = $title . ' has accept your connection request';
                break;

            default:
                return; 
        }

        $this->notification_service->push_notification(
            $receiver_user,
            $title,
            $body,
            $image,
            $additional_data
        );
    }

    public function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ) {
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
        ?string $city,
        ?string $state,
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
        $post->city = $city ?? null;
        $post->state = $state ?? null;
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
        int $post_id,
        int $amount
    ) {
        $post = Post::find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

        if ($user->posts->contains('id', $post_id)) {
            throw new Exceptions\CannotBidOnOwnPost;
        }

        $existing_bid = PostBid::where('user_id', $user->id)
            ->where('post_id', $post_id)->first();

        $bid_ref = $this->db->collection('bids')->document($user->uid);
        $bid = $bid_ref->collection('post_bid')->document($post_id);

        if ($existing_bid) {
            if ($amount >= $existing_bid->amount) {
                throw new Exceptions\BidAmountTooHigh;
            }

            $bid_snapshot = $bid->snapshot();
            if ($bid_snapshot->exists()) {
                $bid->update([
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
        }

        $bid->set([
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

        $receiver_user = User::find($post->user_id);
        $type = 'post_bidding';
        $user_name = $user->first_name . ' ' . $user->last_name;


        $this->push_new_message_notification(
            $user_name,
            $user->avatar ?? '',
            $receiver_user,
            $type,
            [
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'user_avatar' => $user->avatar,
                'description' => $user->about,
            ]
        );

        return [
            'message' => 'Your bid has been placed successfully',
        ];
    }

    public function accept_post_bid(User $user, int $bid_id)
    {
        $bid = PostBid::find($bid_id);

        if ($bid->post->user_id != $user->id) {
            throw new Exceptions\PostOwnership;
        }

        $bid->status = 'accepted';
        $bid->save();

        $bid_ref = $this->db->collection('bids')->document($bid->user->uid)
            ->collection('post_bid')->document($bid->post_id);

        $bid_ref->update([
            ['path' => 'status', 'value' => 'accepted'],
        ]);

        $receiver_user = User::find($bid->user_id);
        $type = 'bid_accepted';
        $user_name = $user->first_name . ' ' . $user->last_name;


        $this->push_new_message_notification(
            $user_name,
            $user->avatar ?? '',
            $receiver_user,
            $type,
            [
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'user_avatar' => $user->avatar,
                'description' => $user->about,
            ]
        );

        return [
            'message' => 'You have successfully accepted the bid',
        ];
    }

    public function decline_post_bid(User $user, int $bid_id)
    {
        $bid = PostBid::find($bid_id);

        if ($bid->post->user_id != $user->id) {
            throw new Exceptions\PostOwnership;
        }

        $bid->status = 'rejected';
        $bid->save();

        $bid_ref = $this->db->collection('bids')->document($bid->user->uid)
            ->collection('post_bid')->document($bid->post_id);

        $bid_ref->update([
            ['path' => 'status', 'value' => 'rejected'],
        ]);

        $receiver_user = User::find($bid->user_id);
        $type = 'bid_rejected';
        $user_name = $user->first_name . ' ' . $user->last_name;


        $this->push_new_message_notification(
            $user_name,
            $user->avatar ?? '',
            $receiver_user,
            $type,
            [
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'user_avatar' => $user->avatar,
                'description' => $user->about,
            ]
        );

        return [
            'message' => 'You have successfully declined the bid',
        ];
    }

    public function get_user_posts(User $user)
    {
        $posts = $user->posts()->orderBy('created_at', 'desc')->paginate(10);

        return $posts->map(function ($post) use ($user) {
            $current_user_like = PostLike::where('user_id', $user->id)
                ->where('post_id', $post->id)->exists();

            return [
                'post_id' => $post->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                'user_id' => $post->user_id,
                'type' => $post->type,
                'title' => $post->title,
                'description' => $post->description,
                'location' => $post->city,
                'lat' => $post->lat,
                'long' => $post->long,
                'budget' => $post->budget,
                'duration' => Carbon::parse($post->start_date)->format('d M').' - '.Carbon::parse($post->end_date)->format('d M y'),
                'created_at' => $post->created_at->diffForHumans(),
                'delivery_requested' => $post->delivery_requested,
                'bids' => $post->bids->count(),
                'current_user_like' => $current_user_like,
                'likes' => $post->likes->count(),
                'images' => $post->images->map(function ($image) {
                    return [
                        'url' => $image->url,
                    ];
                }),
            ];
        });
    }

    public function get_user_posts_reviews(
        User $user,
        ?int $user_id,
        ?int $filter_rating
    ) {
        $reviews = Review::whereHas(
            'post',
            function ($query) use ($user_id, $user) {
                $query->where('user_id', $user_id ?: $user->id);
            }
        )
            ->when($filter_rating, function ($query) use ($filter_rating) {
                $query->where('rating', $filter_rating);
            })
            ->with('user:id,first_name,last_name,avatar')
            ->get();

        $rating_sum = 0;
        $reviews_data = [];
        $stats = [
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
            '5' => 0,
        ];

        foreach ($reviews as $review) {
            $rating = (string) $review->rating;
            if (array_key_exists($rating, $stats)) {
                $stats[$rating] += 1;
                $rating_sum += $review->rating;
            }
        }

        foreach ($reviews as $review) {
            $reviews_data[] = [
                'post_id' => $review->post_id,
                'description' => $review->data,
                'rating' => $review->rating,
                'created_at' => $review->created_at->format('d M'),
                'user_id' => $review->user->id,
                'user_name' => $review->user->first_name.' '.$review->user->last_name,
                'avatar' => $review->user->avatar,
            ];
        }

        return [
            'average_rating' => $reviews->count() > 0 ? $rating_sum / $reviews->count() : 0,
            'rating_count' => $reviews->count(),
            'stats' => $stats,
            'reviews' => $reviews_data,
        ];
    }

    public function get_all_posts(User $user)
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $per_page = 3;

        $data_count = Post::all()->count();
        $posts = Post::orderBy('created_at', 'desc')
            ->with('user:id,first_name,last_name,avatar')
            ->offset($per_page * ($page - 1))
            ->limit($per_page)
            ->get();

        $posts = $posts->map(function ($post) use ($user) {
            $self_liked = $post->likes()->where('user_id', $user->id)->exists();

            $distance = $this->calculateDistance(
                $user->lat,
                $user->long,
                $post->lat,
                $post->long
            );

            return [
                'post_id' => $post->id,
                'user_id' => $post->user_id,
                'first_name' => $post->user->first_name,
                'last_name' => $post->user->last_name,
                'avatar' => $post->user->avatar,
                'type' => $post->type,
                'title' => $post->title,
                'description' => $post->description,
                'location' => $post->city,
                'lat' => $post->lat,
                'long' => $post->long,
                'distance' => round($distance, 2).' miles away',
                'budget' => $post->budget,
                'duration' => Carbon::parse($post->start_date)->format('d M').' - '.Carbon::parse($post->end_date)->format('d M y'),
                'delivery_requested' => $post->delivery_requested,
                'created_at' => $post->created_at->diffForHumans(),
                'current_user_like' => $self_liked,
                'likes' => $post->likes->count(),
                'bids' => $post->bids->count(),
                'images' => $post->images,
            ];

        });

        $paginator = new LengthAwarePaginator(
            $posts,
            $data_count,
            $per_page,
            $page
        );

        return $paginator;
    }

    public function toggle_like(User $user, int $post_id)
    {
        $like = PostLike::where('post_id', $post_id)
            ->where('user_id', $user->id)->first();

        if ($like) {
            $like->delete();

            return ['message' => 'Post successfully un-liked'];
        }

        $like = new PostLike;
        $like->post_id = $post_id;
        $like->user_id = $user->id;
        $like->save();

        return ['message' => 'Post successfully liked'];
    }

    public function place_comment(
        User $user, 
        int $post_id, 
        string $post_comment
    ) {
        $post = Post::find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

        $comment = new PostComment;
        $comment->user_id = $user->id;
        $comment->post_id = $post_id;
        $comment->data = $post_comment;
        $comment->save();

        $receiver_user = User::find($post->user_id);
        $type = 'placed_comment';
        $user_name = $user->first_name . ' ' . $user->last_name;


        $this->push_new_message_notification(
            $user_name,
            $user->avatar ?? '',
            $receiver_user,
            $type,
            [
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'user_avatar' => $user->avatar,
                'description' => $user->about,
            ]
        );

        return ['message' => 'Comment has been successfully posted'];
    }

    public function post_unlike(User $user, int $post_id)
    {
        $post = Post::find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

        $post_like = PostLike::where('post_id', $post_id)->where(
            'user_id', $user->id)->first();

        if ($post_like) {
            $post_like->delete();
        }

        return [
            'message' => 'Post unlike successfully',
        ];
    }

    public function get_post_details(User $current_user, int $post_id)
    {
        $post_details = Post::with([
            'bids' => function ($query) {
                $query->with('user')->orderBy('amount')->limit(4);
            },
            'comments' => function ($query) {
                $query->with('user')->latest();
            },
        ])->with('user')->find($post_id);
        $comments = [];
        $bids = [];
        $images = [];

        if (! $post_details) {
            throw new Exceptions\InvalidPostId;
        }

        foreach ($post_details->bids as $bid) {
            $bids[] = [
                'user_name' => $bid->user->first_name.' '.$bid->user->last_name,
                'avatar' => $bid->user->avatar,
                'amount' => $bid->amount,
                'created_at' => Carbon::parse($bid->created_at)->diffForHumans(),
                'status' => $bid->status,
            ];
        }

        foreach ($post_details->images as $image) {
            $images[] = [
                'image' => $image->url,
            ];
        }

        foreach ($post_details->comments as $comment) {
            $comments[] = [
                'avatar' => $comment->user->avatar,
                'user_name' => $comment->user->first_name.' '.$comment->user->last_name,
                'comment' => $comment->data,
                'created_at' => $comment->created_at->diffForHumans(),
            ];
        }

        $current_user_like = $post_details->likes()
            ->where('user_id', $current_user->id)
            ->exists();

        $distance = $this->calculateDistance(
            $current_user->lat,
            $current_user->long,
            $post_details->lat,
            $post_details->long,
        );

        return [
            'post_id' => $post_details->id,
            'user_id' => $post_details->user->id,
            'first_name' => $post_details->user->first_name,
            'last_name' => $post_details->user->last_name,
            'avatar' => $post_details->user->avatar,
            'type' => $post_details->type,
            'created_at' => $post_details->created_at->diffForHumans(),
            'budget' => $post_details->budget,
            'duration' => Carbon::parse($post_details->start_date)->format('d M').' - '.Carbon::parse($post_details->end_date)->format('d M y'),
            'location' => $post_details->city,
            'distance' => round($distance, 2).' miles away',
            'title' => $post_details->title,
            'description' => $post_details->description,
            'current_user_like' => $current_user_like,
            'likes' => $post_details->likes->count(),
            'images' => $images,
            'bids' => $bids,
            'comments' => $comments,
            'current_user_avatar' => $current_user->avatar,
        ];
    }

    public function get_post_bids(User $user, int $post_id)
    {
        $post = Post::with([
            'bids' => function ($query) {
                $query->with('user')->orderBy('amount');
            },
        ])->find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }
        $bids = [];

        foreach ($post->bids as $bid) {
            $bids[] = [
                'user_name' => $bid->user->first_name.' '.$bid->user->last_name,
                'avatar' => $bid->user->avatar,
                'amount' => $bid->amount,
                'created_at' => Carbon::parse($bid->created_at)->diffForHumans(),
                'status' => $bid->status,
            ];
        }

        return $bids;
    }

    public function get_post_reviews(int $post_id)
    {
        $post = Post::with(['reviews', 'reviews.user'])->find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

        $reviews = [];

        foreach ($post->reviews as $review) {
            $reviews[] = [
                'user_id' => $review->user->id,
                'user_name' => $review->user->first_name.' '.$review->user->last_name,
                'avatar' => $review->user->avatar,
                'rating' => $review->rating,
                'description' => $review->data,
                'created_at' => $review->created_at->format('d M'),
            ];
        }

        return $reviews;
    }

    public function get_post_comments(User $user, int $post_id)
    {
        $post = Post::with(['comments', 'comments.user'])->find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }
        $comments = [];

        foreach ($post->comments as $comment) {
            $comments[] = [
                'post_id' => $post->id,
                'user_id' => $comment->user->id,
                'user_name' => $comment->user->first_name.' '.$comment->user->last_name,
                'avatar' => $comment->user->avatar,
                'comment' => $comment->data,
            ];
        }

        return $comments;
    }

    public function edit_post(
        User $user,
        int $post_id,
        ?string $title,
        ?string $description,
        ?string $lat,
        ?string $long,
        ?string $city,
        ?string $state,
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
        $post->lat = $lat ?? $post->lat ?? null;
        $post->long = $long ?? $post->long ?? null;
        $post->city = $city ?? $post->city ?? null;
        $post->state = $state ?? $post->state ?? null;
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

    public function delete_post(User $user, int $post_id)
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

    public function search_all(User $current_user, string $search_query)
    {
        $search_terms = explode(' ', $search_query);
        $searched_posts = [];
        $searched_users = [];

        $posts = Post::where(
            function ($query) use ($search_query, $search_terms) {
                $query->where('title', 'like', '%'.$search_query.'%');

                foreach ($search_terms as $term) {
                    $query->orWhere('description', 'like', '%'.$term.'%');
                }
            }
        )
            ->with('user')
            ->orderBy('created_at', 'desc')->get();

        foreach ($posts as $post) {
            $distance = $this->calculateDistance(
                $current_user->lat,
                $current_user->long,
                $post->lat,
                $post->long,
            );

            $searched_posts[] = [
                'type' => 'posts',
                'post_id' => $post->id,
                'user_id' => $post->user->id,
                'user_name' => $post->user->first_name.' '.$post->user->last_name,
                'avatar' => $post->user->avatar,
                'post_type' => $post->type,
                'created_at' => $post->created_at->diffForHumans(),
                'budget' => $post->budget,
                'duration' => Carbon::parse($post->start_date)->format('d M').' - '.Carbon::parse($post->end_date)->format('d M y'),
                'location' => $post->location,
                'distance' => round($distance, 2).' miles away',
                'title' => $post->title,
                'description' => $post->description,
            ];
        }

        $users = User::where(
            function ($query) use ($search_terms) {
                foreach ($search_terms as $term) {
                    $query->orWhere('first_name', 'like', '%'.$term.'%')
                        ->orWhere('last_name', 'like', '%'.$term.'%');
                }
            }
        )->get();

        foreach ($users as $user) {
            $searched_users[] = [
                'type' => 'peoples',
                'user_id' => $user->id,
                'user_name' => $user->first_name.' '.$user->last_name,
                'avatar' => $user->avatar,
            ];
        }

        return [
            'posts' => $searched_posts,
            'people' => $searched_users,
        ];
    }

    public function get_placed_bids(User $user)
    {
        if (! $user) {
            throw new Exceptions\UserNotFound;
        }

        $user_bids = PostBid::where('user_id', $user->id)->get();
        $placed_bids = [];

        if ($user_bids->isNotEmpty()) {
            $post_ids = $user_bids->pluck('post_id')->toArray();
            $posts = Post::whereIn('id', $post_ids)->with('user')->get()->keyBy('id');

            foreach ($user_bids as $bid) {
                $post = $posts->get($bid->post_id);

                if ($post) {
                    $placed_bids[] = [
                        'post_id' => $bid->post_id,
                        'bid_status' => $bid->status,
                        'bid_placed_amount' => $bid->amount,
                        'duration' => Carbon::parse($post->start_date)->format('d M').' - '.
                                      Carbon::parse($post->end_date)->format('d M y'),
                        'title' => $post->title,
                        'description' => $post->description,
                        'user_name' => $post->user->first_name.' '.$post->user->last_name,
                        'avatar' => $post->user->avatar,
                    ];
                }
            }
        }

        return $placed_bids;
    }

    public function get_placed_bid_status(User $user, int $post_id)
    {
        $post = Post::where('id', $post_id)->with('user:id,first_name,last_name,avatar')
            ->first();

        if (! $post) {
            throw new Exeptions\InvalidPostId;
        }

        $user_bid = PostBid::where('user_id', $user->id)->where('post_id', $post_id)->first();

        if (! $user_bid) {
            throw new Exceptions\BidNotFound;
        }

        $bids_data = [];
        $post_bids = PostBid::where('post_id', $post_id)
            ->with('user:id,first_name,last_name,avatar')
            ->orderBy('amount', 'asc')
            ->get();

        $distance = $this->calculateDistance(
            $user->lat,
            $user->long,
            $post->lat,
            $post->long,
        );

        foreach ($post_bids as $post_bid) {
            $bids_data[] = [
                'post-id' => $post_bid->post_id,
                'user_id' => $post_bid->user->id,
                'user_name' => $post_bid->user->first_name.' '.$post_bid->user->last_name,
                'avatar' => $post->user->avatar,
                'bid_amount' => $post_bid->amount,
                'bid_status' => $post_bid->status,
            ];
        }

        return [
            'post_id' => $post->id,
            'user_name' => $post->user->first_name.' '.$post->user->last_name,
            'avatar' => $post->user->avatar,
            'post_type' => $post->type,
            'location' => $post->city,
            'distance' => round($distance, 2).' miles away',
            'title' => $post->title,
            'description' => $post->description,
            'duration' => Carbon::parse($post->start_date)->format('d M').' - '.
                          Carbon::parse($post->end_date)->format('d M y'),
            'user_bid_amount' => $user_bid->amount,
            'bids' => $bids_data,
        ];
    }

    public function cancel_placed_bid(User $user, int $post_id)
    {
        $post = Post::where('id', $post_id)->with('user:id,first_name,last_name,avatar')
            ->first();

        if (! $post) {
            throw new Exeptions\InvalidPostId;
        }

        $user_bid = PostBid::where('user_id', $user->id)->where('post_id', $post_id)->first();

        if (! $user_bid) {
            throw new Exceptions\BidNotFound;
        }

        $bid_ref = $this->db->collection('bids')->document($user->uid);
        $bid = $bid_ref->collection('post_bid')->document($post_id)->snapshot();

        if ($bid->exists()) {
            $bid_data = $bid->data();
            if (isset($bid_data['post_id']) && $bid_data['post_id'] == $post_id) {
                $bid->reference()->delete();
            }
        }

        $user_bid->delete();

        return [
            'message' => 'Your bid has been canceled',
        ];
    }

    public function get_received_bids(User $user)
    {
        $user_posts = $user->posts->pluck('id');

        if ($user_posts->isEmpty()) {
            throw new Exceptions\PostNotFound;
        }

        $received_bids = PostBid::whereIn('post_id', $user_posts)
           ->with(['user:id,first_name,last_name,avatar', 'post'])->get();

        return $received_bids->map(function ($received_bid)
        {
            return [
                'bid_id' => $received_bid->id,
                'post_id' => $received_bid->post_id,
                'bid_amount' => $received_bid->amount,
                'status' => $received_bid->status,
                'user_id' => $received_bid->user->id,
                'user_name' => $received_bid->user->first_name . ' ' . $received_bid->user->last_name,
                'avatar' => $received_bid->user->avatar,
                'budget' => $received_bid->post->budget,
                'duration' => Carbon::parse($received_bid->post->start_date)->format('d M').' - '.
                              Carbon::parse($received_bid->post->end_date)->format('d M y'),
                'title' => $received_bid->post->title,
                'description' => $received_bid->post->description,
                'created_at' => $received_bid->post->created_at->diffForHumans(),
            ];
        });
    }
}
