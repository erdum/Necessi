<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostImage>
 */
class PostImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $post_ids = \App\Models\Post::pluck('id')->toArray();

        return [
            'post_id' => fake()->randomElement($post_ids),
            'url' => fake()->randomElement([
                'avatars/0Nue4XZJ3Go7zCB.webp',
                'avatars/yTsRcDF6FUetPkD.webp',
                'avatars/tjha9ShkYsg86Qi.webp',
                'avatars/t4mjGxPmVmO2lPh.webp',
                'avatars/gz1ZD1PzDNOuIqZ.webp',
                'avatars/mOHtO1CtiLptPJU.webp',
                'avatars/D7dcQp2snyJbZkA.webp',
            ]),
        ];
    }
}
