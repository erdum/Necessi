<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user_ids = \App\Models\User::pluck('id')->toArray();
        $start_date = fake()->dateTimeBetween('now', '+1 week');

        return [
            'user_id' => fake()->randomElement($user_ids),
            'type' => fake()->randomElement(['item', 'service']),
            'title' => fake()->word(),
            'description' => fake()->paragraph(),
            'location' => fake()->address(),
            'lat' => fake()->latitude(),
            'long' => fake()->longitude(),
            'budget' => fake()->numberBetween(10, 1000),
            'start_date' => $start_date,
            'end_date' => fake()->dateTimeBetween($start_date, '+1 month'),
            'delivery_requested' => fake()->boolean(),
        ];
    }
}
