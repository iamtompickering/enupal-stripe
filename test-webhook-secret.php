<?php
/**
 * Quick Webhook Secret Test
 *
 * This script tests if your webhook signing secrets are working correctly.
 * Run this to verify your configuration before testing payments.
 */

// Include Craft bootstrap
require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

use Craft;
use enupal\stripe\Stripe as StripePlugin;

// Initialize Craft
$app = new \craft\console\Application();
$app->bootstrap();

echo "=== Webhook Secret Test ===\n\n";

try {
    $settings = StripePlugin::$app->settings->getSettings();

    echo "Current Test Mode: " . ($settings->testMode ? 'Enabled' : 'Disabled') . "\n";
    echo "Current Session Currency: " . StripePlugin::$app->settings->getCurrentCurrency() . "\n\n";

    // Test webhook secrets
    echo "--- Webhook Secret Test ---\n";

    if ($settings->testMode) {
        echo "TEST MODE:\n";
        echo "  General Test: " . (!empty($settings->testWebhookSigningSecret) ? '✅ Set' : '❌ Missing') . "\n";
        echo "  UK Test: " . (!empty($settings->ukTestWebhookSigningSecret) ? '✅ Set' : '❌ Missing') . "\n";
        echo "  US Test: " . (!empty($settings->usTestWebhookSigningSecret) ? '✅ Set' : '❌ Missing') . "\n";

        // Test currency-based retrieval
        $gbpSecret = StripePlugin::$app->settings->getWebhookSigningSecretByCurrency('gbp');
        $usdSecret = StripePlugin::$app->settings->getWebhookSigningSecretByCurrency('usd');

        echo "\nCurrency-based retrieval:\n";
        echo "  GBP: " . (!empty($gbpSecret) ? '✅ Found' : '❌ Not Found') . "\n";
        echo "  USD: " . (!empty($usdSecret) ? '✅ Found' : '❌ Not Found') . "\n";

    } else {
        echo "LIVE MODE:\n";
        echo "  General Live: " . (!empty($settings->liveWebhookSigningSecret) ? '✅ Set' : '❌ Missing') . "\n";
        echo "  UK Live: " . (!empty($settings->ukLiveWebhookSigningSecret) ? '✅ Set' : '❌ Missing') . "\n";
        echo "  US Live: " . (!empty($settings->usLiveWebhookSigningSecret) ? '✅ Set' : '❌ Missing') . "\n";

        // Test currency-based retrieval
        $gbpSecret = StripePlugin::$app->settings->getWebhookSigningSecretByCurrency('gbp');
        $usdSecret = StripePlugin::$app->settings->getWebhookSigningSecretByCurrency('usd');

        echo "\nCurrency-based retrieval:\n";
        echo "  GBP: " . (!empty($gbpSecret) ? '✅ Found' : '❌ Not Found') . "\n";
        echo "  USD: " . (!empty($usdSecret) ? '✅ Found' : '❌ Not Found') . "\n";
    }

    // Test Stripe initialization
    echo "\n--- Stripe Initialization Test ---\n";
    try {
        StripePlugin::$app->settings->initializeStripe();
        echo "✅ Stripe initialized successfully\n";

        // Get the key that was used
        $currentCurrency = StripePlugin::$app->settings->getCurrentCurrency();
        $usedKey = StripePlugin::$app->settings->getPrivateKeyByCurrency($currentCurrency);
        echo "✅ Using " . strtoupper($currentCurrency) . " private key: " . substr($usedKey, 0, 20) . "...\n";

    } catch (\Exception $e) {
        echo "❌ Stripe initialization failed: " . $e->getMessage() . "\n";
    }

    echo "\n✅ Test complete!\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
