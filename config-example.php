<?php
/**
 * Example configuration file for Enupal Stripe Payments plugin
 *
 * Place this in your Craft CMS config/ directory and customize as needed.
 * The plugin will automatically pick up these settings and override the database values.
 */

return [
    'stripePayments' => [
        // Test Mode (1 = test, 0 = live)
        'testMode' => 1,

        // General API Keys (fallback keys)
        'testPublishableKey' => 'pk_test_your_test_publishable_key_here',
        'testSecretKey' => 'sk_test_your_test_secret_key_here',
        'testWebhookSigningSecret' => 'whsec_your_test_webhook_secret_here',
        'testClientId' => 'ca_your_test_client_id_here',

        'livePublishableKey' => 'pk_live_your_live_publishable_key_here',
        'liveSecretKey' => 'sk_live_your_live_secret_key_here',
        'liveWebhookSigningSecret' => 'whsec_your_live_webhook_secret_here',
        'liveClientId' => 'ca_your_live_client_id_here',

        // UK/GBP API Keys (used when session currency is 'gbp')
        'ukTestPublishableKey' => 'pk_test_your_uk_test_publishable_key_here',
        'ukTestSecretKey' => 'sk_test_your_uk_test_secret_key_here',
        'ukTestWebhookSigningSecret' => 'whsec_your_uk_test_webhook_secret_here',
        'ukTestClientId' => 'ca_your_uk_test_client_id_here',

        'ukLivePublishableKey' => 'pk_live_your_uk_live_publishable_key_here',
        'ukLiveSecretKey' => 'sk_live_your_uk_live_secret_key_here',
        'ukLiveWebhookSigningSecret' => 'whsec_your_uk_live_webhook_secret_here',
        'ukLiveClientId' => 'ca_your_uk_live_client_id_here',

        // US/USD API Keys (used when session currency is 'usd' or not specified)
        'usTestPublishableKey' => 'pk_test_your_us_test_publishable_key_here',
        'usTestSecretKey' => 'sk_test_your_us_test_secret_key_here',
        'usTestWebhookSigningSecret' => 'whsec_your_us_test_webhook_secret_here',
        'usTestClientId' => 'ca_your_us_test_client_id_here',

        'usLivePublishableKey' => 'pk_live_your_us_live_publishable_key_here',
        'usLiveSecretKey' => 'sk_live_your_us_live_secret_key_here',
        'usLiveWebhookSigningSecret' => 'whsec_your_us_live_webhook_secret_here',
        'usLiveClientId' => 'ca_your_us_live_client_id_here',

        // Other settings
        'useSca' => 1,
        'capture' => 1,
    ],
];
