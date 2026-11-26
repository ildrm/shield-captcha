# Protect application operations

Use Shield's server assessment API to apply limits to sensitive operations such as login, account registration, token issuance, transaction initiation, and access by registered automation. Shield supplies a policy decision. The integrating service applies it before performing the requested operation.

## Set up a policy

1. Create a sandbox site in the customer panel and register the actions to assess. A production site must pass DNS ownership verification.
2. In the site's **Credentials** action, issue a **Risk assessments and feedback** key. Store it on the integrating server.
3. Open **Protection policies** for the organization. Select the site and action, set the time window and request limits, and publish in **Observe** mode. Each publication creates a new version. Existing assessment replays retain the version and outcome they originally received.
4. Use **Protection events** to review the enforced and hypothetical decisions. Local alerts check up to 250 of the organization's latest assessments from the past hour for repeated velocity flags and abusive feedback. Verify expected behavior in sandbox, then publish **Enforce** for the intended site and action. An unconfigured action starts in Observe mode.

## Request a decision

Send `POST` with `Content-Type: application/json` and `Authorization: Bearer <sk_protect_…>` to the route that matches the operation.

| Route under `/api/v1` | Subject hash | Default decision when velocity exceeds the policy |
| --- | --- | --- |
| `/assessments` | Optional | `challenge` |
| `/accounts/assess` | Required | `step_up` |
| `/api-protection/assess` | Required | `throttle` |
| `/fraud/assess` | Required | `review` |
| `/bots/assess` | Optional | `throttle` |

For all assessment requests, send the site's `site_key`, registered `action`, `environment` (`sandbox` or `production`) and a unique `event_id`. Use a random opaque event ID of 16 to 64 ASCII letters, digits, underscores or hyphens. Retry an identical operation with its original event ID. To set `subject_hash`, compute HMAC-SHA-256 over the relevant account or integration identifier with a secret held by your service and encode the result as 64 lowercase hexadecimal characters. Keep raw identifiers, addresses and secrets out of event IDs and requests.

| Decision | Application behavior |
| --- | --- |
| `allow` | Continue the operation under your usual authorization controls. |
| `challenge` | Complete a Shield CAPTCHA and consume its token on your server. |
| `step_up` | Require stronger authentication before continuing. |
| `throttle` | Defer or reject the request for the returned `retry_after` interval when present. |
| `review` | Hold the operation for a review workflow. |
| `deny` | Stop the operation. |

In Observe mode, `decision` is `allow` and `would_decision` records what enforcement would have returned. Service limits still apply and can return HTTP 429. Handle unavailable assessments explicitly according to the sensitivity of the operation. A `risk_score` is a diagnostic value from the current rule and can be `null`.

## Register automation and submit feedback

Issue a distinct **Declared automation** key to an approved integration. The bot route recognizes that key as `registered_automation`. A `sk_protect_…` request can set `declares_automation: true` and receives `declared_automation`. The policy can require the registered class. Rate limits apply to registered automation as well.

To label a retained assessment, send `site_key`, `action`, `environment`, `assessment_id`, and `label` to `POST /api/v1/feedback` with the protection key. Accepted labels are `legitimate`, `abusive`, and `unknown`. An identical submission can be retried. Labels are available for review during the 30-day assessment retention window.

[OpenAPI contract](../openapi/v1.yaml) · [Postman collection](../openapi/shield-captcha-v1.postman_collection.json)
