<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    public function definition()
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'uid' => str()->random(28),
            'email' => fake()->unique()->freeEmail(),
            'phone_number' => preg_replace('/^\+1/', '', fake()->e164PhoneNumber()),
            'email_verified_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'phone_number_verified_at' => fake()->dateTimeBetween(
                '-1 month', 'now'
            ),
            'password' => Hash::make('123456'),
            'avatar' => fake()->randomElement([
                'avatars%2FAahu8MNUEMEKixp.webp',
                'avatars%2FOafaXmHjraFuVwB.webp',
                'avatars%2FRcQbUVtDds6Yzt7.webp',
                'avatars%2FUO8BFN1K7h0RYDo.webp',
                'avatars%2F2h5mEIeB4CTu5fR.webp',
            ]),
            'age' => fake()->numberBetween(18, 65),
            'about' => fake()->paragraph(),
            'lat' => fake()->latitude(),
            'long' => fake()->longitude(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'location' => fake()->address(),
        ];
    }
}
