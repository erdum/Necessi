<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Kreait\Firebase\Factory;

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
            'avatar' => 'avatars/m64z1YLnlynd1iB.webp',
            'lat' => 24.8599499,
            'long' => 67.0525505,
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
                'bid_id' => $bid->id,
            ]);

            \App\Models\Review::factory()->create([
                'user_id' => $dev->id,
                'post_id' => $post->id,
            ]);

            \App\Models\UserPreference::factory()->create();
        });

        $this->clear_firestore();
    }

    protected function clear_firestore()
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $db->runTransaction(function ($trx) use ($db) {
            $users = $db->collection('users')->listDocuments();
            $chats = $db->collection('chats')->listDocuments();
            $posts = $db->collection('posts')->listDocuments();
            $notifications = $db->collection('notifications')->listDocuments();

            foreach ($users as $user) {
                $user->delete();
            }

            foreach ($chats as $chat) {
                $chat->delete();

                $messages = $post->collection('messages')->listDocuments();

                foreach ($messages as $message) {
                    $message->delete();
                }
            }

            foreach ($posts as $post) {
                $post->delete();
                $bids = $post->collection('bids')->listDocuments();

                foreach ($bids as $bid) {
                    $bid->delete();
                }
            }

            foreach ($notifications as $notification) {
                $notification->delete();
            }
        });
    }
}
