# Troubleshooting US Webhook Issues

## 🚨 **Problem Description**
- ✅ UK/GBP transactions work correctly
- ❌ US/USD transactions: No redirect to success URL
- ❌ US/USD transactions: Pending webhook responses in Stripe dashboard

## 🔍 **Root Cause Analysis**

The issue is likely caused by **webhook signature validation failures** for the US Stripe account. When webhooks fail validation:

1. **No redirect**: The order completion logic isn't triggered
2. **Pending webhooks**: Stripe retries failed webhooks, causing the "pending" status

## 🛠️ **Immediate Fixes**

### 1. **Check US Webhook Signing Secret**

First, verify that your US webhook signing secret is correctly configured:

```php
// In your Craft control panel or config file
'usTestWebhookSigningSecret' => 'whsec_your_actual_us_webhook_secret',
'usLiveWebhookSigningSecret' => 'whsec_your_actual_us_live_webhook_secret',
```

### 2. **Verify Webhook Endpoint in Stripe Dashboard**

For your **US Stripe account**, ensure the webhook endpoint is set to:
```
https://yourdomain.com/enupal/stripe-payments
```

**Important**: The webhook endpoint URL must be exactly the same for both UK and US accounts.

### 3. **Check Webhook Events**

In your US Stripe dashboard, ensure these webhook events are enabled:
- `checkout.session.completed`
- `payment_intent.succeeded`
- `charge.succeeded`
- `charge.captured`

## 🔧 **Step-by-Step Resolution**

### Step 1: Run Diagnostics

Run the diagnostic script to check your configuration:

```bash
php webhook-diagnostics.php
```

### Step 2: Verify US Webhook Configuration

1. **Log into your US Stripe Dashboard**
2. **Go to Developers → Webhooks**
3. **Check the endpoint URL**: Should be `https://yourdomain.com/enupal/stripe-payments`
4. **Verify the signing secret**: Copy it and update your Craft settings

### Step 3: Update Craft Settings

In your Craft control panel:

1. **Go to Settings → Plugins → Stripe Payments**
2. **Ensure US API keys are set** (both test and live)
3. **Set the US webhook signing secret** from your Stripe dashboard
4. **Save settings**

### Step 4: Test with US Currency

1. **Set session currency to USD**:
   ```twig
   {% do craft.app.session.set('currency', 'usd') %}
   ```

2. **Make a test payment** using US keys

3. **Check Craft logs** for webhook processing

## 📋 **Common Issues & Solutions**

### Issue 1: "Invalid signature" errors

**Cause**: Webhook signing secret mismatch
**Solution**:
- Copy the exact webhook signing secret from Stripe dashboard
- Ensure no extra spaces or characters
- Verify you're using the correct secret for test/live mode

### Issue 2: Webhook endpoint not accessible

**Cause**: URL routing issues
**Solution**:
- Verify the webhook URL is accessible: `https://yourdomain.com/enapal/stripe-payments`
- Check Craft URL rules in `src/Stripe.php`
- Ensure no redirects or authentication blocking the endpoint

### Issue 3: Currency detection failing

**Cause**: Session currency not being set correctly
**Solution**:
- Verify session is set before payment: `craft.app.session.set('currency', 'usd')`
- Check if session is being cleared unexpectedly
- Use the diagnostic script to verify current currency

## 🧪 **Testing & Verification**

### Test 1: Manual Webhook Test

1. **Set currency to USD** in your template
2. **Make a test payment**
3. **Check Craft logs** for webhook processing
4. **Verify order completion** in Craft admin

### Test 2: Webhook Endpoint Test

Test your webhook endpoint directly:

```bash
curl -X POST https://yourdomain.com/enapal/stripe-payments \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

You should get a response (even if it's an error about invalid signature).

### Test 3: Stripe Dashboard Verification

1. **Check webhook delivery** in Stripe dashboard
2. **Look for failed webhook attempts**
3. **Verify the response codes** (should be 200 for success)

## 📊 **Monitoring & Debugging**

### Enable Detailed Logging

The enhanced webhook controller now provides detailed logging. Check your Craft logs for:

```
[info] Webhook validation attempt - Test Mode: Yes
[info] Detected currency from webhook: usd
[info] Using session-based webhook secret for currency: usd
[info] Currency-based webhook secret found: Yes
[info] Webhook signature validation successful
```

### Check for Errors

Look for these error patterns in your logs:

```
[error] Invalid webhook signature: ...
[error] Webhook signature header: ...
[error] Webhook endpoint secret length: ...
```

## 🚀 **Advanced Troubleshooting**

### If Webhooks Still Fail

1. **Temporarily disable signature validation** (for testing only):
   ```php
   // In WebhookController.php, temporarily return true
   private function validateWebhookSignature($input)
   {
       return true; // TEMPORARY - REMOVE IN PRODUCTION
   }
   ```

2. **Test webhook processing** without signature validation
3. **Re-enable validation** once webhook processing works
4. **Fix the signature validation** issue

### Check Stripe Account Differences

Ensure both UK and US Stripe accounts have:
- Same webhook endpoint URL
- Same webhook events enabled
- Compatible API versions
- Same webhook retry settings

## ✅ **Success Indicators**

You'll know the issue is resolved when:

1. **US payments redirect** to success URL
2. **Webhook responses** show as successful in Stripe dashboard
3. **Orders are created** in Craft admin for US transactions
4. **No more pending webhook** responses

## 🆘 **Still Having Issues?**

If the problem persists:

1. **Run the diagnostic script** and share the output
2. **Check Craft logs** for specific error messages
3. **Verify webhook configuration** in both Stripe dashboards
4. **Test with a simple webhook** to isolate the issue

The enhanced webhook controller should now provide much better debugging information to help identify the exact cause of the US webhook failures.
