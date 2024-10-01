<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\User::factory(6)->create();
        $dev = \App\Models\User::factory()->create([
            'first_name' => 'Erdum',
            'last_name' => 'Adnan',
            'email' => 'erdumadnan@gmail.com',
        ]);

        $posts = \App\Models\Post::factory(7)->create();

        $posts->each(function ($post) use ($dev) {
            \App\Models\PostImage::factory()->create([
                'post_id' => $post->id,
            ]);

            \App\Models\PostLike::factory()->create([
                'user_id' => $dev->id,
                'post_id' => $post->id,
            ]);

            \App\Models\PostComment::factory()->create([
                'user_id' => $dev->id,
                'post_id' => $post->id,
            ]);

            $bid = \App\Models\PostBid::factory()->create([
                'user_id' => $dev->id,
                'post_id' => $post->id,
            ]);

            \App\Models\OrderHistory::factory()->create([
                'post_id' => $post->id,
                'bid_id' => $bid->id,
            ]);
        });
    }
}
