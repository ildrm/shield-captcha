# Configure billing and SMS providers

Sign in to the operator panel with MFA and open **Provider settings** at `/admin/provider-settings`. Select any combination of **ZarinPal** and **Stripe** for payment. The organization billing page shows each selected gateway that has a valid configuration, and the customer chooses at checkout. Select **Kavenegar**, **Twilio**, or **Disabled** for SMS. The send API uses that single selection for all new deliveries. There is no customer provider parameter or automatic failover.

Configure provider credentials in the deployment configuration or through the site provisioning commands below. The operator page stores the selections, without displaying credentials. On the first upgrade, the existing ZarinPal and Kavenegar environment flags supply defaults until an operator saves settings. The saved settings take precedence afterward.

## Billing

In `/admin/billing-plans`, create a plan with its fixed term, daily verification allowance and one or both gateway prices. Use the operator password to publish or retire it. The billing page offers the configured, selected gateways with a price on that plan. Customers can also export their invoice history as CSV. Existing invoices and their fulfillment terms retain the price, currency, term and allowance quoted at checkout.

The configured default offer remains available on upgraded installs until the first catalog publication. Use the `BILLING_PLAN_*` and `BILLING_STRIPE_*` price variables below only for that legacy offer. Customers renew manually. A verified live payment grants the purchased production allowance once. Test payments leave production allowances unchanged. At expiry the base daily allowance applies again unless another paid term starts.

### ZarinPal

1. Set `BILLING_ZARINPAL_ENVIRONMENT=sandbox` and a test UUID in `BILLING_ZARINPAL_MERCHANT_ID` on staging. Set `BILLING_PLAN_AMOUNT_IRR` to the full integer price in rials. For example, 50,000 toman is 500,000 IRR. `BILLING_PLAN_TERM_DAYS` and `BILLING_PLAN_DAILY_VERIFICATIONS` apply to both gateways. [ZarinPal sandbox](https://www.zarinpal.com/docs/paymentGateway/sandBox) · [Currency units](https://www.zarinpal.com/docs/paymentGateway/moreFeatures/currency)
2. Set `APP_URL` to the deployment's HTTPS origin and refresh Laravel's config cache. Enable ZarinPal in **Provider settings**, then choose it from an organization’s **Billing** page to exercise the sandbox checkout.
3. For live activation, use the merchant code registered to the deployed domain and switch the environment to `production`. Keep the original gateway configuration available until pending invoices are reconciled. ZarinPal can return an unverified payment after its verification window. [ZarinPal gateway and verification](https://www.zarinpal.com/docs/paymentGateway/connectToGateway) · [Verification window](https://www.zarinpal.com/docs/paymentGateway/moreFeatures/session-validation)

### Stripe

1. Set `BILLING_STRIPE_SECRET_KEY` to the server key, `BILLING_STRIPE_CURRENCY` to the supported currency code, and `BILLING_STRIPE_AMOUNT_MINOR` to the integer price in that currency's minor unit. For example, 10.00 USD is 1,000. Use a Stripe test key on staging and a live key only for an eligible deployed merchant. Test/live follows the key. [Stripe Checkout](https://docs.stripe.com/api/checkout/sessions/create) · [Stripe availability](https://stripe.com/global)
2. In the matching Stripe environment, register `https://YOUR_SHIELD_DOMAIN/billing/stripe/webhook` for `checkout.session.completed` and `checkout.session.async_payment_succeeded`. Store that endpoint's signing secret in `BILLING_STRIPE_WEBHOOK_SECRET`. Refresh Laravel's config cache. The endpoint verifies Stripe's signature and checks the session against the invoice before granting access. [Stripe fulfillment](https://docs.stripe.com/checkout/fulfillment.md?ui=full-page) · [Stripe webhook signatures](https://docs.stripe.com/webhooks/signature)
3. Enable Stripe in **Provider settings** and add a Stripe price to each plan that should offer it. Follow the hosted checkout in staging and verify that a completed test payment leaves the production allowance unchanged.

### Reconcile payments

The operator invoice list shows the gateway, amount and provider reference. Deselecting a gateway removes its new checkout option. Existing invoices can still be verified using the original provider configuration. If a customer leaves before returning to Shield or an outcome remains uncertain, use the provider account and `php artisan shield:billing:reconcile INVOICE_UUID` to recheck the original invoice. Keep callback query strings and request bodies out of analytics and external logs. Use the provider account for refunds and settlement accounting.

## SMS delivery

The customer brings their own messaging account. Their backend generates and verifies the OTP, manages expiry and consent, and calls Shield using the site's dedicated sending key. A site needs provisioning for the selected provider. A missing provisioning returns an error and sends nothing. Provision alternate providers before switching if the same sites should continue to send. A request already handed off may finish during a switch.

### Kavenegar

1. Enable a Kavenegar account service level that supports VerifyLookup and approve an SMS template. Add its exact name to the comma-separated `SHIELD_SMS_KAVENEGAR_TEMPLATES` configuration. Configure SMS-only delivery in the template if desired. [Kavenegar verification templates](https://kavenegar.com/rest.html)
2. Register a DNS-verified production site and its OTP action in Shield. Run `php artisan shield:sms:kavenegar SITE_PUBLIC_KEY ACTION TEMPLATE_NAME`. Enter the provider key at the hidden prompt. To remove that site's provisioning, run `php artisan shield:sms:kavenegar SITE_PUBLIC_KEY --disable`.
3. Select Kavenegar in **Provider settings**. Kavenegar deliveries use an Iranian mobile number.

### Twilio

1. Create a Messaging Service and attach a compatible sender. Set the account's messaging destinations and use a server API key with messaging access. [Twilio Messages API](https://www.twilio.com/docs/messaging/api/message-resource) · [SMS Geo Permissions](https://www.twilio.com/docs/messaging/guides/sms-geo-permissions)
2. Run `php artisan shield:sms:twilio SITE_PUBLIC_KEY ACTION ACCOUNT_SID MESSAGING_SERVICE_SID`. Enter the API Key SID and secret at the hidden prompts. To remove the provisioning, run `php artisan shield:sms:twilio SITE_PUBLIC_KEY --disable`.
3. Select Twilio in **Provider settings**. Use E.164 destination numbers and confirm the account and sender support the intended destinations. Twilio blocks SMS to Iran, so select Kavenegar for Iranian recipients. The deployment-wide selection applies to every site. [Twilio Iran SMS guidelines](https://www.twilio.com/en-us/guidelines/ir/sms)

### Send and resolve an OTP

Keep the site's Shield sending credential in the customer backend. Call `POST /api/v1/sms/send` with the `sms_send` bearer key and `site_key`, `action`, `event_id`, `destination`, and `otp`. The OTP is a string of 4–10 digits. Use a separate event ID for each logical delivery. A repeated request with the same event returns the existing result, including across a provider switch, during the 30-day retention window. An accepted result includes the provider and message reference. Carrier delivery can be checked in the provider account.

Treat `sms_delivery_unknown` as an ambiguous outcome and reconcile with the original provider before issuing a replacement send. Keep the original event ID during recovery. Discard expired OTP requests and exclude OTPs, phone numbers, and provider credentials from request logs and tracing. Shield forwards the number and code to the selected provider and stores only keyed digests and delivery metadata. Clients using `/api/v1/sms/assess` can keep their separate assessment-only integrations.
