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

    public function get_account_id(User $user)
    {
        if (! empty($user->stripe_account_id)) {
            return $user->stripe_account_id;
        }

        $stripe_account = $this->client->accounts->create([
            'country' => 'US',
            'email' => $user->email,
            'controller' => [
                'fees' => ['payer' => 'application'],
                'losses' => ['payments' => 'application'],
                'stripe_dashboard' => ['type' => 'none'],
                'requirement_collection' => 'application',
            ],
            'capabilities' => [
                'card_payments' => [
                    'requested' => true
                ],
                'transfers' => [
                    'requested' => true
                ],
            ],
        ]);

        $this->client->accounts->update($stripe_account->id, [
            'settings' => [
                'payouts' => ['schedule' => ['interval' => 'manual']],
            ],
        ]);

        $user->stripe_account_id = $stripe_account->id;
        $user->save();

        return $stripe_account->id;
    }

    public function get_onboarding_link(User $user) {
        return $this->client->accountLinks->create([
            'account' => $this->get_account_id($user),
            'refresh_url' => config('services.stripe.onboarding.refresh_url'),
            'return_url' => config('services.stripe.onboarding.return_url'),
            'type' => 'account_onboarding',
            'collection_options' => ['fields' => 'eventually_due'],
        ]);
    }

    public function get_bank_accounts(User $user)
    {
        return $this->client->accounts->allExternalAccounts(
            $this->get_account_id($user),
            ['object' => 'bank_account']
        )['data'];
    }

    public function add_bank(User $user, string $bank_id)
    {
        $this->client->accounts->createExternalAccount(
            $this->get_account_id($user),
            ['external_account' => $bank_id]
        );

        return true;
    }

    public function detach_bank(User $user, string $bank_id)
    {
        $stripe->accounts->deleteExternalAccount(
            $this->get_account_id($user),
            $bank_id,
            []
        );

        return true;
    }

    public function get_cards(User $user)
    {
        return $stripe->accounts->allExternalAccounts(
            $this->get_account_id($user),
            ['object' => 'card']
        )['data'];
    }

    public function add_card(User $user, string $card_id) {
        $this->client->accounts->createExternalAccount(
            $this->get_account_id($user),
            ['external_account' => $card_id]
        );

        return true;
    }

    public function detach_card(User $user, string $card_id)
    {
        $this->client->accounts->deleteExternalAccount(
            $this->get_account_id($user),
            $card_id,
            []
        );

        return true;
    }

    public function get_account_balance(User $user)
    {
        $balance = $this->client->balance->retrieve([], [
            'stripe_account' => $this->get_account_id($user),
        ]);

        return $balance;
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

    public function charge_card_on_behalf(
        string $payment_method_id,
        string $stripe_customer_id,
        string $stripe_account_id,
        float $amount
    ) {
        try {
            $payment = $this->client->paymentIntents->create([
                'amount' => $amount * 100,
                'currency' => 'usd',
                'customer' => $stripe_customer_id,
                'payment_method' => $payment_method_id,
                'application_fee_amount' => (
                    ($amount * config('services.stripe.application_fee')) * 100
                ),
                'transfer_data' => [
                    'destination' => $stripe_account_id,
                ],
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

    public function payout_to_account(User $user, float $amount)
    {
        try {
            $transaction = $stripe->payouts->create(
                [
                    'amount' => $amount * 100,
                    'currency' => 'usd',
                    'source_type' => 'bank_account',
                ],
                ['stripe_account' => $this->get_account_id($user)]
            );
        } catch (Exception $e) {
            $error = $e->getMessage();

            return response()->json(['error' => [
                'message' => 'Transaction failed',
                'type' => $error,
            ]], 500);
        }

        return $transaction;
    }

    public function refund_charge(string $charge_id)
    {
        $charge = $this->client->refunds->create(['charge' => $charge_id]);

        return $charge->id;
    }
}
