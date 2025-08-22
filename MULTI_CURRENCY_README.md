# Multi-Currency API Key Support

This plugin now supports switching between UK and US Stripe API keys based on a Craft session named 'currency'.

## How It Works

The plugin automatically detects the current currency from the Craft session and uses the appropriate API keys:

- **GBP/UK Keys**: Used when `craft.app.session.get('currency')` returns `'gbp'`
- **USD/US Keys**: Used when `craft.app.session.get('currency')` returns `'usd'` or when no currency is specified
- **Fallback Keys**: The original API key fields are used as fallbacks if currency-specific keys are not configured

## Setting the Currency Session

To switch between currencies, set the session value in your Craft templates or controllers:

```twig
{# Set currency to GBP #}
{% do craft.app.session.set('currency', 'gbp') %}

{# Set currency to USD #}
{% do craft.app.session.set('currency', 'usd') %}

{# Get current currency #}
{% set currentCurrency = craft.app.session.get('currency', 'usd') %}
```

Or in PHP:

```php
// Set currency to GBP
Craft::$app->getSession()->set('currency', 'gbp');

// Set currency to USD
Craft::$app->getSession()->set('currency', 'usd');

// Get current currency
$currency = Craft::$app->getSession()->get('currency', 'usd');
```

## Configuration

### Database Settings

Configure your API keys through the Craft control panel:

1. Go to **Settings** → **Plugins** → **Stripe Payments**
2. Configure the general API keys (fallback keys)
3. Configure UK-specific API keys for GBP transactions
4. Configure US-specific API keys for USD transactions

### Config File Override

You can also override these settings using a config file. Create `config/stripe-payments.php`:

```php
<?php

return [
    'stripePayments' => [
        'testMode' => 1,

        // UK/GBP API Keys
        'ukTestPublishableKey' => 'pk_test_your_uk_key',
        'ukTestSecretKey' => 'sk_test_your_uk_key',
        'ukLivePublishableKey' => 'pk_live_your_uk_key',
        'ukLiveSecretKey' => 'sk_live_your_uk_key',

        // US/USD API Keys
        'usTestPublishableKey' => 'pk_test_your_us_key',
        'usTestSecretKey' => 'sk_test_your_us_key',
        'usLivePublishableKey' => 'pk_live_your_us_key',
        'usLiveSecretKey' => 'sk_live_your_us_key',
    ],
];
```

## Template Variables

The plugin provides new template variables for accessing currency-specific API keys:

```twig
{# Get publishable key for current currency #}
{{ craft.enupalStripe.getPublishableKeyByCurrency() }}

{# Get publishable key for specific currency #}
{{ craft.enupalStripe.getPublishableKeyByCurrency('gbp') }}
{{ craft.enupalStripe.getPublishableKeyByCurrency('usd') }}

{# Get current currency #}
{{ craft.enupalStripe.getCurrentCurrency() }}
```

## API Methods

New methods are available in the Settings service:

- `getPublishableKeyByCurrency($currency = null)`
- `getPrivateKeyByCurrency($currency = null)`
- `getClientIdByCurrency($currency = null)`
- `getCurrentCurrency()`

## Use Cases

This functionality is particularly useful for:

- **Multi-region businesses** with different Stripe accounts per region
- **Marketplaces** serving customers in different currencies
- **International e-commerce** sites with region-specific payment processing
- **Compliance requirements** where different regions need separate Stripe accounts

## Migration

Existing installations will continue to work unchanged. The new currency-specific keys are optional and only used when configured. If no currency-specific keys are set, the plugin falls back to the original API key fields.

## Security Notes

- API keys are stored securely in the database
- Config file overrides are processed securely
- Session values are validated and sanitized
- All existing security measures remain in place
