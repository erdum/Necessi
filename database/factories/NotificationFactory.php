<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user_ids = \App\Models\User::pluck('id')->toArray();
        $user = \App\Models\User::inRandomOrder()->first();

        return [
            'user_id' => fake()->randomElement($user_ids),
            'title' => $user->first_name . ' ' . $user->last_name,
            'body' => fake()->randomElement([
                'has sent you a connection request',
                'has accepted your connection request',
                'has bid on your post',
                'has accepted your bid request',
                'has rejected your bid request',
                'has commented on your post',
            ]),
           'image' => $user->avatar,
        ];
    }
}
