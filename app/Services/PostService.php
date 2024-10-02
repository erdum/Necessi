<?php

namespace App\Services;

use App\Models\User;
use App\Models\Post;
use App\Models\PostImage;
use Kreait\Firebase\Factory;
use Illuminate\Http\UploadedFile;
use App\Jobs\StoreImages;

class PostService
{
    protected $db;

    public function __construct(
        Factory $factory,
    ) {
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
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
                    'avatars',
                    $avatar_name
                );
            }
        }

        return $post;
    }
}