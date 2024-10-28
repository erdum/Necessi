<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Stripe\Exception\CardException;
use Stripe\StripeClient;

class StripeService
{
    protected $client;

    public function __construct()
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    public function get_customer_id(User $user)
    {
        if (! empty($user->stripe_customer_id)) {
            return $user->stripe_customer_id;
        }

        $result = $this->client->customers->search([
            'query' => "email:'{$user->email}'",
        ]);

        if (count($result->data) > 0) {
            $user->stripe_customer_id = $result->data[0]->id;
            $user->save();

            return $user->stripe_customer_id;
        }

        $stripe_customer = $this->client->customers->create([
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $user->stripe_customer_id = $stripe_customer->id;
        $user->save();

        return $stripe_customer->id;
    }

    public function add_card(
        string $payment_method_id,
        string $stripe_customer_id
    ) {
        $payment_method = $this->client->paymentMethods->attach(
            $payment_method_id,
            ['customer' => $stripe_customer_id]
        );

        return true;
    }

    public function detach_card(string $payment_method_id)
    {
        $this->client->paymentMethods->detach($payment_method_id);
    }

    public function charge_card(
        string $payment_method_id,
        string $stripe_customer_id,
        float $amount
    ) {
        try {
            $payment = $this->client->paymentIntents->create([
                'amount' => $amount * 100,
                'currency' => 'usd',
                'customer' => $stripe_customer_id,
                'payment_method' => $payment_method_id,
                'off_session' => true,
                'confirm' => true,
            ]);
        } catch (CardException $e) {
            $error = $e->getError();

            return response()->json(['error' => [
                'message' => 'Transaction failed',
                'type' => $error->code,
            ]], 500);
        } catch (Exception $e) {
            $error = $e->getMessage();

            return response()->json(['error' => [
                'message' => 'Transaction failed',
                'type' => $error,
            ]], 500);
        }

        return $payment;
    }

    public function refund_charge(string $charge_id)
    {
        $charge = $this->client->refunds->create(['charge' => $charge_id]);

        return $charge->id;
    }
}
