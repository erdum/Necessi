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
                'avatars%2FqBlpSaEWHHat6oD.webp',
                'avatars%2FM4HxT2tH0s1uGZA.webp',
                'avatars%2FIDDLRzfuXTXPOAn.webp',
                'avatars%2Fnv9gVZwthrdcgWE.webp',
                'avatars%2Fq1vc0DjeavO0OZY.webp',
                'avatars%2FECv2R7ecEDymlh2.webp',
                'avatars%2FY74AYZBDSF7hOwT.webp',
            ]),
        ];
    }
}
