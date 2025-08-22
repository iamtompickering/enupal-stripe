<?php
/**
 * Stripe Payments plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2018 Enupal LLC
 */

namespace enupal\stripe\controllers;

use Craft;
use enupal\stripe\services\Checkout;
use enupal\stripe\Stripe as StripePlugin;
use Stripe\Webhook;

class WebhookController extends FrontEndController
{
    /**
     * @return \yii\web\Response
     * @throws \Throwable
     */
    public function actionStripe()
    {
        // Retrieve the request's body and parse it as JSON:
        $input = @file_get_contents('php://input');

        if (!$this->validateWebhookSignature($input)) {
            http_response_code(400);
            exit();
        }

        $isPro = StripePlugin::getInstance()->is(StripePlugin::EDITION_PRO);
        $eventJson = json_decode($input, true);

        // Enhanced logging for debugging
        Craft::info('Webhook received - Type: ' . ($eventJson['type'] ?? 'unknown'), __METHOD__);
        Craft::info('Webhook data: ' . json_encode($eventJson), __METHOD__);

        if (!isset($eventJson['type'])) {
            Craft::info('This is not a request from Stripe, skipping...', __METHOD__);
            return $this->getResponse(false);
        }

        $stripeId = $eventJson['data']['object']['id'] ?? null;
        Craft::info('Processing webhook for Stripe ID: ' . $stripeId, __METHOD__);

        $order = StripePlugin::$app->orders->getOrderByStripeId($stripeId);
        if ($order) {
            Craft::info('Found existing order: ' . $order->number, __METHOD__);
        } else {
            Craft::info('No existing order found for Stripe ID: ' . $stripeId, __METHOD__);
        }

        switch ($eventJson['type']) {
            case 'source.chargeable':
                if ($order === null){
                    break;
                }
                // iDEAL or SOFORT
                $type = $eventJson['data']['object']['type'];
                $order = StripePlugin::$app->orders->asynchronousCharge($order, $eventJson, $type);

                break;
            case 'source.failed':
                if ($order === null){
                    break;
                }
                Craft::error('Stripe Payments - Source Failed, order: '.$order->number, __METHOD__);
                break;
            case 'source.canceled':
                if ($order === null){
                    break;
                }
                Craft::error('Stripe Payments - Source Canceled,  order: '.$order->number, __METHOD__);
                break;
            case 'charge.pending':
                // Sofort may require days for the funds to be confirmed and the charge to succeed.
                // Let's update the order message
                break;
            case 'charge.succeeded':
                if ($order === null){
                    break;
                }
                // Finalize the order and trigger order complete event to send a confirmation to the customer over email.
                if (!$order->isCompleted){
                    $order->isCompleted = true;
                    StripePlugin::$app->orders->saveOrder($order);
                }
                break;
            case 'charge.failed':
                if ($order === null){
                    break;
                }
                // Finalize the order and trigger order complete event to send a confirmation to the customer over email.
                Craft::error('Stripe Payments - Charge Failed,  order: '.$order->number, __METHOD__);
                break;

            case 'charge.captured':
                if ($order === null){
                    break;
                }
                // Capture Order
                $object = $eventJson['data']['object'];
                $order = StripePlugin::$app->orders->getOrderByStripeId($object['id']);
                if (isset($object['captured']) && $object['captured'] && $order) {
                    $order->isCompleted = true;
                    StripePlugin::$app->orders->saveOrder($order, false);
                    StripePlugin::$app->messages->addMessage($order->id, 'Webhook - Payment captured', $object);

                    StripePlugin::$app->orders->triggerOrderCaptureEvent($order);
                    Craft::info('Stripe Payments - Payment Captured order: '.$order->number, __METHOD__);
                }
                break;
            // New checkout
            case 'checkout.session.completed':
                Craft::info('Processing checkout.session.completed webhook', __METHOD__);

                // Capture Order
                $checkoutSession = $eventJson['data']['object'];
                $paymentIntentId = $checkoutSession['payment_intent'];
                $order = null;

                // Log checkout session details
                Craft::info('Checkout session details - Payment Intent: ' . $paymentIntentId, __METHOD__);
                Craft::info('Checkout session currency: ' . ($checkoutSession['currency'] ?? 'not set'), __METHOD__);

                // Cart logic
                $metadata = $checkoutSession['metadata'];
                $cartNumber = $metadata[Checkout::METADATA_CART_NUMBER] ?? null;
                $checkoutSessionUrl = $metadata[Checkout::METADATA_CHECKOUT_TWIG] ?? null;

                Craft::info('Checkout metadata - Cart Number: ' . ($cartNumber ?? 'null') . ', Checkout URL: ' . ($checkoutSessionUrl ?? 'null'), __METHOD__);

                if ((!is_null($cartNumber) || !is_null($checkoutSessionUrl)) && $isPro) {
                    Craft::info('Creating cart order from checkout session', __METHOD__);
                    $order = StripePlugin::$app->paymentIntents->createCartOrder($checkoutSession);
                } else if (is_null($cartNumber) and is_null($paymentIntentId)){
                    // We have a subscription
                    Craft::info('Creating order from subscription', __METHOD__);
                    $subscriptionId = $checkoutSession['subscription'];

                    // Add retry mechanism for subscription webhooks
                    $maxRetries = 3;
                    $retryDelay = 1; // seconds
                    $subscription = null;

                    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                        Craft::info('Attempt ' . $attempt . ' to get subscription: ' . $subscriptionId, __METHOD__);

                        try {
                            $subscription = StripePlugin::$app->subscriptions->getStripeSubscription($subscriptionId);
                            if ($subscription) {
                                Craft::info('Subscription retrieved successfully on attempt ' . $attempt, __METHOD__);
                                break;
                            }
                        } catch (\Exception $e) {
                            Craft::warning('Attempt ' . $attempt . ' failed: ' . $e->getMessage(), __METHOD__);

                            // If this is a "No such subscription" error, it's likely a timing issue
                            if (strpos($e->getMessage(), 'No such subscription') !== false) {
                                Craft::info('Subscription not yet created in Stripe - this is normal for immediate webhooks', __METHOD__);
                            }
                        }

                        if ($attempt < $maxRetries) {
                            Craft::info('Waiting ' . $retryDelay . ' seconds before retry...', __METHOD__);
                            usleep($retryDelay * 1000000); // Use usleep instead of sleep for shorter delays
                        }
                    }

                    if ($subscription) {
                        $order = StripePlugin::$app->orders->getOrderByStripeId($subscriptionId);
                        if ($order !== null) {
                            Craft::warning('Checkout session was already processed under order: '.$order->number, __METHOD__);
                            break;
                        }

                        $order = StripePlugin::$app->paymentIntents->createOrderFromSubscription($subscription, $checkoutSession);
                    } else {
                        Craft::error('Failed to retrieve subscription after ' . $maxRetries . ' attempts', __METHOD__);
                        Craft::error('This may be a timing issue - subscription not yet created in Stripe', __METHOD__);

                        // Log additional information for debugging
                        Craft::info('Checkout session mode: ' . ($checkoutSession['mode'] ?? 'not set'), __METHOD__);
                        Craft::info('Checkout session status: ' . ($checkoutSession['status'] ?? 'not set'), __METHOD__);
                        Craft::info('Checkout session payment status: ' . ($checkoutSession['payment_status'] ?? 'not set'), __METHOD__);

                        // Store checkout session for later processing when subscription is available
                        // This will be handled by the subscription.created webhook
                        Craft::info('Storing checkout session for later processing when subscription is available', __METHOD__);

                        // We'll return here and let the subscription.created webhook handle the order creation
                        // This is a more robust approach than blocking the webhook
                    }
                }else{
                    Craft::info('Creating order from payment intent', __METHOD__);
                    $paymentIntent = StripePlugin::$app->paymentIntents->getPaymentIntent($paymentIntentId);

                    if ($paymentIntent){
                        $chargeId = $paymentIntent['charges']['data'][0]['id'];
                        $order = StripePlugin::$app->orders->getOrderByStripeId($chargeId);
                        if ($order !== null) {
                            Craft::warning('Checkout session was already processed under order: '.$order->number, __METHOD__);
                            break;
                        }

                        $order = StripePlugin::$app->paymentIntents->createOrderFromPaymentIntent($paymentIntent, $checkoutSession);
                    }
                }

                if ($order === null){
                    Craft::error('Something went wrong creating the Order from checkout session', __METHOD__);
                    Craft::error('Checkout session data: ' . json_encode($checkoutSession), __METHOD__);
                } else {
                    Craft::info('Order created successfully: ' . $order->number, __METHOD__);
                }
                break;
            // Products
            case 'product.created':
            case 'product.deleted':
            case 'product.updated':
                if (!$isPro) {
                    break;
                }
                $stripeObject = $eventJson['data']['object'];
                $isSyncProduct = $stripeObject['metadata']['enupal_sync'] ?? $stripeObject['metadata']['enupal-sync'] ?? false;

                if ($isSyncProduct) {
                    $product = StripePlugin::$app->products->createOrUpdateProduct($stripeObject);
                    if (!is_null($product)) {
                        StripePlugin::$app->prices->syncPricesFromProduct($product);
                    }
                }
                break;
            // Prices
            case 'price.created':
            case 'price.deleted':
            case 'price.updated':
                if (!$isPro) {
                    break;
                }
                $stripeObject = $eventJson['data']['object'];

                StripePlugin::$app->prices->createOrUpdatePrice($stripeObject);

                break;

            // Handle subscription creation events
            case 'customer.subscription.created':
                Craft::info('Processing customer.subscription.created webhook', __METHOD__);

                if (!$isPro) {
                    break;
                }

                $subscription = $eventJson['data']['object'];
                $subscriptionId = $subscription['id'];

                Craft::info('Subscription created - ID: ' . $subscriptionId, __METHOD__);

                // Check if order already exists for this subscription
                $existingOrder = StripePlugin::$app->orders->getOrderByStripeId($subscriptionId);
                if ($existingOrder) {
                    Craft::info('Order already exists for subscription: ' . $existingOrder->number, __METHOD__);
                    break;
                }

                // Try to create order from subscription
                try {
                    // We need to get the checkout session to create the order
                    // For now, we'll try to create the order directly from the subscription
                    Craft::info('Attempting to create order from subscription: ' . $subscriptionId, __METHOD__);

                    // Create a minimal checkout session structure for order creation
                    $checkoutSession = [
                        'id' => 'webhook_' . $subscriptionId,
                        'livemode' => $subscription['livemode'],
                        'shipping' => null,
                        'currency' => $subscription['currency'] ?? 'usd'
                    ];

                    $order = StripePlugin::$app->paymentIntents->createOrderFromSubscription($subscription, $checkoutSession);

                    if ($order) {
                        Craft::info('Order created successfully from subscription webhook: ' . $order->number, __METHOD__);
                    } else {
                        Craft::error('Failed to create order from subscription webhook', __METHOD__);
                    }

                } catch (\Exception $e) {
                    Craft::error('Error creating order from subscription webhook: ' . $e->getMessage(), __METHOD__);
                }

                break;
        }

        // Let's add a message to the order
        if ($order !== null){
            StripePlugin::$app->messages->addMessage($order->id, $eventJson['type'], $eventJson);
            Craft::info('Added webhook message to order: ' . $order->number, __METHOD__);
        } else {
            Craft::warning('No order to add webhook message to', __METHOD__);
        }

        StripePlugin::$app->orders->triggerWebhookEvent($eventJson, $order);

        http_response_code(200); // PHP 5.4 or greater
        Craft::info('Webhook processed successfully', __METHOD__);

        return $this->getResponse();
    }

    /**
     * @param $input
     * @return bool
     */
    private function validateWebhookSignature($input)
    {
        $settings = StripePlugin::$app->settings->getSettings();
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? null;
        $endpointSecret = null;

        // Log webhook attempt for debugging
        Craft::info('Webhook validation attempt - Test Mode: ' . ($settings->testMode ? 'Yes' : 'No'), __METHOD__);

        // Try to detect currency from webhook data if available
        $detectedCurrency = $this->detectCurrencyFromWebhook($input);
        if ($detectedCurrency) {
            Craft::info('Detected currency from webhook: ' . $detectedCurrency, __METHOD__);
            $endpointSecret = StripePlugin::$app->settings->getWebhookSigningSecretByCurrency($detectedCurrency);
        }

        // If no currency detected or no currency-specific secret, try the current session currency
        if (empty($endpointSecret)) {
            $endpointSecret = StripePlugin::$app->settings->getWebhookSigningSecretByCurrency();
            Craft::info('Using session-based webhook secret for currency: ' . StripePlugin::$app->settings->getCurrentCurrency(), __METHOD__);
        }

        Craft::info('Currency-based webhook secret found: ' . (!empty($endpointSecret) ? 'Yes' : 'No'), __METHOD__);

        // Fallback to original method if no currency-based secret is found
        if (empty($endpointSecret)) {
            if ($settings->testMode && !empty($settings->testWebhookSigningSecret)) {
                $endpointSecret = $settings->testWebhookSigningSecret;
                Craft::info('Using fallback test webhook secret', __METHOD__);
            }

            if (!$settings->testMode && !empty($settings->liveWebhookSigningSecret)) {
                $endpointSecret = $settings->liveWebhookSigningSecret;
                Craft::info('Using fallback live webhook secret', __METHOD__);
            }
        }

        if (empty($endpointSecret)) {
            Craft::warning('No webhook signing secret found - skipping validation', __METHOD__);
            return true;
        }

        try {
            $event = Webhook::constructEvent(
                $input, $sigHeader, $endpointSecret
            );
            Craft::info('Webhook signature validation successful', __METHOD__);
            return true;
        } catch(\UnexpectedValueException $e) {
            Craft::error('Invalid webhook payload: ' . $e->getMessage(), __METHOD__);
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            Craft::error('Invalid webhook signature: ' . $e->getMessage(), __METHOD__);

            // Log additional debugging info
            Craft::error('Webhook signature header: ' . ($sigHeader ?? 'null'), __METHOD__);
            Craft::error('Webhook endpoint secret length: ' . strlen($endpointSecret), __METHOD__);
            Craft::error('Webhook input length: ' . strlen($input), __METHOD__);
        }

        return false;
    }

    /**
     * Try to detect currency from webhook data
     * @param string $input
     * @return string|null
     */
    private function detectCurrencyFromWebhook($input)
    {
        try {
            $eventData = json_decode($input, true);

            if (!$eventData || !isset($eventData['data']['object'])) {
                return null;
            }

            $object = $eventData['data']['object'];

            // Check for currency in different webhook types
            if (isset($object['currency'])) {
                return strtolower($object['currency']);
            }

            // Check for currency in payment intent
            if (isset($object['payment_intent'])) {
                // We'd need to retrieve the payment intent to get currency
                // For now, return null to avoid additional API calls
                return null;
            }

            // Check for currency in checkout session
            if (isset($object['currency'])) {
                return strtolower($object['currency']);
            }

            // Check for currency in subscription
            if (isset($object['currency'])) {
                return strtolower($object['currency']);
            }

        } catch (\Exception $e) {
            Craft::error('Error detecting currency from webhook: ' . $e->getMessage(), __METHOD__);
        }

        return null;
    }

    /**
     * @param bool $status
     * @return \yii\web\Response
     */
    private function getResponse($status = true)
    {
        $return = [];
        $return['success'] = $status;
        return $this->asJson($return);
    }

}
