<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Webhook;
use App\Services\StripeService;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, StripeService $stripe_service)
    {
        $payload = $request->getContent();
        $header_signature = $request->header('Stripe-Signature');
        $event = null;

        try {
            $event = Webhook::constructEvent(
                $payload,
                $header_signature,
                config('services.stripe.webhook_secret')
            );
        } catch (UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        switch ($event->type) {
            case 'account.external_account.created':
                $stripe_service->handle_external_account_creation(
                    $event->data->object
                );
                break;
            default:
                break;
        }

        return ['status' => 'success'];
    }
}
