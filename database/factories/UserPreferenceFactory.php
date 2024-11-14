<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class UserPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        do {
            $users = \App\Models\User::inRandomOrder()->first();
        } while (\App\Models\UserPreference::where('user_id', $users->id)->exists());
    
        return [
            'user_id' => $users->id,
            'general_notifications' => 1,
            'biding_notifications' => 1,
            'transaction_notifications' => 1,
            'activity_notifications' => 1,
            'who_can_see_connections' => 'public',
            'who_can_send_messages' => 'public',
        ];
    }
}
