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
        $this->clear_firestore();

        \App\Models\User::factory(5)->create();
        \App\Models\User::factory()->create([
            'first_name' => 'Anahi',
            'last_name' => 'Smith',
            'email' => 'anahi.smith@example.org',
            'stripe_customer_id' => 'cus_RHvzhMFFDhmSwF',
            'stripe_account_id' => 'acct_1QNUuZRqTPUued33',
            'avatar' => 'avatars/m64z1YLnlynd1iB.webp',
            'lat' => 24.8599499,
            'long' => 67.0525505,
        ]);
        $dev = \App\Models\User::factory()->create([
            'first_name' => 'Erdum',
            'last_name' => 'Adnan',
            'email' => 'erdumadnan@gmail.com',
            'stripe_customer_id' => 'cus_RG0sykdYAzc17P',
            'avatar' => 'avatars%2F2h5mEIeB4CTu5fR.webp',
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

            // \App\Models\OrderHistory::factory()->create([
            //     'bid_id' => $bid->id,
            // ]);

            \App\Models\Review::factory()->create([
                'user_id' => $dev->id,
                'post_id' => $post->id,
            ]);

            \App\Models\UserPreference::factory()->create();
        });
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

            foreach ($users as $user) {
                $user->delete();

                $notifications = $user->collection('notifications')
                    ->listDocuments();

                $reqs = $user->collection('connection_requests')
                    ->listDocuments();

                foreach ($notifications as $notification) {
                    $notification->delete();
                }

                foreach ($reqs as $req) {
                    $req->delete();
                }
            }

            foreach ($chats as $chat) {
                $chat->delete();

                $messages = $chat->collection('messages')->listDocuments();

                foreach ($messages as $message) {
                    $message->delete();
                }
            }

            foreach ($posts as $post) {
                $post->delete();
                $bids = $post->collection('bids')->listDocuments();
                $comments = $post->collection('comments')->listDocuments();
                $likes = $post->collection('likes')->listDocuments();

                foreach ($bids as $bid) {
                    $bid->delete();
                }

                foreach ($comments as $comment) {
                    $comment->delete();
                }

                foreach ($likes as $like) {
                    $like->delete();
                }
            }
        });
    }
}
