<?php
/**
 * Webhook Diagnostics Script for Enupal Stripe Payments
 *
 * This script helps diagnose webhook issues with multi-currency API keys.
 * Place this in your Craft web root and run it to check your configuration.
 */

// Include Craft bootstrap
require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

use Craft;
use enupal\stripe\Stripe as StripePlugin;

// Initialize Craft
$app = new \craft\console\Application();
$app->bootstrap();

echo "=== Enupal Stripe Webhook Diagnostics ===\n\n";

try {
    // Get plugin instance
    $plugin = StripePlugin::getInstance();
    if (!$plugin) {
        echo "❌ Plugin not found or not initialized\n";
        exit(1);
    }

    echo "✅ Plugin found: " . $plugin->getName() . "\n";

    // Get settings
    $settings = StripePlugin::$app->settings->getSettings();
    echo "✅ Settings loaded\n";

    // Check test mode
    echo "\n--- Test Mode Configuration ---\n";
    echo "Test Mode: " . ($settings->testMode ? 'Enabled' : 'Disabled') . "\n";

    // Check current currency
    $currentCurrency = StripePlugin::$app->settings->getCurrentCurrency();
    echo "Current Session Currency: " . strtoupper($currentCurrency) . "\n";

    // Check API keys
    echo "\n--- API Key Configuration ---\n";

    if ($settings->testMode) {
        echo "TEST MODE KEYS:\n";
        echo "  General Test Publishable Key: " . (!empty($settings->testPublishableKey) ? '✅ Set' : '❌ Not Set') . "\n";
        echo "  General Test Secret Key: " . (!empty($settings->testSecretKey) ? '✅ Set' : '❌ Not Set') . "\n";
        echo "  General Test Webhook Secret: " . (!empty($settings->testWebhookSigningSecret) ? '✅ Set' : '❌ Not Set') . "\n";

        echo "\n  UK Test Publishable Key: " . (!empty($settings->ukTestPublishableKey) ? '✅ Set' : '❌ Not Set') . "\n";
        echo "  UK Test Secret Key: " . (!empty($settings->ukTestSecretKey) ? '✅ Set' : '❌ Not Set') . "\n";
        echo "  UK Test Webhook Secret: " . (!empty($settings->ukTestWebhookSigningSecret) ? '✅ Set' : '❌ Not Set') . "\n";

        echo "\n  US Test Publishable Key: " . (!empty($settings->usTestPublishableKey) ? '✅ Set' : '❌ Not Set') . "\n";
        echo "  US Test Secret Key: " . (!empty($settings->usTestSecretKey) ? '✅ Set' : '❌ Not Set') . "\n";
        echo "  US Test Webhook Secret: " . (!empty($settings->usTestWebhookSigningSecret) ? '✅ Set' : '❌ Not Set') . "\n";
    } else {
        echo "LIVE MODE KEYS:\n";
        echo "  General Live Publishable Key: " . (!empty($settings->livePublishableKey) ? '✅ Set' : '❌ Not Set') . "\n";
        echo "  General Live Secret Key: " . (!empty($settings->liveSecretKey) ? '✅ Set' : '❌ Not Set') . "\n";
        echo "  General Live Webhook Secret: " . (!empty($settings->liveWebhookSigningSecret) ? '✅ Set' : '❌ Not Set') . "\n";

        echo "\n  UK Live Publishable Key: " . (!empty($settings->ukLivePublishableKey) ? '✅ Set' : '❌ Not Set') . "\n";
        echo "  UK Live Secret Key: " . (!empty($settings->ukLiveSecretKey) ? '✅ Set' : '❌ Not Set') . "\n";
        echo "  UK Live Webhook Secret: " . (!empty($settings->ukLiveWebhookSigningSecret) ? '✅ Set' : '❌ Not Set') . "\n";

        echo "\n  US Live Publishable Key: " . (!empty($settings->usLivePublishableKey) ? '✅ Set' : '❌ Not Set') . "\n";
        echo "  US Live Secret Key: " . (!empty($settings->usLiveSecretKey) ? '✅ Set' : '❌ Not Set') . "\n";
        echo "  US Live Webhook Secret: " . (!empty($settings->usLiveWebhookSigningSecret) ? '✅ Set' : '❌ Not Set') . "\n";
    }

    // Check currency-based key retrieval
    echo "\n--- Currency-Based Key Retrieval ---\n";

    $gbpPublishableKey = StripePlugin::$app->settings->getPublishableKeyByCurrency('gbp');
    $usdPublishableKey = StripePlugin::$app->settings->getPublishableKeyByCurrency('usd');

    echo "GBP Publishable Key: " . (!empty($gbpPublishableKey) ? '✅ Retrieved' : '❌ Not Found') . "\n";
    echo "USD Publishable Key: " . (!empty($usdPublishableKey) ? '✅ Retrieved' : '❌ Not Found') . "\n";

    $gbpWebhookSecret = StripePlugin::$app->settings->getWebhookSigningSecretByCurrency('gbp');
    $usdWebhookSecret = StripePlugin::$app->settings->getWebhookSigningSecretByCurrency('usd');

    echo "GBP Webhook Secret: " . (!empty($gbpWebhookSecret) ? '✅ Retrieved' : '❌ Not Found') . "\n";
    echo "USD Webhook Secret: " . (!empty($usdWebhookSecret) ? '✅ Retrieved' : '❌ Not Found') . "\n";

    // Check webhook endpoint
    echo "\n--- Webhook Endpoint ---\n";
    $webhookUrl = Craft::getAlias('@web') . '/enupal/stripe-payments';
    echo "Webhook URL: " . $webhookUrl . "\n";

    // Check if webhook endpoint is accessible
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Webhook Endpoint Response: HTTP " . $httpCode . "\n";

    if ($httpCode == 200 || $httpCode == 404) {
        echo "✅ Webhook endpoint is accessible\n";
    } else {
        echo "❌ Webhook endpoint may not be accessible\n";
    }

    // Recommendations
    echo "\n--- Recommendations ---\n";

    if ($settings->testMode) {
        if (empty($settings->usTestWebhookSigningSecret)) {
            echo "⚠️  US Test Webhook Signing Secret is not set. This may cause webhook validation failures for USD transactions.\n";
        }
        if (empty($settings->ukTestWebhookSigningSecret)) {
            echo "⚠️  UK Test Webhook Signing Secret is not set. This may cause webhook validation failures for GBP transactions.\n";
        }
    } else {
        if (empty($settings->usLiveWebhookSigningSecret)) {
            echo "⚠️  US Live Webhook Signing Secret is not set. This may cause webhook validation failures for USD transactions.\n";
        }
        if (empty($settings->ukLiveWebhookSigningSecret)) {
            echo "⚠️  UK Live Webhook Signing Secret is not set. This may cause webhook validation failures for GBP transactions.\n";
        }
    }

    echo "\n✅ Diagnostics complete!\n";

} catch (\Exception $e) {
    echo "❌ Error during diagnostics: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
