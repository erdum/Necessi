<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostBid>
 */
class PostBidFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $post_ids = \App\Models\Post::pluck('id')->toArray();
        $user_ids = \App\Models\User::pluck('id')->toArray();

        return [
            'user_id' => fake()->randomElement($user_ids),
            'post_id' => fake()->randomElement($post_ids),
            'amount' => fake()->numberBetween(1, 1000),
            'status' => fake()->randomElement([
                'accepted',
                'rejected',
                'pending',
            ]),
        ];
    }
}
