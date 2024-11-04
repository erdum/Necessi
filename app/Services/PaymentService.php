<?php

namespace App\Services;

use App\Models\User;

class PaymentService
{
    public function __construct(
    ) {}

    public function add_payment_method(
        User $user,
        string $payment_method_id,
        string $last_digits,
        string $expiry_month,
        string $expiry_year,
        string $card_holder_name,
        string $brand_name
    ) {}

    public function delete_payment_method(
        User $user,
        string $payment_method_id
    ) {}

    public function make_payment(
        User $user,
        string $payment_method_id,
        float $amount
    ) {}
}
