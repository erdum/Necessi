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

    public function __construct(
        FirebaseNotificationService $notification_service
    ) {
        $this->notification_service = $notification_service;
    }

    protected function make_bid_status(PostBid $bid)
    {

        if ($bid->status == 'pending') {
            return 'pending';
        }

        if ($bid->status == 'rejected') {
            return 'rejected';
        }

        if ($bid->status == 'accepted') {

            if ($bid->order?->transaction_id == null) {
                $check_time = Carbon::parse($bid->order?->created_at)->addDay();

                if ($check_time->isPast()) {
                    return 'canceled';
                } else {
                    return 'payment pending';
                }
            }

            return 'paid';
        }
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
        int $post_id,
        int $amount
    ) {
        $post = Post::find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }
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

        if ($post->user_id == $user->id) {
            throw new Exceptions\BaseException(
                'You cannot place a bid on your own post.', 400
            );
        }

        $existing_bid = PostBid::where('user_id', $user->id)
            ->where('post_id', $post_id)->first();

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
        $post_bid->post_id = $post_id;
        $post_bid->amount = $amount;
        $post_bid->status = 'pending';
        $post_bid->save();

        $receiver_user = $post->user;

        $this->notification_service->push_notification(
            $receiver_user,
            NotificationType::BID,
            $user->full_name,
            ' has placed bid on your post',
            $user->avatar ?? '',
            [
                'description' => $user->about,
                'sender_id' => $user->id,
                'post_id' => $post->id,
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

        $receiver_user = $bid->user;

        $this->notification_service->push_notification(
            $receiver_user,
            NotificationType::BID,
            $user->full_name,
            ' has accepted your bid request',
            $user->avatar ?? '',
            [
                'description' => $user->about,
                'sender_id' => $user->id,
                'post_id' => $bid->post_id,
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

        $receiver_user = $bid->user;

        $this->notification_service->push_notification(
            $receiver_user,
            NotificationType::BID,
            $user->full_name,
            ' has rejected your bid request',
            $user->avatar ?? '',
            [
                'description' => $user->about,
                'sender_id' => $user->id,
                'post_id' => $bid->post_id,
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
                'lat' => $post->lat,
                'long' => $post->long,
                'distance' => round($distance, 2).' miles away',
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

    public function get_user_review(User $user, int $post_id)
    {
        $post = Post::find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

        $review = Review::where('user_id', $user->id)->where('post_id', $post_id)
            ->with('user:id,first_name,last_name,avatar')->first();

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
        int $post_id,
        string $description,
        int $rating,
    ) {
        $post = Post::find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

        $review = new Review;
        $review->user_id = $user->id;
        $review->post_id = $post_id;
        $review->data = $description;
        $review->rating = $rating;
        $review->save();

        return $review;
    }

    public function get_all_posts(User $user)
    {
        $posts = Post::orderBy('created_at', 'desc')
            ->with('user:id,first_name,last_name,avatar', 'bids.order')
            ->paginate(3);

        $posts->getCollection()->transform(function ($post) use ($user) {
            $self_liked = $post->likes()->where('user_id', $user->id)->exists();
            $self_bid = $post->bids()->where('user_id', $user->id)->where('post_id', $post->id)->exists();

            $order_status = $post->bids->filter(function ($bid) {
                return $bid->order !== null;
            })->isNotEmpty();

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
                'location' => $post->location,
                'lat' => $post->lat,
                'long' => $post->long,
                'distance' => round($distance, 2).' miles away',
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
                'current_user_like' => $self_liked,
                'current_user_bid' => $self_bid,
                'likes' => $post->likes->count(),
                'bids' => $post->bids->count(),
                'images' => $post->images,
                'paid' => $order_status,
            ];
        });

        return $posts;
    }

    public function toggle_like(User $user, int $post_id)
    {
        $post = Post::find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

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

        if ($post->user_id !== $user->id) {
            $receiver_user = Post::find($post_id)?->user;

            $this->notification_service->push_notification(
                $receiver_user,
                NotificationType::ACTIVITY,
                $user->full_name,
                ' has liked your post',
                $user->avatar ?? '',
                [
                    'description' => $user->about,
                    'sender_id' => $user->id,
                    'post_id' => $post_id,
                ]
            );
        }

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

    public function delete_post_comment(User $user, int $comment_id)
    {
        $comment = PostComment::find($comment_id);

        if (! $comment) {
            throw new Exceptions\BaseException(
                'Comment not found',
                404
            );
        }

        $comment->delete();

        return ['message' => 'Comment has been successfully deleted'];
    }

    public function report_post_comment(
        User $user,
        int $comment_id,
        string $reason_type,
        ?string $other_reason,
    ) {
        $comment = PostComment::find($comment_id);

        if (! $comment) {
            throw new Exceptions\BaseException(
                'Comment not found',
                404
            );
        }

        $reported_comment = ReportedComment::where('reporter_id', $user->id)
            ->where('reported_id', $comment_id)->first();

        if ($reported_comment) {
            throw new Exceptions\BaseException(
                'Comment already reported',
                400
            );
        } else {
            $comment_report = new ReportedComment;
            $comment_report->reporter_id = $user->id;
            $comment_report->reported_id = $comment_id;
            $comment_report->reason_type = $reason_type;
            $comment_report->other_reason = $other_reason ?: null;
            $comment_report->save();
        }

        return [
            'message' => 'Comment successfully reported',
        ];
    }

    public function report_post(
        User $user,
        string $reason_type,
        ?string $other_reason,
        int $post_id,
    ) {
        $post = Post::find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

        $post_report = new ReportedPost;
        $post_report->reporter_id = $user->id;
        $post_report->reported_id = $post_id;
        $post_report->reason_type = $reason_type;
        $post_report->other_reason = $other_reason ?: null;
        $post_report->save();

        return [
            'message' => 'Post successfully reported',
        ];
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
        $is_lowest = true;

        if (! $post_details) {
            throw new Exceptions\InvalidPostId;
        }

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
        ];
    }

    public function get_post_preview(int $post_id, User $user)
    {
        $post = Post::findOrFail($post_id);

        return [
            'post_user_name' => $post->user->full_name,
            'post_user_avatar' => $post->user->avatar,
            'post_budget' => $post->budget,
            'post_duration' => $post->start_date->format('d M').' - '.$post->end_date->format('d M Y'),
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
                'user_name' => $review->user->full_name,
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
        $post = Post::with([
            'comments' => function ($query) {
                $query->orderBy('created_at', 'asc');
            },
            'comments.user',
        ])->find($post_id);

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }
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
                'user_name' => $post->user->full_name,
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
                'user_name' => $user->full_name,
                'avatar' => $user->avatar,
            ];
        }

        return [
            'posts' => $searched_posts,
            'people' => $searched_users,
        ];
    }

    public function get_placed_bids(User $user, ?string $bid_id)
    {
        if (! $user) {
            throw new Exceptions\UserNotFound;
        }

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
                'distance' => round($distance, 2).' miles away',
                'title' => $user_bid->post->title,
                'decscription' => $user_bid->post->description,
                'budget' => $user_bid->post->budget,
                'duration' => Carbon::parse($user_bid->post->start_date)->format('d M').' - '.
                              Carbon::parse($user_bid->post->end_date)->format('d M y'),
                'current_user_name' => $user->full_name,
                'curretn_user_avatar' => $user->avatar,
                'current_user_bid_amount' => $user_bid->amount,
                'bid_status' => $this->make_bid_status($user_bid),
                'chat_id' => $chat_id,
            ];
        }

        $user_bids = PostBid::where('user_id', $user->id)->get();
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
                        'bid_status' => $this->make_bid_status($bid),
                        'bid_placed_amount' => $bid->amount,
                        'duration' => Carbon::parse($post->start_date)->format('d M').' - '.
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

    public function remove_rejected_bid(User $user, int $bid_id)
    {
        $user_bid = $user->bids()->where('id', $bid_id)->first();

        if (! $user_bid) {
            throw new Exceptions\BidNotFound;
        }

        if ($user_bid->status == 'rejected') {
            $user_bid->delete();

            return [
                'message' => 'Bid successfully removed',
            ];
        } else {
            throw new Exceptions\BidNotFound;
        }
    }

    public function get_placed_bid_status(User $user, int $post_id)
    {
        $post = Post::where('id', $post_id)->with('user:id,first_name,last_name,avatar')
            ->first();

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }
        $user_bid = $user->bids()->where('post_id', $post_id)->first();

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

        return [
            'post_id' => $post->id,
            'user_name' => $post->user->full_name,
            'avatar' => $post->user->avatar,
            'post_type' => $post->type,
            'location' => $post->location,
            'distance' => round($distance, 2).' miles away',
            'title' => $post->title,
            'description' => $post->description,
            'duration' => Carbon::parse($post->start_date)->format('d M').' - '.
                          Carbon::parse($post->end_date)->format('d M y'),
            'user_bid_amount' => $user_bid->amount,
            'payment_status' => 'pending',
            'bids' => $bids_data,
        ];
    }

    public function cancel_placed_bid(User $user, int $post_id)
    {
        $post = Post::where('id', $post_id)->with('user:id,first_name,last_name,avatar')
            ->first();

        if (! $post) {
            throw new Exceptions\InvalidPostId;
        }

        $user_bid = PostBid::where('user_id', $user->id)->where('post_id', $post_id)->first();

        if (! $user_bid) {
            throw new Exceptions\BidNotFound;
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
            return [];
        }

        $received_bids = PostBid::whereIn('post_id', $user_posts)->whereNot('status', 'rejected')
            ->with(['user:id,first_name,last_name,avatar', 'post'])->get();

        return $received_bids->map(function ($received_bid) {
            return [
                'bid_id' => $received_bid->id,
                'post_id' => $received_bid->post_id,
                'bid_amount' => $received_bid->amount,
                'status' => $this->make_bid_status($received_bid),
                'user_id' => $received_bid->user->id,
                'user_name' => $received_bid->user->full_name,
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
