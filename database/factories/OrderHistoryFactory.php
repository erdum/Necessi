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
        $bid_ids = \App\Models\PostBid::pluck('id')->toArray();
        $bid = \App\Models\PostBid::find(fake()->randomElement($bid_ids));

        $transaction_ids = \App\Models\Transaction::pluck('id')->toArray();
        $random_trx_id = fake()->randomElement($transaction_ids);

        if ($bid->status == 'accepted') {
            $transaction_id = fake()->randomElement([null, $random_trx_id]);

            return [
                'bid_id' => $bid->id,
                'transaction_id' => $transaction_id,
                'received_by_borrower' => fake()->randomElement([
                    null,
                    now(),
                ]),
                'received_by_lender' => fake()->randomElement([
                    null,
                    now(),
                ]),
            ];
        }

        return null;
    }
}
