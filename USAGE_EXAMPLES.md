# Usage Examples for Multi-Currency API Keys

## Setting Currency in Templates

### Basic Currency Switching

```twig
{# Set currency to GBP for UK customers #}
{% do craft.app.session.set('currency', 'gbp') %}

{# Set currency to USD for US customers #}
{% do craft.app.session.set('currency', 'usd') %}

{# Get current currency #}
{% set currentCurrency = craft.app.session.get('currency', 'usd') %}
```

### Currency-Based Content

```twig
{# Show different content based on currency #}
{% if craft.app.session.get('currency') == 'gbp' %}
    <h2>UK Pricing (GBP)</h2>
    <p>All prices shown in British Pounds</p>
{% else %}
    <h2>US Pricing (USD)</h2>
    <p>All prices shown in US Dollars</p>
{% endif %}
```

### Dynamic API Key Usage

```twig
{# Get the appropriate publishable key for current currency #}
<script>
    var stripe = Stripe('{{ craft.enupalStripe.getPublishableKeyByCurrency() }}');
</script>

{# Or get specific currency keys #}
{% set gbpKey = craft.enupalStripe.getPublishableKeyByCurrency('gbp') %}
{% set usdKey = craft.enupalStripe.getPublishableKeyByCurrency('usd') %}
```

## Setting Currency in Controllers

### PHP Controller Example

```php
<?php

namespace modules\mymodule\controllers;

use Craft;
use craft\web\Controller;

class MyController extends Controller
{
    public function actionSetCurrency()
    {
        $currency = Craft::$app->getRequest()->getParam('currency');

        if (in_array($currency, ['gbp', 'usd'])) {
            Craft::$app->getSession()->set('currency', $currency);

            // Redirect back or return success
            return $this->redirect('back');
        }

        // Invalid currency
        return $this->asJson(['error' => 'Invalid currency']);
    }

    public function actionGetCurrentCurrency()
    {
        $currency = Craft::$app->getSession()->get('currency', 'usd');
        return $this->asJson(['currency' => $currency]);
    }
}
```

### AJAX Currency Switching

```javascript
// Switch to GBP
$.post('/actions/my-module/my/set-currency', {
    currency: 'gbp'
}, function(response) {
    if (response.success) {
        location.reload(); // Reload to apply new currency
    }
});

// Switch to USD
$.post('/actions/my-module/my/set-currency', {
    currency: 'usd'
}, function(response) {
    if (response.success) {
        location.reload(); // Reload to apply new currency
    }
});
```

## Integration with Payment Forms

### Currency-Aware Payment Form

```twig
{# Set currency before rendering payment form #}
{% do craft.app.session.set('currency', 'gbp') %}

{# Render the payment form - it will automatically use GBP keys #}
{{ craft.enupalStripe.paymentForm('my-form-handle') }}
```

### Dynamic Currency Selection

```twig
{# Create a currency selector #}
<form method="post" action="{{ url('my-module/my/set-currency') }}">
    <select name="currency" onchange="this.form.submit()">
        <option value="usd" {% if craft.app.session.get('currency') == 'usd' %}selected{% endif %}>
            USD - US Dollar
        </option>
        <option value="gbp" {% if craft.app.session.get('currency') == 'gbp' %}selected{% endif %}>
            GBP - British Pound
        </option>
    </select>
    {{ csrfInput() }}
</form>
```

## Advanced Usage

### Currency-Based Pricing

```twig
{# Get different prices based on currency #}
{% if craft.app.session.get('currency') == 'gbp' %}
    {% set price = 29.99 %}
    {% set currency = 'GBP' %}
{% else %}
    {% set price = 39.99 %}
    {% set currency = 'USD' %}
{% endif %}

<div class="price">
    {{ currency }} {{ price }}
</div>
```

### Multi-Region Checkout

```twig
{# Set up different checkout flows based on region #}
{% if craft.app.session.get('currency') == 'gbp' %}
    {# UK checkout with GBP pricing and UK-specific fields #}
    {% set checkoutUrl = '/checkout/uk' %}
    {% set taxRate = 0.20 %} {# UK VAT #}
{% else %}
    {# US checkout with USD pricing and US-specific fields #}
    {% set checkoutUrl = '/checkout/us' %}
    {% set taxRate = 0.08 %} {# US sales tax #}
{% endif %}

<a href="{{ checkoutUrl }}" class="checkout-button">
    Proceed to Checkout ({{ craft.app.session.get('currency')|upper }})
</a>
```

## Testing

### Test Mode Currency Switching

```twig
{# Test with different currencies in development #}
{% if craft.app.config.general.devMode %}
    <div class="dev-currency-switcher">
        <h4>Dev Mode: Currency Switcher</h4>
        <a href="?currency=usd">USD</a> |
        <a href="?currency=gbp">GBP</a>

        <p>Current: {{ craft.app.session.get('currency', 'usd')|upper }}</p>
    </div>
{% endif %}
```

### Debug Information

```twig
{# Show debug info in development #}
{% if craft.app.config.general.devMode %}
    <div class="debug-info">
        <h4>Debug Info</h4>
        <p>Current Currency: {{ craft.app.session.get('currency', 'usd') }}</p>
        <p>Publishable Key: {{ craft.enupalStripe.getPublishableKeyByCurrency()|slice(0, 20) }}...</p>
        <p>Test Mode: {{ craft.enupalStripe.getSettings().testMode ? 'Yes' : 'No' }}</p>
    </div>
{% endif %}
```

## Best Practices

1. **Always set a default currency** - Use 'usd' as the fallback
2. **Validate currency values** - Only accept 'gbp' or 'usd'
3. **Persist currency choice** - Consider storing in user preferences or session
4. **Handle edge cases** - What happens if no currency-specific keys are configured?
5. **Test thoroughly** - Ensure both currencies work in test and live modes
6. **Monitor webhooks** - Verify webhook signatures work with both currency setups
