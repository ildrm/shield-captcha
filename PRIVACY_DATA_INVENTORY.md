# Privacy data inventory

| Data | Purpose and retention |
| --- | --- |
| User name, email, password hash, membership, encrypted authenticator factors | Sign-in and tenant authorization. Account and audit lifecycles require an operator retention policy. |
| Invited email, role, code digest, expiry and acceptance or revocation time | Match the invitation to the verified account and prevent reuse. Codes expire after seven days and scheduled deletion runs in bounded batches. |
| Organizations, site origin, actions, public key, DNS proof, scoped credential digests | Integration and authorization. The encrypted one-time key receipt expires after two minutes and scheduler cleanup removes expired rows in bounded batches. |
| Challenge and token digests, context, expiry | Verify an issued challenge and atomically consume a token. Bounded cron deletes expired rows. API expiry enforcement is independent of cleanup. |
| Keyed request-source and velocity bucket digests | Rate controls. Buckets expire and bounded cleanup removes them after the grace period. The app observes source IP while handling a request. Configure hosting and proxy access logs separately. |
| SMS event and request digests, coarse outcome, action and time | Idempotent assessment and reporting. Bounded cleanup after seven days. The customer hashes the destination with a tenant secret before sending. |
| Protection event and request digests, decision, policy revision, reason codes and feedback label | Policy enforcement, retries and audit of outcomes. Bounded cleanup after 30 days. Customer-supplied subject hashes enter distinct keyed rate buckets. |
| SMS provider configuration and delivery metadata | Per-site Kavenegar and Twilio credentials are encrypted at rest. The selected provider receives the mobile number and OTP. Shield keeps a keyed request digest, provider, delivery state and message reference for 30 days. |
| Billing invoice amount, currency, offer snapshot, organization, transaction identifiers, result and paid term | Payment verification, receipts, allowance enforcement and reconciliation. Review retention with the operator's billing obligations. Card fields and gateway response bodies are excluded from storage. |
| Verification usage records | Per-site activity and quota evidence. Bounded cleanup after 30 days. |
| Redacted administrative audit entries | Sensitive account, site and key operations. The pilot preserves them for operator review. Set a retention policy before launch. |
| Latest scheduler heartbeat | Identify a stalled host scheduler. Each heartbeat replaces the previous timestamp. |

The assessment-only integration uses destination digests. Direct OTP delivery gives the raw destination and code to the selected provider. Payment providers collect card data on their hosted pages. Organization owners can download a bounded team and audit snapshot. Billing managers can download their organization's invoice history. Backups inherit the sensitivity of stored records. Protect backup access and define deletion and export procedures for the deployed jurisdiction before onboarding customers.
