<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderHistory>
 */
class OrderHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $post_ids = \App\Models\Post::pluck('id')->toArray();
        $bid_ids = \App\Models\PostBid::pluck('id')->toArray();

        return [
            'post_id' => fake()->randomElement($post_ids),
            'bid_id' => fake()->randomElement($bid_ids),
        ];
    }
}
