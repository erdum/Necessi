<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user_ids = \App\Models\User::pluck('id')->toArray();
        $post_ids = \App\Models\Post::pluck('id')->toArray();

        return [
            'user_id' => fake()->randomElement($user_ids),
            'post_id' => fake()->randomElement($post_ids),
            'data' => fake()->paragraph(),
            'rating' => fake()->numberBetween(0, 5),
        ];
    }
}
