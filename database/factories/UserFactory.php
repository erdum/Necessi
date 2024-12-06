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
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => preg_replace('/^\+1/', '', fake()->e164PhoneNumber()),
            'email_verified_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'phone_number_verified_at' => fake()->dateTimeBetween(
                '-1 month', 'now'
            ),
            'password' => Hash::make('123456'),
            'avatar' => fake()->randomElement([
                'avatars/0Nue4XZJ3Go7zCB.webp',
                'avatars/yTsRcDF6FUetPkD.webp',
                'avatars/tjha9ShkYsg86Qi.webp',
                'avatars/t4mjGxPmVmO2lPh.webp',
                'avatars/gz1ZD1PzDNOuIqZ.webp',
                'avatars/mOHtO1CtiLptPJU.webp',
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
