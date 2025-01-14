<?php

namespace App\Services;

use App\Exceptions;
use App\Jobs\StoreImages;
use App\Models\ConnectionRequest;
use App\Models\Post;
use App\Models\PostBid;
use App\Models\PostComment;
use App\Models\PostImage;
use App\Models\PostLike;
use App\Models\ReportedComment;
use App\Models\ReportedPost;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;

class PostService
{
    protected $notification_service;

    protected $stripe_service;

    public function __construct() {
        $this->notification_service = app(FirebaseNotificationService::class);
        $this->stripe_service = app(StripeService::class);
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
        string $city,
        string $state,
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
        $post->city = $city;
        $post->state = $state;
        $post->location = $location;
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
                $post_image->url = urlencode("avatars/$avatar_name.webp");
                $post_image->save();

                StoreImages::dispatchAfterResponse(
                    $avatar->path(),
                    'avatars',
                    $avatar_name,
                    'firestorage'
                );
            }
            $post->images;
        }

        return $post;
    }

    public function place_bid(
        User $user,
        Post $post,
        int $amount
    ) {
        // $lowest_bid = $post->bids()->orderBy('amount')->first();

        // if ($lowest_bid) {
        //     if ($amount >= $lowest_bid?->amount) {
        //         throw new Exceptions\BaseException(
        //             'New bid amount must be less than the previous bids', 400
        //         );
        //     }
        // }

        // if ($amount > $post->budget) {
        //     throw new Exceptions\BaseException(
        //         'The bid amount should be less than the budget amount', 400
        //     );
        // }

        if ($user->is_blocked($post->user->id)) {
            throw new Exceptions\BaseException(
                'You cannot place a bid as you have blocked the post user.',
                400
            );
        }

        if ($user->is_blocker($post->user->id)) {
            throw new Exceptions\BaseException(
                'You cannot place a bid as you are blocked by the post user.',
                400
            );
        }

        if ($post->user_id == $user->id) {
            throw new Exceptions\BaseException(
                'You cannot place a bid on your own post.', 400
            );
        }

        if (! $this->stripe_service->is_account_active($user)) {
            throw new Exceptions\BaseException(
                'User does not have an active Stripe Connect account.',
                400
            );
        }

        if (
            PostBid::where('post_id', $post->id)->where('status', 'accepted')
                ->exists()
        ) {
            throw new Exceptions\BaseException(
                'Biding closed on this post.',
                400
            );
        }

        $existing_bid = PostBid::where('user_id', $user->id)
            ->where('post_id', $post->id)->first();

        if ($existing_bid) {
            $existing_bid->amount = $amount;
            $existing_bid->status = 'pending';
            $existing_bid->save();

            return [
                'message' => 'Your bid has been updated successfully',
            ];
        }

        $post_bid = new PostBid;
        $post_bid->user_id = $user->id;
        $post_bid->post_id = $post->id;
        $post_bid->amount = $amount;
        $post_bid->status = 'pending';
        $post_bid->save();

        $receiver_user = $post->user;

        $this->notification_service->push_notification(
            $receiver_user,
            NotificationType::BID,
            $receiver_user->full_name,
            " you have received a new bid on {$post->title}. Accept or decline now!",
            $receiver_user->avatar ?? '',
            [
                'description' => $user->about,
                'sender_id' => $user->id,
                'post_id' => $post->id,
                'notification_type' => 'post_details',
            ]
        );

        return [
            'message' => 'Your bid has been placed successfully',
        ];
    }

    public function accept_post_bid(User $user, PostBid $bid)
    {
        if ($bid->post->user_id != $user->id) {
            throw new Exceptions\PostOwnership;
        }

        $has_accepted_bid = $bid->post->bids()->where('status', 'accepted')
            ->exists();

        if ($has_accepted_bid) {
            throw new Exceptions\BaseException(
                'This post have an already accepted bid.',
                400
            );
        }

        $bid->status = 'accepted';
        $bid->save();

        $post_bids = $bid->post->bids()->whereNot('status', 'accepted');

        foreach ($post_bids as $post_bid) {
            $this->decline_post_bid($user, $post_bid);
        }

        $receiver_user = $bid->user;

        foreach ([$user, $receiver_user] as $not_user) {
            $notification_receiver = $not_user->id === $user->id 
              ? $receiver_user : $user;
    
            $this->notification_service->push_notification(
                $not_user,
                NotificationType::BID,
                $not_user->full_name,
                $not_user->id === $user->id
                    ? "You accepted a bid for {$bid->post->title}. Payment must be made within 24 hours to confirm the bid."
                    : "Your bid has been accepted! View details and next steps.",
                $notification_receiver->avatar ?? '',
                [
                    'description' => $user->about,
                    'sender_id' => $user->id,
                    'post_id' => $bid->post_id,
                    'notification_type' => $not_user->id === $user->id
                        ? 'you_accepted_bid'
                        : 'bid_accepted',
                    'bid_chip' => 0,
                ]
            );
        }

        return [
            'message' => 'You have successfully accepted the bid',
        ];
    }

    public function decline_post_bid(User $user, PostBid $bid)
    {
        if ($bid->post->user_id != $user->id) {
            throw new Exceptions\PostOwnership;
        }

        if ($bid->order?->transaction_id) {
            throw new Exceptions\BaseException(
                'Payment has been made on this bid, it cannot be declined.',
                400
            );
        }

        if ($bid->status == 'rejected') {
             throw new Exceptions\BaseException(
                 'This bid has already been rejected.', 400
             );
        }

        $bid->status = 'rejected';
        $bid->save();

        $receiver_user = $bid->user;

        $this->notification_service->push_notification(
            $receiver_user,
            NotificationType::BID,
            $receiver_user->full_name,
            ' Unfortunately, your bid was rejected. View details to try again',
            $receiver_user->avatar ?? '',
            [
                'description' => $user->about,
                'sender_id' => $user->id,
                'post_id' => $bid->post_id,
                'notification_type' => 'bid_rejected',
                'bid_chip' => 1,
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

            $distance = $this->calculateDistance(
                $user->lat,
                $user->long,
                $post->lat,
                $post->long,
            );

            $order_status = $post->bids->filter(function ($bid) {
                return $bid->order !== null;
            })->isNotEmpty();

            return [
                'post_id' => $post->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                'user_id' => $post->user_id,
                'type' => $post->type,
                'title' => $post->title,
                'description' => $post->description,
                'location' => $post->location,
                'location_details' => $post->city . ', ' . $post->state,
                'lat' => $post->lat,
                'long' => $post->long,
                'distance' => round($distance, 2).' miles away',
                'budget' => $post->budget,
                'duration' => ($post->start_time && $post->end_time)
                    ? Carbon::parse($post->start_time)->format('h:i A').' - '.Carbon::parse($post->end_time)->format('h:i A')
                    : null,
                'date' => Carbon::parse($post->start_date)->format('d M').' - '.Carbon::parse($post->end_date)->format('d M y'),
                'start_date' => $post->start_date->format('d M Y'),
                'end_date' => $post->end_date->format('d M Y'),
                'start_time' => $post->start_time?->format('h:i A'),
                'end_time' => $post->end_time?->format('h:i A'),
                'created_at' => $post->created_at->diffForHumans(),
                'delivery_requested' => (bool) $post->delivery_requested,
                'bids' => $post->bids->count(),
                'current_user_like' => $current_user_like,
                'likes' => $post->likes->count(),
                'paid' => $order_status,
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
                'user_name' => $review->user->full_name,
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

    public function get_user_review(User $user, Post $post)
    {
        $review = Review::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->with('user:id,first_name,last_name,avatar')
            ->first();

        if (! $review) {
            return [];
        }

        return [
            'review_id' => $review->id,
            'user_id' => $review->user_id,
            'user_name' => $review->user->full_name,
            'avatar' => $review->user->avatar,
            'post_id' => $review->post_id,
            'description' => $review->data,
            'rating' => $review->rating,
        ];
    }

    public function place_post_review(
        User $user,
        Post $post,
        string $description,
        int $rating,
    ) {
        if ($post->user_id == $user->id) {
            throw new Exceptions\BaseException(
                'User cannot place review on its own post',
                400
            );
        }

        $review = new Review;
        $review->user_id = $user->id;
        $review->post_id = $post->id;
        $review->data = $description;
        $review->rating = $rating;
        $review->save();

        return $review;
    }

    public function get_all_posts(?User $user)
    {
        $posts = Post::whereDoesntHave('bids')
            ->orWhereHas('bids', function ($query) {
                $query->whereNot('status', 'accepted');
            })
            ->when($user, function ($query) use ($user) {
                $query->whereDoesntHave(
                    'user.blocked_users',
                    function ($query) use ($user) {
                        $query->where('blocked_id', $user->id);
                    }
                )
                ->whereDoesntHave(
                    'user.blocker_users',
                    function ($query) use ($user) {
                        $query->where('blocker_id', $user->id);
                    }
                );
            })
            ->orderBy('created_at', 'desc')
            ->with('user:id,first_name,last_name,avatar', 'bids.order')
            ->paginate(3);

        $posts->getCollection()->transform(function ($post) use ($user) {

            if ($user) {
                $self_liked = $post->likes()->where('user_id', $user->id)
                    ->exists();
                $self_bid = $post->bids()->where('user_id', $user->id)
                    ->where('post_id', $post->id)->exists();

                $distance = $this->calculateDistance(
                    $user->lat,
                    $user->long,
                    $post->lat,
                    $post->long
                );
            }

            $order_status = $post->bids->filter(function ($bid) {
                return $bid->order !== null;
            })->isNotEmpty();

            return [
                'post_id' => $post->id,
                'user_id' => $post->user_id,
                'first_name' => $post->user->first_name,
                'last_name' => $post->user->last_name,
                'avatar' => $post->user->avatar,
                'type' => $post->type,
                'title' => $post->title,
                'description' => $post->description,
                'location' => $post->location,
                'location_details' => $post->city . ', ' . $post->state,
                'lat' => $post->lat,
                'long' => $post->long,
                'distance' => round($distance ?? rand(2, 50), 2).' miles away',
                'budget' => $post->budget,
                'duration' => ($post->start_time && $post->end_time)
                    ? Carbon::parse($post->start_time)->format('h:i A').' - '.Carbon::parse($post->end_time)->format('h:i A')
                    : null,
                'date' => $post->start_date->format('d M').' - '.$post->end_date->format('d M Y'),
                'start_date' => $post->start_date->format('d M Y'),
                'end_date' => $post->end_date->format('d M Y'),
                'start_time' => $post->start_time?->format('h:i A'),
                'end_time' => $post->end_time?->format('h:i A'),
                'delivery_requested' => (bool) $post->delivery_requested,
                'created_at' => str_replace(
                    [' seconds ago', ' second ago', ' minutes ago', ' minute ago', ' hours ago', ' hour ago'],
                    [' sec ago', ' sec ago', ' mins ago', ' min ago', ' hrs ago', ' hr ago'],
                    $post->created_at->diffForHumans()
                ),
                'current_user_like' => $self_liked ?? false,
                'current_user_bid' => $self_bid ?? false,
                'likes' => $post->likes->count(),
                'bids' => $post->bids->count(),
                'images' => $post->images,
                'paid' => $order_status,
            ];
        });

        return $posts;
    }

    public function toggle_like(User $user, Post $post)
    {
        $like = PostLike::where('post_id', $post->id)
            ->where('user_id', $user->id)->first();

        if ($like) {
            $like->delete();

            return ['message' => 'Post successfully un-liked'];
        }

        $like = new PostLike;
        $like->post_id = $post->id;
        $like->user_id = $user->id;
        $like->save();

        if ($post->user_id !== $user->id) {
            $receiver_user = Post::find($post->id)?->user;

            $this->notification_service->push_notification(
                $receiver_user,
                NotificationType::ACTIVITY,
                $user->full_name,
                ' has liked your post',
                $user->avatar ?? '',
                [
                    'description' => $user->about,
                    'sender_id' => $user->id,
                    'post_id' => $post->id,
                    'notification_type' => 'post_details',
                ]
            );
        }

        return ['message' => 'Post successfully liked'];
    }

    public function place_comment(
        User $user,
        Post $post,
        string $post_comment
    ) {
        $comment = new PostComment;
        $comment->user_id = $user->id;
        $comment->post_id = $post->id;
        $comment->data = $post_comment;
        $comment->save();

        if ($post->user_id !== $user->id) {
            $receiver_user = $post->user;
            $type = 'placed_comment';

            $this->notification_service->push_notification(
                $receiver_user,
                NotificationType::ACTIVITY,
                $user->full_name,
                ' has commented on your post',
                $user->avatar ?? '',
                [
                    'description' => $user->about,
                    'sender_id' => $user->id,
                    'post_id' => $post->id,
                    'notification_type' => 'post_details',
                ]
            );
        }

        return [
            'id' => $comment->id,
            'post_id' => $comment->post_id,
            'user_id' => $comment->user_id,
            'user_name' => $user->full_name,
            'avatar' => $user->avatar,
            'comment' => $comment->data,
            'created_at' => $comment->created_at->diffForHumans(),
        ];
    }

    public function delete_post_comment(User $user, PostComment $comment)
    {
        $comment->delete();

        return ['message' => 'Comment has been successfully deleted'];
    }

    public function report_post_comment(
        User $user,
        PostComment $comment,
        string $reason_type,
        ?string $other_reason,
    ) {
        $comment_report = new ReportedComment;
        $comment_report->reporter_id = $user->id;
        $comment_report->reported_id = $comment->id;
        $comment_report->reason_type = $reason_type;
        $comment_report->other_reason = $other_reason ?: null;
        $comment_report->save();

        return [
            'message' => 'Comment successfully reported',
        ];
    }

    public function report_post(
        User $user,
        Post $post,
        string $reason_type,
        ?string $other_reason
    ) {
        $post_report = new ReportedPost;
        $post_report->reporter_id = $user->id;
        $post_report->reported_id = $post->id;
        $post_report->reason_type = $reason_type;
        $post_report->other_reason = $other_reason ?: null;
        $post_report->save();

        return [
            'message' => 'Post successfully reported',
        ];
    }

    public function get_post_details(User $current_user, Post $post)
    {
        $post_details = Post::with([
            'bids' => function ($query) {
                $query->with('user')->orderBy('amount')->limit(4);
            },
            'comments' => function ($query) {
                $query->with('user')->latest();
            },
        ])->with('user')->findOrFail($post->id);

        $comments = [];
        $bids = [];
        $images = [];
        $is_lowest = true;

        foreach ($post_details->bids as $bid) {
            $bids[] = [
                'user_name' => $bid->user->full_name,
                'avatar' => $bid->user->avatar,
                'amount' => $bid->amount,
                'created_at' => Carbon::parse($bid->created_at)->diffForHumans(),
                'status' => $bid->status,
                'is_lowest' => $is_lowest,
            ];
            $is_lowest = false;
        }

        foreach ($post_details->images as $image) {
            $images[] = [
                'image' => $image->url,
            ];
        }

        foreach ($post_details->comments as $comment) {
            $comments[] = [
                'id' => $comment->id,
                'user_id' => $comment->user->id,
                'avatar' => $comment->user->avatar,
                'user_name' => $comment->user->full_name,
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

        $order_status = $post_details->bids->filter(function ($bid) {
            return $bid->order !== null;
        })->isNotEmpty();

        return [
            'post_id' => $post_details->id,
            'user_id' => $post_details->user->id,
            'first_name' => $post_details->user->first_name,
            'last_name' => $post_details->user->last_name,
            'avatar' => $post_details->user->avatar,
            'type' => $post_details->type,
            'created_at' => $post_details->created_at->diffForHumans(),
            'budget' => $post_details->budget,
            'duration' => ($post_details->start_time && $post_details->end_time)
                ? Carbon::parse($post_details->start_time)->format('h:i A').' - '.Carbon::parse($post_details->end_time)->format('h:i A')
                : null,
            'date' => $post_details->start_date->format('d M').' - '.$post_details->end_date->format('d M Y'),
            'location' => $post_details->location,
            'location_details' => $post_details->city . ', ' . $post_details->state,
            'distance' => round($distance, 2).' miles away',
            'title' => $post_details->title,
            'description' => $post_details->description,
            'current_user_like' => $current_user_like,
            'likes' => $post_details->likes->count(),
            'images' => $images,
            'bids' => $bids,
            'comments' => $comments,
            'current_user_name' => $current_user->full_name,
            'current_user_avatar' => $current_user->avatar,
            'own_post' => $current_user->id == $post_details->user->id,
            'paid' => $order_status,
        ];
    }

    public function get_post_preview(User $user, Post $post)
    {
        $has_accepted_bid = $post->bids()->where('status', 'accepted')
            ->exists();

        return [
            'post_id' => $post->id,
            'post_user_name' => $post->user->full_name,
            'post_user_avatar' => $post->user->avatar,
            'post_budget' => $post->budget,
            'post_duration' => $post->start_date->format('d M').' - '.$post->end_date->format('d M Y'),
            'has_accepted_bid' => $has_accepted_bid,
        ];
    }

    public function get_post_bids(User $user, Post $post)
    {
        $post = Post::with([
            'bids' => function ($query) {
                $query->with('user')->orderBy('amount');
            },
        ])->findOrFail($post->id);

        $bids = [];
        $status = 'lowest';

        foreach ($post->bids as $bid) {
            $bids[] = [
                'user_name' => $bid->user->full_name,
                'avatar' => $bid->user->avatar,
                'amount' => $bid->amount,
                'created_at' => Carbon::parse($bid->created_at)->diffForHumans(),
                'status' => $status,
            ];
            $status = $bid->status;
        }

        return $bids;
    }

    public function get_post_reviews(Post $post)
    {
        $reviews = [];

        foreach ($post->reviews as $review) {
            $reviews[] = [
                'user_id' => $review->user->id,
                'user_name' => $review->user->full_name,
                'avatar' => $review->user->avatar,
                'rating' => $review->rating,
                'description' => $review->data,
                'created_at' => $review->created_at->format('d M'),
            ];
        }

        return $reviews;
    }

    public function get_post_comments(User $user, Post $post)
    {
        $post = Post::with([
            'comments' => function ($query) {
                $query->orderBy('created_at', 'asc');
            },
            'comments.user',
        ])->findOrFail($post->id);

        $comments = [];

        foreach ($post->comments as $comment) {
            $comments[] = [
                'id' => $comment->id,
                'post_id' => $post->id,
                'user_id' => $comment->user->id,
                'user_name' => $comment->user->full_name,
                'avatar' => $comment->user->avatar,
                'comment' => $comment->data,
                'created_at' => $comment->created_at->diffForHumans(),
            ];
        }

        return [
            'current_user_name' => $user->full_name,
            'current_user_avatar' => $user->avatar,
            'comments' => $comments,
        ];
    }

    public function edit_post(
        User $user,
        Post $post,
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
        if ($user->id != $post->user_id) {
            throw new Exceptions\PostOwnership;
        }

        $post->title = $title ?? $post->title ?? null;
        $post->description = $description ?? $post->description ?? null;
        $post->lat = $lat ?? $post->lat ?? null;
        $post->long = $long ?? $post->long ?? null;
        $post->city = $city ?? $post->city ?? null;
        $post->state = $state ?? $post->state ?? null;
        $post->location = $location ?? $post->location ?? null;
        // $post->budget = $budget ?? $post->budget ?? null;
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
                $new_post_image->url = urlencode("avatars/$avatar_name.webp");
                $new_post_image->save();

                StoreImages::dispatchAfterResponse(
                    $avatar->path(),
                    'avatars',
                    $avatar_name,
                    'firestorage'
                );
            }
        }

        return $post;
    }

    public function delete_post(User $user, Post $post)
    {
        if ($user->id != $post->user_id) {
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
                $query->where('title', 'like', '%'. $search_query .'%');

                foreach ($search_terms as $term) {
                    $query->orWhere('description', 'like', '%'.$term.'%');
                }
            }
        )
            ->whereDoesntHave(
                'user.blocked_users',
                function ($query) use ($current_user) {
                    $query->where('blocked_id', $current_user->id);
                }
            )
            ->whereDoesntHave(
                'user.blocker_users',
                function ($query) use ($current_user) {
                    $query->where('blocker_id', $current_user->id);
                }
            )
            ->with('user')
            ->orderBy('created_at', 'desc')->get();
        
        $users = User::where(
            function ($query) use ($search_terms) {
                foreach ($search_terms as $term) {
                    $query->orWhere('first_name', 'like', '%'. $term .'%')
                        ->orWhere('last_name', 'like', '%'. $term .'%');
                }
            }
        )
            ->whereDoesntHave(
                'blocked_users',
                function ($query) use ($current_user) {
                    $query->where('blocked_id', $current_user->id);
                }
            )
            ->whereDoesntHave(
                'blocker_users',
                function ($query) use ($current_user) {
                    $query->where('blocker_id', $current_user->id);
                }
            )
            ->get();

        foreach ($users as $user) {
            $searched_users[] = [
                'type' => 'peoples',
                'user_id' => $user->id,
                'user_name' => $user->full_name,
                'avatar' => $user->avatar,
            ];

            foreach ($user->posts as $post) {
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
                    'user_name' => $post->user->full_name,
                    'avatar' => $post->user->avatar,
                    'post_type' => $post->type,
                    'created_at' => $post->created_at->diffForHumans(),
                    'budget' => $post->budget,
                    'duration' => ($post->start_time && $post->end_time)
                        ? Carbon::parse($post->start_time)->format('h:i A').' - '.Carbon::parse($post->end_time)->format('h:i A')
                        : null,
                    'date' => Carbon::parse($post->start_date)->format('d M').' - '.Carbon::parse($post->end_date)->format('d M y'),
                    'location' => $post->location,
                    'location_details' => $post->city . ', ' . $post->state,
                    'distance' => round($distance, 2).' miles away',
                    'title' => $post->title,
                    'description' => $post->description,
                ];
            }
        }

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
                'user_name' => $post->user->full_name,
                'avatar' => $post->user->avatar,
                'post_type' => $post->type,
                'created_at' => $post->created_at->diffForHumans(),
                'budget' => $post->budget,
                'duration' => ($post->start_time && $post->end_time)
                    ? Carbon::parse($post->start_time)->format('h:i A').' - '.Carbon::parse($post->end_time)->format('h:i A')
                    : null,
                'date' => Carbon::parse($post->start_date)->format('d M').' - '.Carbon::parse($post->end_date)->format('d M y'),
                'location' => $post->location,
                'location_details' => $post->city . ', ' . $post->state,
                'distance' => round($distance, 2).' miles away',
                'title' => $post->title,
                'description' => $post->description,
            ];
        }

        return [
            'posts' => $searched_posts,
            'people' => $searched_users,
        ];
    }

    public function get_placed_bids(User $user, ?int $bid_id)
    {
        if ($bid_id) {
            $post_ids = $user->posts()->pluck('id');
            $user_bid = PostBid::where('id', $bid_id)->whereIn('post_id', $post_ids)->with('post')->first();

            if (! $user_bid) {
                return $user_bid;
            }

            $distance = $this->calculateDistance(
                $user->lat,
                $user->long,
                $user_bid->post->lat,
                $user_bid->post->long,
            );

            $chat_id = ConnectionRequest::where([
                ['sender_id', '=', $user->id],
                ['receiver_id', '=', $user_bid->user->id],
            ])
                ->orWhere([
                    ['sender_id', '=', $user_bid->user->id],
                    ['receiver_id', '=', $user->id],
                ])
                ->value('chat_id');

            return [
                'bid_id' => $user_bid->id,
                'post_id' => $user_bid->post_id,
                'bid_user_id' => $user_bid->user->id,
                'bid_user_uid' => $user_bid->user->uid,
                'bid_user_name' => $user_bid->user->full_name,
                'avatar' => $user_bid->user->avatar,
                'type' => $user_bid->post->type,
                'location' => $user_bid->post->location,
                'location_details' => $user_bid->post->city . ', ' . $user_bid->post->state,
                'distance' => round($distance, 2).' miles away',
                'title' => $user_bid->post->title,
                'decscription' => $user_bid->post->description,
                'budget' => $user_bid->post->budget,
                'duration' => ($user_bid->post->start_time && $user_bid->post->end_time)
                    ? Carbon::parse($user_bid->post->start_time)->format('h:i A').' - '.Carbon::parse($user_bid->post->end_time)->format('h:i A')
                    : null,
                'date' => Carbon::parse($user_bid->post->start_date)->format('d M').' - '.
                              Carbon::parse($user_bid->post->end_date)->format('d M y'),
                'current_user_name' => $user->full_name,
                'curretn_user_avatar' => $user->avatar,
                'current_user_bid_amount' => $user_bid->amount,
                'created_at' => $user_bid->created_at->diffForHumans(),
                'bid_status' => $user_bid->getStatus,
                'chat_id' => $chat_id,
            ];
        }

        $user_bids = PostBid::where('user_id', $user->id)->orderBy('created_at', 'desc')
         ->get();
        $placed_bids = [
            'pending' => [],
            'accepted' => [],
            'rejected' => [],
        ];

        if ($user_bids->isNotEmpty()) {
            $post_ids = $user_bids->pluck('post_id')->toArray();
            $posts = Post::whereIn('id', $post_ids)->with('user')->get()->keyBy('id');

            foreach ($user_bids as $bid) {
                $post = $posts->get($bid->post_id);

                if ($post) {
                    $bid_data = [
                        'bid_id' => $bid->id,
                        'post_id' => $bid->post_id,
                        'bid_status' => $bid->status,
                        'bid_placed_amount' => $bid->amount,
                        'duration' => ($post->start_time && $post->end_time)
                            ? Carbon::parse($post->start_time)->format('h:i A').' - '.Carbon::parse($post->end_time)->format('h:i A')
                            : null,
                        'date' => Carbon::parse($post->start_date)->format('d M').' - '.
                                      Carbon::parse($post->end_date)->format('d M y'),
                        'title' => $post->title,
                        'description' => $post->description,
                        'user_name' => $post->user->full_name,
                        'avatar' => $post->user->avatar,
                    ];

                    if ($bid->status === 'pending') {
                        $placed_bids['pending'][] = $bid_data;
                    } elseif ($bid->status === 'accepted') {
                        $placed_bids['accepted'][] = $bid_data;
                    } elseif ($bid->status === 'rejected') {
                        $placed_bids['rejected'][] = $bid_data;
                    }
                }
            }
        }

        return $placed_bids;
    }

    public function remove_rejected_bid(User $user, Post $bid)
    {
        if ($bid->status == 'rejected') {
            $bid->delete();

            return [
                'message' => 'Bid successfully removed',
            ];
        }
    }

    public function get_placed_bid_status(User $user, Post $post)
    {
        $user_bid = $user->bids()->where('post_id', $post->id)->firstOrFail();

        $bids_data = [];
        $post_bids = PostBid::where('post_id', $post->id)
            ->with('user:id,first_name,last_name,avatar')
            ->orderBy('amount', 'asc')
            ->get();

        $distance = $this->calculateDistance(
            $user->lat,
            $user->long,
            $post->lat,
            $post->long,
        );
        $is_first = true;

        foreach ($post_bids as $post_bid) {
            $bids_data[] = [
                'post_id' => $post_bid->post_id,
                'user_id' => $post_bid->user->id,
                'user_name' => $post_bid->user->full_name,
                'avatar' => $post->user->avatar,
                'bid_amount' => $post_bid->amount,
                'bid_status' => $post_bid->status,
                'created_at' => $post_bid->created_at->diffForHumans(),
                'is_lowest' => $is_first,
            ];
            $is_first = false;
        }

        $order_status = $post->bids->filter(function ($bid) {
            return $bid->order !== null;
        })->isNotEmpty();

        return [
            'post_id' => $post->id,
            'user_name' => $post->user->full_name,
            'avatar' => $post->user->avatar,
            'post_type' => $post->type,
            'location' => $post->location,
            'location_details' => $post->city . ', ' . $post->state,
            'created_at' => $post->created_at->diffForHumans(),
            'distance' => round($distance, 2).' miles away',
            'title' => $post->title,
            'description' => $post->description,
            'duration' => ($post->start_time && $post->end_time)
                ? Carbon::parse($post->start_time)->format('h:i A').' - '.Carbon::parse($post->end_time)->format('h:i A')
                : null,
            'date' => Carbon::parse($post->start_date)->format('d M').' - '.
                      Carbon::parse($post->end_date)->format('d M y'),
            'user_bid_amount' => $user_bid->amount,
            'payment_status' => $user_bid->getStatus,
            'bids' => $bids_data,
            'paid' => $order_status,
        ];
    }

    public function cancel_placed_bid(User $user, Post $post)
    {
        $user_bid = PostBid::where('user_id', $user->id)
            ->where('post_id', $post->id)->firstOrFail();

        if ($user_bid?->order && $user_bid?->order?->transaction_id) {
            throw new Exceptions\BaseException(
                'Can not cancel this bid, as payment has been made.',
                400
            );
        }

        if($user_bid->status === 'accepted')
        {
            $this->notification_service->push_notification(
                $user,
                NotificationType::BID,
                $user->full_name,
                ' your accepted bid has been successfully canceled',
                $user->avatar ?? '',
                [
                    'description' => $user->about,
                    'sender_id' => $user->id,
                    'post_id' => $bid->post_id,
                    'notification_type' => 'bid_canceled',
                ]
            );
        }

        $user_bid->delete();

        $receiver_user = $bid->user;

        return [
            'message' => 'Your bid has been canceled',
        ];
    }

    public function get_received_bids(User $user)
    {
        $user_posts = $user->posts->pluck('id');

        if ($user_posts->isEmpty()) {
            return [];
        }

        $received_bids = PostBid::whereIn('post_id', $user_posts)->whereNot('status', 'rejected')
            ->with(['user:id,first_name,last_name,avatar', 'post'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $received_bids->map(function ($received_bid) {
            return [
                'bid_id' => $received_bid->id,
                'post_id' => $received_bid->post_id,
                'bid_amount' => $received_bid->amount,
                'status' => $received_bid->status,
                'user_id' => $received_bid->user->id,
                'user_name' => $received_bid->user->full_name,
                'avatar' => $received_bid->user->avatar,
                'budget' => $received_bid->post->budget,
                'location' => $received_bid->post->location,
                'location_details' => $received_bid->post->city . ', ' . $received_bid->post->state,
                'duration' => ($received_bid->post->start_time && $received_bid->post->end_time)
                    ? Carbon::parse($received_bid->post->start_time)->format('h:i A').' - '.Carbon::parse($received_bid->post->end_time)->format('h:i A')
                    : null,
                'date' => Carbon::parse($received_bid->post->start_date)->format('d M').' - '.
                              Carbon::parse($received_bid->post->end_date)->format('d M y'),
                'title' => $received_bid->post->title,
                'description' => $received_bid->post->description,
                'created_at' => $received_bid->post->created_at->diffForHumans(),
            ];
        });
    }
}
