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

        \App\Models\Admin::factory()->create();

        \App\Models\User::factory(5)->create();

        $test_user_provider = \App\Models\User::factory()->create([
            'first_name' => 'Anahi',
            'last_name' => 'Smith',
            'stripe_account_id' => 'acct_1QZ8eJ2YugRvxApi',
            'stripe_customer_id' => 'cus_RS6LoLC4rF6VcK',
            'email' => 'anahi.smith@example.org',
            'avatar' => 'avatars%2Fm64z1YLnlynd1iB.webp',
            'lat' => 24.8599499,
            'long' => 67.0525505,
        ]);
        $test_user_borrower = \App\Models\User::factory()->create([
            'first_name' => 'Erdum',
            'last_name' => 'Adnan',
            'stripe_account_id' => 'acct_1QZ7lhFTymRFSFeG',
            'stripe_customer_id' => 'cus_RS2Zf0KLp7gngU',
            'email' => 'erdumadnan@gmail.com',
            'avatar' => 'avatars%2F2h5mEIeB4CTu5fR.webp',
            'lat' => 24.8599499,
            'long' => 67.0525505,
        ]);

        $card = new \App\Models\UserCard;
        $card->id = 'pm_1QZ8U9FTifuzbav1S4emzCqH';
        $card->user_id = $test_user_borrower->id;
        $card->last_digits = '0077';
        $card->expiry_month = '07';
        $card->expiry_year = '2044';
        $card->brand = 'visa';
        $card->save();

        $bank = new \App\Models\UserBank;
        $bank->id = 'ba_1QZ7oaFTymRFSFeG2g4oPVhw';
        $bank->user_id = $test_user_borrower->id;
        $bank->holder_name = 'Anahi Smith';
        $bank->last_digits = '4321';
        $bank->bank_name = 'STRIPE TEST BANK';
        $bank->routing_number = '110000000';
        $bank->save();

        $posts = \App\Models\Post::factory(7)->create([
            'user_id' => $test_user_borrower->id,
        ]);

        $posts->each(
            function ($post) use ($test_user_provider, $test_user_borrower) {
                \App\Models\PostImage::factory()->create([
                    'post_id' => $post->id,
                ]);

                \App\Models\PostLike::factory()->create([
                    'post_id' => $post->id,
                ]);

                \App\Models\PostComment::factory()->create([
                    'post_id' => $post->id,
                ]);

                $bid = \App\Models\PostBid::factory()->create([
                    'user_id' => $test_user_provider->id,
                    'post_id' => $post->id,
                    'status' => 'accepted',
                ]);

                $transaction = \App\Models\Transaction::factory()->create([
                    'user_id' => $test_user_borrower->id,
                ]);

                \App\Models\OrderHistory::factory()->create([
                    'bid_id' => $bid->id,
                    'transaction_id' => $transaction->id,
                ]);

                \App\Models\Review::factory()->create([
                    'post_id' => $post->id,
                ]);

            }
        );

        \App\Models\User::all()->each(function ($user) {
            \App\Models\UserPreference::factory()->create([
                'user_id' => $user->id,
            ]);
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

    protected function random_unique(&$elements)
    {
        if (empty($elements)) {
            return null;
        }

        return array_pop($elements);
    }
}
