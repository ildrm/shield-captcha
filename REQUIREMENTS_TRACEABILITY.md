# Requirements traceability

The identifiers and complete acceptance conditions are preserved in the [product specification](docs/requirements/product-specification.md). Verified rows cover the listed behavior. Partial rows record implemented behavior and the remaining criteria. Pending rows await a full implementation.

| Requirement | State | Evidence or next dependency |
| --- | --- | --- |
| PROD-01, CAP-01–02, CAP-08–09 | Partial | `app/Shield/Captcha.php`, `routes/api.php`, widget assets, feature/replay tests and two-origin browser journey. Pilot SHA-256 provider needs independent assessment, theming and broader client coverage. |
| CAP-03–07 | Partial | Opt-in automatic on-submit and queue-volume adaptive modes with visible keyboard fallback. Slider, puzzle, image, and specialist accessibility options remain. |
| PROD-02, ABUSE-01 | Partial | `/api/v1/sms/assess` and `/api/v1/sms/send`, administrator-selected Kavenegar or Twilio delivery, cooldown and velocity checks, idempotency and redacted history. Reporting, campaign savings and provider delivery callbacks remain. |
| PROD-03, ABUSE-02 | Partial | Registered account actions, subject and site velocity rules, and a step-up decision. Customer workflows and extra signals remain. |
| PROD-04, ABUSE-03 | Partial | Bot classification and tenant policy results for registered application actions. Additional reputation and challenge providers remain. |
| PROD-05, ABUSE-04 | Partial | Scoped general and API assessments, fixed-window controls and tenant-isolated event history. Customer middleware adapters and advanced quotas remain. |
| PROD-06, ABUSE-05 | Partial | Subject-based transaction velocity and a review decision. Transaction context, case handling, and reports remain. |
| PROD-07, PROD-08, ABUSE-06 | Partial | Per-site automation credentials, declared/registered classification, and configurable registered-only enforcement. Crawlers, agent identity verification, and gateway policies remain. |
| PROD-09, DOC-01–02, SDK-01 | Partial | `/docs/v1`, `/playground`, JavaScript/TypeScript widget, PHP and Node.js verifier packages, PHP consumer and OpenAPI/Postman. Docs search and more framework, language, and mobile targets remain. |
| PROD-10 | Partial | Shared hosting ZIP, organization boundaries, invitation and ownership workflows, scoped policies, fixed verification entitlements, and audit. Expanded entitlements, SSO, SLA and enterprise support remain. |
| Website §3 | Partial | Localized homepage, quick start, playground, releases, security and real readiness status in `resources/views/site`. Additional products/solutions, pricing, editorial/legal pages, search and metadata remain. |
| IAM-01 | Partial | Registration, email verification, login, password flows, TOTP and recovery baseline. Central session controls, real SMTP and complete localization remain. |
| IAM-02 | Partial | Organization-based Filament access, production DNS TXT, exact site origin, membership roles, invitations and ownership transfer. Enterprise federation remains. |
| IAM-03 | Partial | Scoped verification, assessment, sending, protection and automation keys, digest storage, one-time reveal and rotation/revocation. Expiry, last-used and management scope remain. |
| RISK-01 | Partial | Tenant-editable fixed windows, deterministic rule outcomes and reason codes. Calibrated scores and flexible rule composition remain. |
| RISK-02–03 | Partial | Observe/enforce, versioned policy revisions, feedback records and publication of earlier settings as a new revision. Reputation and model integration remain. |
| API-01–02 | Partial | Widget, proof, verification, SMS, account, API, fraud, bot, feedback and health contracts in OpenAPI and Postman. Management APIs remain. |
| API-03 | Pending | Management credentials, signed webhooks and delivery diagnostics. |
| CUST-01–04 | Partial | Customer tenant/site onboarding, overview counts, exact origin/actions, DNS and key actions. Guided checklist and advanced filters/expiry remain. |
| CUST-05–06 | Partial | Site challenge-mode control and protection policy editing. Full widget designer and richer rule composition remain. |
| CUST-07–09 | Partial | Real per-tenant counts and SMS history; deeper analytics, reports, logs and configurable policy remain. |
| CUST-10–11 | Partial | Protection policy and event pages with recent tenant-local alerts. Additional product views, notification delivery and webhook history remain. |
| CUST-12 | Partial | Catalog, site actions and quick start; automatic health and migration checklist remain. |
| CUST-13 | Partial | Audit-backed invite and role lifecycle, ownership transfer and owner-only data export. Enterprise directory and branding remain. |
| CUST-14 | Partial | Customer plan and gateway choice, immutable invoice terms, quota display and invoice CSV. Invoice notifications remain. |
| CUST-15 | Pending | Expanded settings, privacy and support workflows. |
| ADMIN-01–03 | Partial | `/admin` dedicated guard, required TOTP, real counts and audit-backed organization suspension. Full operational and abuse workflows remain. |
| ADMIN-04 | Partial | Runtime provider selection and plan publication with operator MFA, password confirmation and audit. Expanded product entitlements remain. |
| ADMIN-06 | Pending | Managed challenge and risk administration. |
| ADMIN-05 | Partial | Operator-only invoice history and CLI payment reconciliation. Refund, invoice adjustment and automated settlement tooling remain. |
| ADMIN-07 | Partial | Restricted audit list and incident/retention docs; cases, privacy actions and reviews remain. |
| ADMIN-08–10 | Pending | Support, content editing and operator release workflows. |
| ADMIN-11 | Partial | CLI preflight, health, and scheduler heartbeat in the operator dashboard. External monitoring and job-specific alerts remain. |
| ADMIN-12 | Partial | Operator audit list and protective provisioning; role management/export and integrity options remain. |
| BILL-01–05 | Partial | Multiple fixed-term plans, gateway-specific prices, invoice snapshots and CSV, manual renewals, paid daily allowance, ZarinPal settlement and signed Stripe fulfillment. Automatic subscriptions, refunds and richer accounting integrations remain. |
| BILL-06 | Pending | Enterprise quote and assisted subscription workflows. |
| DATA-01–03 | Partial | Indexed InnoDB schema, digest-only verification state, bounded retention, team and audit JSON, and billing CSV. Analytics rollups and full data deletion workflows remain. |
| CMS-01 | Partial | Built WordPress plugin, real activation and six smoke-tested hooks. Full native/browser request flows, checkout and external form adapters remain. |
| CMS-02–06 | Pending | Drupal, Joomla, Magento, OpenCart and PrestaShop. |
| REL-01 | Partial | Root `downloads/`, real archives, checksums, release builders and core CI checks. Optional signing, SBOM, release CI and platform matrix remain. |
| REL-02 | Partial | Public catalog and exact manifest-bound, hashed delivery. Private entitlements, yanking and extended filters remain. |
| SEC-01 | Partial | InnoDB replay tests, strict context/scopes, middleware separation, MFA, origin controls, privacy and operator runbook. Independent review and platform hardening remain. |
| SEC-02 | Pending | Calibrated defensive evaluation, broader accessibility and capacity evidence. |
| OPS-01–05 | Partial | Bounded request and worker controls, scheduled pruning, heartbeat, readiness, and host and restore runbooks. Quantified capacity, external alerting and richer operational profiles remain. |
| DEP-01–04 | Partial | `shield:preflight`, `shield:mail-smoke`, cPanel/DirectAdmin guide, shared-host ZIP and scheduler exercised locally, and core CI definition. Live host verification, VPS profile, SMTP and deployed cron exercise remain. |
| QA-01, QA-02 | Partial | Local assertions, two-origin headless flow, package tests and private MariaDB. All pilot gates are tracked in the source specification. |
| QA-03 | Pending | Advance each feature independently with a workflow, tests and evidence. |
| QA-UNIT, QA-API, QA-REPLAY, QA-TENANT | Verified | Core targeted PHPUnit behavior and controlled concurrent solves/consumes against MariaDB. |
| QA-POLICY, QA-WIDGET, QA-FILAMENT | Partial | Fixed SMS policy, versioned protection and feedback tests, team and billing access tests, and Chromium managed and manual flows. More browser, assistive technology and product cases remain. |
| QA-BILL | Partial | Tenant boundaries, duplicate checkout and callbacks, plan snapshots, quota windows, provider failure/recovery and sandbox isolation in fixture tests. Actual merchant sandbox and live host verification remain. |
| QA-LOAD | Pending | Authorized capacity suite. |
| QA-DATA, QA-SEC | Partial | Schema, cleanup, replay, authorization and controlled download assertions. Wider hardening and suite remain. |
| QA-DOC, QA-SDK, QA-CMS | Partial | OpenAPI/Postman, real JS/PHP archive tests, WordPress/Woo activation and hook checks. Wider ecosystems and lifecycle coverage remain. |
| QA-DEPLOY, QA-A11Y | Partial | Local install and preflight, keyboard, status, RTL and expiry. Host-specific deploy and accessibility audit remain. |
