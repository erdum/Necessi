<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostLike>
 */
class PostLikeFactory extends Factory
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
        ];
    }
}
