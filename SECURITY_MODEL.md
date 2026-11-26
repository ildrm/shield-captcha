# Security model

## Trust boundaries

Tenant and site ownership come from the database. The customer panel checks membership and permissions. Platform operations use a dedicated login guard and require TOTP. Production site origins require HTTPS and DNS TXT ownership verification. Browser Origin is checked against the registered exact origin for public calls and CORS. The customer backend stores a separate secret for each required scope and supplies expected context from its own configuration.

The bounded checkbox proof uses a public random salt, a server-selected integer, and an SHA-256 digest. The server persists the binding, verifies the result, and issues a random 256-bit token. Only the digest of the opaque token and of each server secret is persisted in the protocol tables. The customer sees a newly issued key through a short-lived encrypted receipt bound to their identity and session, consumed once on display. Revoke credentials after a leak. A valid replacement verification key can verify an unconsumed token scoped to the same site and context.

An organization lock followed by site and protocol row locks orders changes across workers. A successful solve creates at most one token. A successful verification consumes the token and records usage in the same transaction. Return a fresh challenge after a lost result. Customer operations need their own idempotency if a consumed result or side effect can be lost in transit. Temporary suspension rejects requests while active; resuming a site can permit outstanding, unexpired work with matching context. Reconfigured context must match current site settings.

## Bounds and policy

The challenge expires after three minutes and allows three incorrect attempts. The verification token expires after two minutes. The browser limits computation to one worker and at most 12,001 candidates. The service bounds issuance, outstanding challenges, source ingress, and successful verifications. Sandbox usage uses its own context and accounting. A test site can never satisfy verification when its backend requires production.

SMS assessment uses an assessment-scope credential and a customer-generated destination HMAC. Enforce the expected site, action, environment and `decision: allow` before calling a separate SMS provider. Maintain send-side idempotency in the customer application for that flow. Direct delivery uses a distinct `sms_send` credential bound to a DNS-verified production site and the operator-selected provider. It reserves the event and selected provider before sending, and persists the outcome without a plaintext destination or code. The original receipt remains authoritative across provider changes. Disable request-body and outbound-provider tracing, since the provider receives the destination and OTP.

Checkout snapshots the gateway, payable amount, currency, term, and verification allowance in the invoice. Browser returns and signed Stripe webhooks initiate verification against the original provider. A successful settlement and its entitlement are recorded atomically. A sandbox payment cannot increase a production allowance. Payment credentials and card fields stay out of customer views and payment logs. Provider settings changes require an authenticated platform operator and are audited without credentials.

The SPA or form treats a browser success as readiness to submit. A sensitive operation needs a matching server result. Escalate service outages to independent recovery, such as a separately configured MFA or manual review. Keep verification credentials, tokens, and raw SMS destinations out of traces, access logs and frontend markup.

## Residual risks and release gates

This provider adds bounded computational cost. It has no calibrated bot or fraud detection claims and requires an independent protocol and accessibility review. Shared hosting still depends on the web host's capacity for connections, bandwidth, CPU and storage. Source limits can affect shared networks. CORS is browser enforcement and clients can copy allowed origin strings. A compromised consumer origin or backend can defeat application protections. Apply independent authentication, rate limits and abuse handling around sensitive operations.

Pre-flight controls cover core application configuration. The release still needs a host-specific HTTPS, mail, cron and restore exercise, capacity measurement, and customer integration review before commercial use.
