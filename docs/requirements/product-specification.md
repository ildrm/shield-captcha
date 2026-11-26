# MASTER IMPLEMENTATION PROMPT — CAPTCHA, BOT MANAGEMENT & FRAUD PREVENTION SAAS

**Execution target:** A high-capability coding agent with repository, terminal, and testing access, operating at its maximum available reasoning effort.  
**Primary stack:** Laravel 13.x, FilamentPHP 5.x, PHP 8.3+ (8.4 preferred), MySQL/MariaDB, Blade, Livewire, Alpine.js, Tailwind CSS, and framework-independent JavaScript/TypeScript.  
**Deployment constraint:** The core production application must be installable on ordinary PHP hosting managed by DirectAdmin or cPanel, without root access, mandatory persistent workers, Redis, Docker, Node.js at runtime, Python services, GPU, or external paid security APIs.  
**Deliverable:** A complete, working, tested, documented, deployable product, not a mockup, feasibility report, code sketch, or architecture-only response.

---

## 0. ROLE, AUTHORITY, AND OPERATING INSTRUCTIONS

Act as a coordinated senior engineering team. Internally apply the perspectives and standards of these roles throughout implementation:

- Principal Software Architect and Technical Lead: modular architecture, bounded contexts, dependency direction, deployment portability, decisions and trade-offs.
- Senior Laravel/PHP Backend Engineer: services, API, domain invariants, transactions, MySQL optimization, authentication, authorization, billing, events and background jobs.
- Senior FilamentPHP 5 / Livewire Engineer: accessible, fast, secure, localized, multi-tenant customer and operator panels, resources, widgets, pages, policies, and UI tests.
- Senior JavaScript/TypeScript Engineer: embeddable CAPTCHA widget, Web Components or isolated iframe, browser security, async state machine, SDKs, accessibility, performance, responsive and cross-browser behavior.
- Application Security Engineer and Defensive Bot Management Specialist: threat modeling, applied cryptography, protocol design, anti-abuse, secure tenant separation, replay resistance, secure secret handling, conservative risk decisions and incident response.
- Data/Analytics Engineer and Applied Statistician: privacy-preserving signals, explainable risk scoring, aggregation, feedback, model evaluation and calibration, metrics and retention.
- Product Manager and B2B SaaS Strategist: scope, workflows, feature entitlements, tenant experience, migration and commercial product packaging.
- UI/UX and Accessibility Specialist: design system, WCAG 2.2 AA target, Persian RTL, keyboard-first interaction, understandable errors, low-bandwidth and mobile usability.
- DevOps / SRE Engineer: DirectAdmin and cPanel deployment, resource limits, database-backed queues, monitoring, backups, reliability, upgrades, and later VPS/edge migration.
- QA Lead: unit, integration, contract, browser, package, security, race condition, load, regression and recovery tests.
- Developer Relations / Technical Writer: versioned public documentation, runnable examples in multiple languages, SDK releases, CMS extensions, downloadable packages, and integration troubleshooting.
- Privacy Engineer and Release Engineer: data minimization, data deletion, supply chain verification, dependency licenses, security advisories, reproducible artifacts and truthful release status.

This is an execution assignment. Make actual repository changes, install compatible dependencies when available, implement features, run migrations in a safe test database, build assets, run tests and produce verified artifacts. Do not just tell the user how to implement the system. Do not present a theoretical blueprint as a completed implementation. Use concise progress notes, but prioritize working software.

### 0.1 Workspace rules

1. Inspect the existing repository first: working directory, Git status, README, composer.json, package.json, lockfiles, routes, application structure, database configuration, CI, existing tests and any repository instructions. Preserve existing user changes. Do not reset, delete, force-push, overwrite unrelated files, or replace an existing project without authorization. If the repository is empty, initialize a new application. If the repository is an existing Laravel app, integrate incrementally.
2. Inspect actual installed PHP, Composer, Node/npm (build-time only), database and extensions. Use official documentation and real package metadata to resolve compatibility; never guess API signatures or framework methods. Do not install untrusted packages or execute unreviewed third-party scripts. Do not require root or modify system services without explicit authorization.
3. Never access, test, scan, or attack live third-party sites, networks, accounts, or CAPTCHA services. Use local fixtures, owned staging environments, and explicitly authorized test systems. Do not copy competitor code, images, names, or branding. Public competitor documents are product references only.
4. Never read, print, commit, expose, or transmit actual secrets, production customer data, credentials, private keys, cookies, or payment information. Use environment variables, `.env.example`, local test keys, synthetic fixtures and safe configuration. Never run destructive migrations, seed demo data, or performance/security tests against production. Do not send real SMS, payments or external emails during tests.
5. Make reasonable, documented defaults and proceed without asking the user routine design questions. Use configurable placeholders for brand, domain, jurisdiction, payment credentials, contact details and legal policies. Ask only if a truly irreversible or credential-dependent decision requires explicit input. If a tool or credential is missing, deliver what is executable, mark the blocked portion clearly, and document exact resolution steps.
6. Work in small, testable vertical slices. Maintain `IMPLEMENTATION_STATUS.md`, `REQUIREMENTS_TRACEABILITY.md`, `DECISIONS.md`, `SECURITY_MODEL.md`, and `DEPLOYMENT.md`. Keep requirement IDs mapped to files, routes, migrations, tests and release artifacts. Mark statuses `verified`, `implemented-unverified`, `partial`, `blocked`, or `not-started`; never mark untested work as verified.
7. When a context, runtime or tool limit prevents completion, persist a specific checkpoint: completed scope, current failing commands, remaining acceptance criteria, changed files, and next execution steps. Do not silently omit requirements, pretend placeholders are implementations, or merely output a plan at the end. Never claim continued background execution.
8. Use the repository as the source of truth. Keep application code, docs, OpenAPI schemas, example projects, tests, package sources and actual downloadable outputs synchronized. Avoid empty buttons, fake dashboards, dead navigation, arbitrary mock production data, unimplemented controller stubs, hard-coded demo credentials, hidden premium bypasses, and misleading security claims. Synthetic data is allowed only in explicit local/test/demo contexts, clearly labeled and isolated.

### 0.2 Reference documentation (verify the currently selected versions)

- Filament 5 installation: https://filamentphp.com/docs/5.x/introduction/installation
- Filament 5 overview: https://filamentphp.com/docs/5.x/introduction/overview
- Filament 5 multi-tenancy: https://filamentphp.com/docs/5.x/users/tenancy
- Filament documentation index: https://filamentphp.com/docs/llms.txt
- Laravel: https://laravel.com/docs/13.x
- Laravel deployment: https://laravel.com/docs/13.x/deployment
- ALTCHA: https://altcha.org/docs/ and https://github.com/altcha-org/altcha
- ALTCHA PHP library: https://github.com/altcha-org/altcha-lib-php
- OWASP ASVS: https://owasp.org/www-project-application-security-verification-standard/
- OWASP API Security: https://owasp.org/www-project-api-security/
- OWASP Automated Threats: https://owasp.org/www-project-automated-threats-to-web-applications/
- OWASP Cheat Sheet Series: https://cheatsheetseries.owasp.org/
- WCAG 2.2: https://www.w3.org/TR/WCAG22/
- OpenAPI: https://spec.openapis.org/oas/latest.html
- Public product references: https://arcaptcha.co/ ; https://developers.cloudflare.com/turnstile/ ; https://docs.hcaptcha.com/ ; https://cloud.google.com/security/products/recaptcha ; https://friendlycaptcha.com/ ; https://www.geetest.com/ ; https://prosopo.io/ . Do not assume competitor internal algorithms, capabilities, prices or performance.

Use source URLs and versions in `DECISIONS.md`. Filament 5 officially supports multiple panels in one Laravel application and specifies PHP 8.2+, Laravel 11.28+ and Tailwind CSS 4.1+ as its baseline; Laravel 13 raises the PHP minimum to 8.3. Use a real Composer resolution to confirm Laravel 13 + Filament 5 + Livewire/Tailwind and every optional dependency. Do not silently downgrade Filament, use v3/v4 APIs, or ignore platform requirements. If there is an actual incompatible lockfile or hosting limitation, explain it and choose the highest compatible supported combination with a documented decision; retain Filament 5 unless explicitly authorized otherwise. Use `composer require filament/filament:"^5.0"` and `php artisan filament:install --panels` only in an appropriate workspace, registering providers correctly. Never use destructive scaffold commands on an existing application.

---

## 1. PRODUCT VISION AND SCOPE

Build an independently operated, multi-tenant **CAPTCHA-as-a-Service, Bot Management and Fraud Prevention SaaS** that can compete with regional services such as ARCaptcha and adopt the best public product patterns of global vendors. The product is not a wrapper around Google reCAPTCHA, Cloudflare Turnstile, or hCaptcha. Its core challenge and verification system must continue functioning without them or an Internet-based AI inference service.

Use a neutral configurable working name, `Shield CAPTCHA` (internal package/namespace placeholders must be replaceable). Do not assume trademark availability; centralize brand name, logo, colors, favicon, support email, legal name, URLs and locales. Never copy competitor branding or claim superiority before benchmark evidence exists.

### 1.1 Personas and jobs to be done

- Developer: create a site, obtain keys, install a widget/SDK, validate on the backend, test a real request, troubleshoot and migrate without friction.
- Website owner: protect registration, login, contact forms, comments, password recovery, OTP, checkout and other abuse-prone operations; understand savings and impact; manage billing.
- Security operator: inspect risk, configure rules, tune policies in observe mode, review false positives, manage keys, alerts, evidence, and incident response.
- Organization owner: invite teammates, delegate roles, control retention, review invoices, configure multiple websites/apps, and request enterprise services.
- Platform operator: manage tenants, plans, limits, global configuration, system health, suspicious traffic, support, downloads, content and operational controls without exposing customer secrets.
- End user: complete forms with minimal friction, on mobile, with poor connectivity, in their language, with keyboard or assistive technology, and with clear recovery from errors.

### 1.2 Required product modules

All modules below belong in the product backlog and traceability matrix. Implement real shared-hosting-compatible functionality where possible; infrastructure-dependent capabilities must have a real documented optional profile or be explicitly blocked, never faked. A single common engine should support these product experiences rather than duplicating systems.

| ID | Product | Required functional scope | Deployment baseline |
|---|---|---|---|
| PROD-01 | Smart CAPTCHA | Checkbox, interactive/puzzle/image, low-friction invisible and adaptive challenge, privacy-conscious proof of work, accessible alternatives, branding, language and risk policies | Shared |
| PROD-02 | SMS Shield | Server-side pre-send assessment, per-tenant and per-destination rate controls, OTP and resend abuse protection, feedback, estimated savings | Shared |
| PROD-03 | Account Shield | Login and registration risk, credential-stuffing and account-abuse indicators, step-up recommendations, tenant feedback | Shared |
| PROD-04 | Bot Shield | Browser/client/server assessment, traffic classification, tenant policies, coarse threat reasons, managed rules, trusted automation | Shared application integration; full traffic coverage requires gateway |
| PROD-05 | API Shield | Server-to-server assessment, endpoint-aware quotas, rate limits, integration middleware, risk verdicts, machine-client authentication guidance | Shared application integration; edge enforcement optional |
| PROD-06 | Fraud Shield | Rules for signup, coupon/promo, checkout and suspicious transaction patterns, velocity controls, feedback and reporting; no payment-card collection | Shared rule-based baseline; enhanced analytics optional |
| PROD-07 | Scraper Shield | Tenant-side middleware and request-risk policies protecting explicitly integrated content endpoints; authorized crawler allowlists | Shared application integration; continuous full-site traffic protection requires proxy |
| PROD-08 | AI Agent / Verified Bot Management | Distinguish verified permitted automation, declared clients, unknown and suspicious automated traffic; tenant rules and transparent limitations | Shared baseline; enhanced intelligence optional |
| PROD-09 | Developer Platform | Versioned API, widget, sandbox, integration health checks, SDKs, CMS plugins, migration, documentation, downloads and release catalog | Shared runtime; builds off-host |
| PROD-10 | Enterprise | Team RBAC, audit, custom rules/risk configuration, retention, branding/white-label entitlements, self-hosted package, optional SSO, support and SLA workflow | Shared baseline; SSO and HA/edge depend on profile/configuration |

Do not promise that CAPTCHA can prove a human identity, stop all fraud, replace MFA, block network-level DDoS, or protect pages/APIs that have not been integrated. Explain precisely which customer-side action is necessary for each service. No claims of certification, GDPR compliance, WCAG conformance, guaranteed uptime, detection percentage, revenue/savings or beating competitors without appropriate independent evidence.

### 1.3 Delivery tiers and scope governance

Deliver a complete vertical slice early, then extend it. P0, P1, P2 and P3 are implementation order, not permission to ignore later items. P0 must be functional and tested before enabling high-risk features. Keep a clear feature readiness manifest. Advanced capabilities are not considered complete merely because a database column, menu item, toggle or interface exists.

- **P0 — Core:** installable application, public pages, two Filament panels, auth/organizations/sites/keys, secure proof-of-work/checkbox widget, challenge protocol, one-time server verification, rules/initial risk, rate limits, SMS baseline, quota accounting, dashboards, test keys, docs, WordPress/WooCommerce and PHP/JS integration, deploy/test/release infrastructure.
- **P1 — Competitive:** adaptive/invisible modes, interactive puzzle and accessible alternatives, account/API/anti-spam integrations, richer rules, shadow mode, reporting, alerts, integration health, billing, localization, additional SDKs and CMS releases.
- **P2 — Advanced:** fraud, scraper and bot/AI agent controls, signed feedback, custom risk configurations, enterprise teams, reports, branding, self-hosted package, support and migration tooling; optional reputation providers, privacy controls, enterprise auth adapter.
- **P3 — Scale:** optional edge gateway/proxy, Redis, scalable analytics and data retention stores, permanent workers, load balancer, multi-region and SSO provider integration, calibrated ML risk adapter and stronger enterprise availability. Must not be a prerequisite for the core shared-host profile. Provide working optional profiles where possible, explicit prerequisites and verified status. Do not claim full P3 operation on a shared host.

---

## 2. ARCHITECTURE, COMPATIBILITY AND HOSTING PROFILES

### 2.1 Architectural style

Implement a modular monolith with domain-oriented modules, clear application service boundaries, dependency injection and narrow interfaces. Keep controllers, Livewire components and Filament actions thin. Enforce business invariants in domain/application services, not only in UI visibility or browser validation. Use Laravel conventions and native facilities where sufficient; avoid unnecessary layers or fashionable dependencies.

Suggested bounded contexts (adapt to an existing repo with an ADR):

- Identity and Tenancy; Sites and Credentials; Challenge; Verification; Risk and Policy; Abuse Controls; SMS; Accounts; API Protection; Fraud; Bot and Agent Management; Usage and Billing; Analytics; Alerts and Webhooks; Integrations and Downloads; Content and Documentation; Support; Administration; Operational Health; Privacy and Audit.

Use an explicit tenant context for all tenant-owned operations (HTTP, Filament, queued jobs, exports, cache keys, commands, metrics). Central global entities are separate and privileged. Prefer a shared MySQL schema with a tenant ID and indexed foreign keys rather than one database per tenant for the baseline. Filament tenancy is a convenience, not a complete authorization boundary. All direct queries, relations, actions, global search, route binding, files, and background jobs must enforce tenant scoping independently.

Suggested interfaces: `ChallengeProvider`, `ChallengeStore`, `TokenStore`, `RiskSignalProvider`, `RiskEngine`, `PolicyEvaluator`, `RateLimitStore`, `CredentialRepository`, `UsageMeter`, `PaymentGateway`, `NotificationChannel`, `ReputationProvider`, `BotIdentityVerifier`, `ArtifactRepository`, `MetricsSink`, `TenantContext`, `Clock`, `IdGenerator`. Implement real MySQL/shared-host adapters first. Additional adapters use the same contracts and integration tests. Use dependency injection, typed DTOs, explicit errors and small service classes.

### 2.2 Shared-hosting profile (mandatory and default)

- PHP 8.3+ and preferably PHP 8.4, Composer 2, supported MySQL/MariaDB with InnoDB and transactions, Apache/LiteSpeed/Nginx as provided by host. Confirm exact database and PHP versions rather than assuming all hosting plans support them.
- Required PHP extensions per Laravel and selected packages: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, pdo, pdo_mysql, session, tokenizer, xml; intl/zip/gd/sodium when the chosen features require them. Verify supported crypto/encoding and image codecs. Use OPcache if available. Avoid mandatory `exec`, shell access during web requests, FFI, background daemons, or uncommon server extensions.
- Build assets locally or in CI (Node/npm, Vite, Tailwind). Deploy compiled assets plus required PHP vendor files. Production must not need Node.js, npm, Docker, Python, Java, npm dev server or a live Vite service. Provide an upload-ready artifact for hosting without SSH where practical. Build SDK and CMS outputs off-host.
- Database-backed sessions, cache and queues where needed, with tenant-prefixed keys. Use MySQL atomic operations for one-use tokens, throttling, quotas, leases and unique jobs. Never rely on local file cache for correctness across PHP workers. Provide synchronous request-critical validation and deterministic cleanup. Optional APCu/OPcache for safe non-authoritative caches only.
- No persistent workers required: bounded cron-driven scheduler/queue processing; use `schedule:run` as supported, non-overlapping jobs with durable locks and short max-time/stop-when-empty workers, bounded batches and retry/backoff. Document minimum cron interval, PHP CLI path, memory/time constraints and behavior if cron is delayed. Public request decisions cannot depend on asynchronous processing. Do not place heavy analytics work in verification hot path.
- Shared host must support document roots pointing to Laravel `public/` or a safe split public/private deployment; never expose application root, `.env`, `.git`, `vendor`, storage, source packages, backups, or internal `downloads/` directly. If safe isolation cannot be achieved, mark the hosting plan unsupported.
- Require HTTPS for production and secure cookie settings. Support subdomains for API, panel, asset delivery and docs where available, but allow a single canonical origin with path prefixes so wildcard DNS or multiple virtual hosts are not mandatory. Use explicit trusted hosts/proxies and URL generation. Do not trust arbitrary forwarding headers.
- No mandatory external paid service. Provide configurable SMTP, transactional mail, webhook, payment provider, CDN and monitoring. If disabled, expose correct readiness/feature status rather than pretend delivery. Self-host local widget/assets and docs. Verify operation without third-party fonts, analytics or CDN dependencies. Protect tenant-sensitive dynamic routes from caching.
- Start with a test planning envelope of approximately 1 CPU/1 GB RAM/5 GB SSD for development or small pilot, and 2–4 CPU/2–4 GB/10–20 GB for a more capable pilot if provider resource limits permit. These are planning assumptions, not performance guarantees. Track PHP worker limits, entry processes, database connections, disk I/O, CPU, memory, request bursts, DB growth, inode caps, provider ToS and outbound HTTP restrictions. Benchmark actual hosting before accepting paying high-traffic tenants.

### 2.3 Optional profiles (not mandatory to install core)

| Profile | Additions | Why |
|---|---|---|
| `shared` (default) | PHP, MySQL, cron, local assets | Lowest-cost pilot and small deployments |
| `vps` | Redis cache/rate limits/queues, permanent worker/supervisor, dedicated DB optional, metrics | Lower latency and higher throughput |
| `edge` | Customer-authorized reverse proxy/API gateway or CDN/WAF integration with signed service auth, upstream enforcement, network controls | Site-wide traffic coverage; not just widget-protected forms |
| `enterprise` | Dedicated installation, external secret manager, SSO provider, isolated data, centralized logs, replicas/failover, optional ML/reputation | Organizational requirements and scale |

For optional profiles implement contract-tested adapters and deployment recipes/configuration when feasible, fail closed on misconfiguration, and report exactly which profile has been executed. Do not expose a live-looking edge or ML control when no functioning adapter is configured. No root-dependent tasks in the shared installer. Provide portable schema and migration path from MySQL-backed rate counters/queues to Redis and from one host to dedicated infrastructure; avoid premature microservices or complex service orchestration.

### 2.4 Technical stack and quality

- Laravel 13 supported patch release with locked dependencies; Filament `^5.0`, compatible Livewire/Alpine/Tailwind 4.1+; use Filament panel builder for `/admin` and `/panel` and Blade for public site/docs; public challenge widget must not require Filament or Livewire assets.
- Modern PHP strict typing in domain code, PSR-4, Laravel Pint, PHPStan/Larastan if compatible, Pest or PHPUnit. TypeScript strict mode for widget/SDK, ESLint and reproducible Vite build. Use Laravel validation, policies, migrations, Eloquent/query builder and transactions; parameterize all SQL.
- REST JSON API version `/api/v1` and public widget assets with separately versioned stable protocol. OpenAPI 3.1 or another actually supported OAS version with validated schemas. UTC server times, RFC 3339 timestamps, local presentation only. Use locale-aware formatting, ISO currency codes, integer minor units, documented units and precision. Do not store floats for money.
- Maintain `composer.lock` and JS lockfile; audit dependencies; generate license inventory and SBOM; pin versions for reproducible builds. No unlicensed media, paid Filament plugin requirement, proprietary dataset requirement or model/API dependency in the mandatory profile.

### 2.5 Repository layout (adapt while preserving these deliverables)

```text
app/
  Domain/                    # bounded contexts, policies, DTOs, entities/services
  Application/               # use cases, ports, orchestration
  Infrastructure/            # persistence, provider adapters, external integration
  Http/Controllers/Api/V1/
  Http/Middleware/
  Filament/Admin/            # operator resources, pages, widgets
  Filament/Customer/         # tenant resources, pages, widgets
  Providers/Filament/        # independent panel providers
  Console/Commands/
  Jobs/
  Policies/
config/                      # brand, captcha, risk, plans, deployment, features, docs
routes/                      # web, API, console; explicit versioning
resources/
  views/                     # public marketing, docs, demos, status, auth
  js/widget/                 # standalone widget source, worker, localization
  js/                        # site-specific assets, not part of widget runtime
  css/
  lang/                      # or Laravel-supported lang/ path; en, fa
  docs/                      # versioned Markdown/structured documentation sources
public/                      # only web-facing assets; no app source or private files
packages/
  sdk/                       # package source, tests, release scripts
  cms/                       # installable CMS extension source and tests
  examples/                  # runnable example apps, never real credentials
  gateway/                   # optional edge integration source/configuration
  self-hosted/               # edition installer/upgrade tooling and docs
  shared-contracts/          # API schemas, fixtures and compatibility vectors
openapi/                     # canonical versioned specification
scripts/                     # build/release/checksum/package/preflight scripts
infra/                       # shared-host, VPS, edge profiles; CI recipes
storage/                     # private runtime files; never publish logs/backups
downloads/                  # REQUIRED ROOT DIRECTORY: generated release artifacts only
  manifest.json
  sdk/
  cms/
  examples/
  self-hosted/
  checksums/
tests/                       # unit, feature, security, contract, load, E2E
.github/workflows/           # or equivalent CI
README.md
IMPLEMENTATION_STATUS.md
REQUIREMENTS_TRACEABILITY.md
SECURITY_MODEL.md
DECISIONS.md
DEPLOYMENT.md
SECURITY.md
THIRD_PARTY_NOTICES.md
CHANGELOG.md
```

The `downloads/` directory must exist at repository root (no leading space in the actual path). It is a generated distribution repository, separate from package sources and not the runtime application document root. See Section 13 for exact release and publication rules. Avoid public symlinks to arbitrary paths. Keep reproducible sources in Git; choose a documented Git policy for generated binaries and large release assets, but retain the required output folder in the delivered workspace.

---

## 3. PUBLIC WEBSITE, BRAND AND UX

Build a coherent production-quality responsive website; use original design, configurable branding, accessible contrast, typography, whitespace, consistent component styles and full right-to-left layout. Public site and both panels must support English (`en`, LTR) and Persian (`fa`, RTL), persist locale selection, and allow additional locales without changing application logic. Store all timestamps in UTC and use an optional Jalali display formatter for Persian; use explicit Gregorian/ISO dates in API and exports. All UI strings, widget messages, errors and documentation navigation need translations or a clearly documented fallback. Avoid loading external fonts at runtime; use licensed self-hosted fonts or robust system fonts. Do not hard-code translations in PHP business logic.

### 3.1 Public routes/pages

Create actual routes with navigation and meaningful content (adjust paths consistently):

- `/`, `/products`, `/products/smart-captcha`, `/products/sms-shield`, `/products/account-shield`, `/products/bot-shield`, `/products/api-shield`, `/products/fraud-shield`, `/products/scraper-shield`, `/products/ai-agent-management`, `/enterprise`, `/self-hosted`.
- `/solutions/ecommerce`, `/solutions/fintech`, `/solutions/marketplaces`, `/solutions/saas`, `/solutions/wordpress`, `/solutions/media`, `/solutions/organizations`; explain requirements and honest limitations for each.
- `/pricing`, plan comparison, configurable quotas, currency and billing units, fair usage/overage/grace rules, feature status, contact sales; no hard-coded fake pricing promises.
- `/demo` and `/playground`: functional real test-mode widget/challenge demonstrations with test credentials, demo reset, adaptive examples, multiple modes, frontend and backend sample integration, simulated errors and test results. Demo traffic never grants production access, sends messages, creates charges or exposes secrets; rate-limit demo endpoints.
- `/developers`, `/docs`, `/docs/{version}/...`, `/api-reference`, `/downloads`, `/downloads/{artifact}`, `/changelog`, `/migration`, `/security`, `/responsible-disclosure`, `/privacy`, `/terms`, `/acceptable-use`, `/accessibility`, `/status`, `/help`, `/contact`, `/blog`, `/blog/{slug}`, `/case-studies` (only real approved stories or explanatory empty state), `/login`, `/register`, password recovery, email verification. Implement legal templates clearly labeled for operator/legal review before launch; no invented certifications or customer logos.
- Include searchable documentation, article hierarchy, breadcrumbs, sticky version/language navigation, copy code controls, integration guides and links to actual artifact versions. Site search must use indexed queries or bounded static indexing, not a separate search service in shared mode.
- Localized SEO metadata/canonical URLs, hreflang where translations exist, sitemap, robots.txt, OpenGraph, structured data where appropriate, pagination, 404/403/429/503 error views, helpful empty/loading/error states. Noindex operator/customer panels and test environments, not a substitute for authentication.
- A status page must use actual health/monitoring data or say unavailable; no invented uptime. Offer an external status-page configuration to survive an application outage. Add transparent incident history, maintenance notices and subscription preference when configured.

### 3.2 Design and interaction acceptance

- Mobile, tablet, desktop; light/dark theme for panels and widget; no layout breakage in Persian; keyboard focus, visible labels, screen-reader status announcements, reduced motion, high contrast and accessible alternatives for interactive challenges.
- Working CTA, registration, sign-in, navigation, filters, validation, confirmation dialogs, copy snippets, downloads, pagination and export actions. No dead-end feature cards. Do not render unavailable services as enabled; label experimental, configuration-required, disabled or forthcoming honestly with explanatory links.
- Avoid layout shift, oversized JS bundles and synchronous external assets. Use meaningful empty states, error recovery and retry only when safe. Do not require JavaScript for reading documentation, pricing or legal pages. Use safe Markdown rendering and sanitization for articles/docs; no untrusted arbitrary scripts/HTML.

---
## 4. IDENTITY, TENANCY, CREDENTIALS AND ACCESS CONTROL

### 4.1 Identity and authentication (IAM-01)

Implement Laravel-supported authentication flows: registration, email verification, login, logout, password reset, password change, session management/revocation, account settings, locale, timezone, login throttling, and secure recovery. Use secure password hashing and framework-standard protections. Require email verification before production key creation. Offer MFA (TOTP or other supported secure method with single-use recovery codes) for organization owners and require it for platform operators; protect enrollment/disable/recovery with re-authentication, audit and rate limits. Do not implement ad hoc cryptography or send MFA recovery secrets by email. Ensure CSRF protection for browser sessions, cookie flags, session regeneration on authentication and privilege changes, and secure error messages that avoid account enumeration.

The application's own auth routes should use defense-in-depth: native throttles and abuse controls remain independent of its CAPTCHA service to avoid dependency loops. Apply conservative outage handling. Do not require a working third-party CAPTCHA to bootstrap the first administrator.

### 4.2 Organizations, sites and roles (IAM-02)

- Support individual customers through a personal organization plus multiple organizations/team memberships, invitations with expiry and single use, member removal, ownership transfer, organization suspension and deletion. Provide a safe path for the last owner and tenant recovery. Verify invited email and membership on acceptance.
- Customer roles: `owner`, `admin`, `developer`, `security_analyst`, `billing_manager`, `viewer`; implement a documented least-privilege permission matrix (sites/keys/rules/logs/billing/team/exports/support/privacy) using Laravel policies, explicit checks and tenant context. Owners alone can grant owner status and transfer ownership. Tenant admins cannot escalate to platform operator.
- Operator roles: `super_admin`, `security_operator`, `support_agent`, `billing_operator`, `content_manager`, `release_manager`, `read_only_auditor`. Separate platform-role assignment from tenant membership; protect operator authentication and panel access. Support staff must not silently impersonate customer accounts. If an audited assisted-access flow is implemented, require explicit permission, reason, limited duration, visible banner, and deny secret access or destructive financial actions.
- Sites/apps: create, name, categorize, environment (production/test), configure domains, allowed actions, widget mode/appearance, locale, security policy, integration status, quotas, pause, archive, revoke credentials, delete according to retention. Each site belongs to exactly one tenant; no cross-tenant access through IDs, exports, jobs, or support links.
- Domain control: normalize hostname safely (IDNA/punycode, lower case, port/trailing dot handling); reject invalid hosts, public suffix wildcards, bare `*`, unsafe wildcard rules and misleading lookalikes. Support exact host and explicitly scoped subdomains. Verify domain ownership via DNS TXT or a safe well-known HTTP challenge where appropriate; verification requests must use an allowlisted protocol, block private/loopback/link-local and metadata ranges, handle DNS rebinding/redirects, set strict timeouts and avoid arbitrary outbound URL requests. Never treat an untrusted browser header as proof of ownership. Allow explicit localhost/test-domain usage only for test credentials. App/mobile integrations use registered app identifiers and optional platform attestation when configured; do not claim app attestation from a client-supplied string.

### 4.3 Keys and secret management (IAM-03)

- Generate high-entropy random public site keys, server-only verification secrets and scoped management API keys with clear prefixes and distinguishable test/production environments. Use a secure CSPRNG and versioned key identifiers. Never use predictable IDs or human-readable passwords as verification secrets.
- Store verification/management credential hashes or keyed digests with an explicit cryptographic design appropriate to lookup and verification; if a secret must be recovered for signing, use authenticated encryption and restricted access. Store platform signing/encryption material outside the web root in secure configuration; support purpose-specific secret separation, key IDs and rotation. Never rely on a public site key for privileged authorization.
- Show newly created private keys once; require re-authentication for sensitive operations; display only key prefix, creation, expiration, scope, last-used, status, and rotation controls afterward. Never include raw secrets in HTML, logs, browser bundles, exceptions, analytics, URLs, email, support tickets, CSV or docs examples.
- Implement revoke, disable, rotation with a bounded overlap/grace period when safe, explicit expiry and last-use timestamps. Revoked credentials must fail verification and cannot sign new requests; define how outstanding challenges/tokens are affected. Rotation must be tenant-scoped, audited, race-safe and testable. Separate API credentials for verification, server risk assessment, feedback, and administrative read/write scopes; default deny unspecified scope.
- Sandbox test credentials operate only against sandbox endpoints/fixtures, never bypass production policy, quotas, domain restrictions or billing. Provide predictable test outcomes (allow/challenge/reject/error/expiry) only under authenticated sandbox and explicit test context.

---

## 5. CHALLENGE ENGINE AND SECURE VERIFICATION PROTOCOL

This is a security product. Correctness of the server-side protocol is more important than the appearance or quantity of CAPTCHA widgets.

### 5.1 Supported challenge modes (CAP-01 through CAP-07)

Implement a capability registry and pluggable providers. Each provider declares supported modes, accessibility alternative, cost constraints, expiry, validation contract and configuration schema. Only expose enabled, verified providers.

1. **Checkbox / managed:** an accessible checkbox or one-click trigger backed by actual server-issued challenge/risk assessment; checking a box alone is never a pass.
2. **Proof of work (PoW):** use the maintained open-source ALTCHA widget and PHP library when compatible and licensed, or a documented audited alternative. Confirm widget/library major-version protocol compatibility and supported algorithms/options in the actual version; do not combine incompatible v1/v2 payloads. Pin a compatible version. Use server-controlled bounded difficulty, unique signed short-lived challenges, web worker computation where supported, safe low-resource defaults and fast server verification. Do not implement home-grown crypto when established primitives/libraries suffice. PoW adds economic cost to automated abuse but is not proof of human identity; GPUs, botnets and outsourcing can undermine it. Benchmark low-end devices and do not trigger high-cost repeated work or deny solely based on compute speed.
3. **Invisible / non-interactive:** a real risk assessment and/or background PoW flow without forced user interaction when policy permits; explicit widget lifecycle, success/failure callbacks, expiration, and accessible recovery. Do not call an always-pass hidden input invisible CAPTCHA.
4. **Adaptive:** policy-based choice of mode and action (`allow`, `challenge`, `throttle`, `deny`, `step_up`, `observe` internally as appropriate). Consider action sensitivity and validated risk, accessibility needs, device capabilities and retry budget. Default to low friction when evidence permits, not indiscriminate denial. Allow safe tenant configuration and platform limits.
5. **Slider/puzzle:** original or properly licensed assets, server-generated randomized challenge identifiers and server-side verification, bounded retries, mobile touch and keyboard or equivalent accessible alternative. Do not deliver answer keys or authoritative solutions to browser code, or accept client claims of success. Do not represent puzzle difficulty as security assurance; validate the actual design through authorized testing. Limit image generation CPU, cache only safe public/static assets and prevent upload/decompression abuse.
6. **Image selection / interactive:** a curated original/licensed image/icon dataset and server-owned validation; no copyrighted competitor images, private data or unsourced large datasets. Store metadata and validation rules privately; safe bounded rendering, accessibility alternative, localized instructions, test challenge lifecycle. If no quality dataset or accessible fallback can be provided, explicitly label experimental/blocked rather than launch an ineffective challenge.
7. **Audio / accessibility:** Persian and English accessible recovery flow. Prefer privacy-preserving nonvisual alternatives that do not depend solely on hearing or visual recognition; if implementing audio CAPTCHA, use licensed or generated audio, server-owned answers, randomized safe challenge selection, expiration and rate limits. Do not expose answers or imply audio alone is secure. Provide mute/play/replay controls, instructions, keyboard support, screen reader announcements, and nonaudio alternative for deaf users. Never use disability claims as automatic allow/deny signals.

Provider configuration: allowed challenge modes, adaptive mode, safe difficulty tiers, max retries, TTL, appearance and locale. Platform sets hard bounds; tenant cannot set unsafe or impossible values in production. A brand-image customization feature may use approved assets but must not weaken challenge generation or expose answers. Support light/dark/auto theme, compact/normal layout, logo, colors, accessible labels and white-label only when entitled. A customization option must never allow arbitrary JS, CSS injection, uncontrolled HTML or weakened security.

### 5.2 Protocol lifecycle and invariants (CAP-08)

Define and test an explicit challenge/solution/token state machine: `issued -> solved -> token_issued -> consumed` with expiry/revocation/attempt-exhaustion paths. Represent actual states according to chosen provider, but maintain all invariants below.

1. Customer registers a site/domain/action and receives a public site key and server secret. Customer backend stores secret; widget uses only public configuration.
2. Browser initializes independently via the versioned widget asset, obtains public settings, and requests a challenge for a site, environment, normalized allowed origin/hostname and registered action. Validate the request; bind the server-issued record/signature to the site/tenant, expected context, nonce, issue time, expiry, provider version and relevant policy version. Browser headers/origin/action are untrusted context, not authentication; enforce server-side configuration and explain limitations.
3. Browser performs the selected challenge or permitted low-friction assessment. Server must validate provider-specific solution, signed fields, nonce/context, expiry, attempts and current site/key policy. Never trust a client assertion that it passed, a hidden checkbox, client-computed risk score, or a browser-supplied verification result. Perform cheap validation and quotas before expensive processing. All failures must be bounded and logged safely.
4. Successful assessment issues an opaque random, short-lived verification token (for example 32 random bytes encoded safely) tied to the tenant, site, environment, action, issued context, policy and creation/expiry. Store only digest or other secure representation of token, plus required metadata. Initial suggested TTL: challenge 2–5 minutes and verification token 1–2 minutes, configurable within safe documented limits. Never treat these as guarantees. Prevent multiple independent usable tokens from one challenge; ensure race safety.
5. Customer submits form/token to its own backend. Customer backend uses authenticated HTTPS server-to-server verification with its secret and expected action/site/domain where applicable. It authorizes the protected operation only after validating successful verdict and expected context. Browser success is a UX state, not authorization.
6. Server verifies active credentials, ownership, token binding, TTL, revocation, action, environment, policy and one-time consumption. Use a MySQL transaction/row lock or atomic conditional update with unique constraints; concurrent verification attempts must permit at most one successful consume. Exactly one success for one token, even across PHP workers. Prevent cross-site, cross-tenant, cross-environment and cross-action reuse. Verify and meter within a coherent transactional boundary; do not report success when durable state failed. Do not revalidate an already consumed token as successful; document safe retry/recovery if network response is lost. Any separate request-idempotency contract must not permit replay for a new protected operation.
7. Return structured API outcome and minimal authorized risk context to customer backend. On transient failure, distinguish retryable infrastructure errors from invalid/expired/consumed tokens; obtain new token if ambiguous. Customer should apply its own policy appropriate to operation sensitivity. Never silently allow high-risk actions when validation is unavailable.
8. Emit sanitized analytics/audit/usage records without token values or secrets; aggregate via cron; expire and clean temporary challenge/token data in bounded batches. Any asynchronous job failure must not change the already committed verification result.

Document trust boundaries and cryptographic design in `SECURITY_MODEL.md`. Use vetted CSPRNG, HMAC and standard constant-time comparison (`hash_equals` or library equivalent), purpose separation, signed canonical payloads where applicable, explicit key rotation and versioning. Do not disclose private detector logic/weights in browser responses or downloadable SDKs. Stateless signed challenges can reduce hot-path database writes, but still require durable replay/attempt control to satisfy one-use semantics. Do not sacrifice atomic correctness for speed. Never log complete challenge answers, raw verification secrets or tokens.

### 5.3 Browser widget and SDK contract (CAP-09)

Develop a small framework-independent TypeScript/JavaScript bundle and optionally a sandboxed cross-origin iframe with narrowly scoped permissions. If embedding an iframe, validate exact `postMessage` source, origin, message type, instance ID and schema, and use exact target origin. Never use wildcard origin for sensitive messages. If using Web Components/shadow DOM, avoid style conflicts and unsafe HTML, and provide CSP-friendly asset loading. Provide a documented trust model and strict allowlists. Never inject arbitrary third-party scripts. Treat all data entering JS and HTML contexts as untrusted.

- Stable public loading URL (e.g. `/assets/captcha/v1/widget.js`) plus immutable versioned release paths, safe caching/invalidation, no secret in client configuration. Defer/asynchronous load, tree-shake, reduce JS and avoid third-party trackers/fonts. Provide an integrity hash for immutable assets where appropriate and document CSP directives and any worker restrictions. Serve widgets across explicitly configured domains with proper response headers.
- Programmatic contract similar to `ShieldCaptcha.render(container, options)`, `execute(instanceId)`, `getResponse(instanceId)`, `reset(instanceId)`, `remove(instanceId)`. Options: site key, registered action, locale, theme, size, mode, callbacks for success/error/expiry/timeout, accessible fallback. Choose real signatures and generate docs/types/SDK from the implemented version. Keep multiple instances on one page independent, including SPA navigation and component unmount.
- Handle duplicate initialization, double submission, slow/offline network, timeouts, canceled requests, expired challenge/token, blocked third-party storage, resize, dark mode, RTL, touch, keyboard, tab visibility, unsupported browser features and safe retry. No requirement for third-party cookies. Worker/iframe cleanup on reset/remove. Do not trap focus or freeze page. Use progress and status with accessible live region; no misleading infinite loader.
- Form integration: hidden response field (configurable name, default `shield-captcha-response`), token on success, clear on expiration/reset, no secret in front end. Protect against double submit in UX but enforce server verification. Document HTML form, AJAX, SPA, server-rendered, mobile, multiple widgets, event callbacks, refresh and reset. Provide explicit no-JS failure or accessible alternate workflow; never auto-accept when JS absent.
- Browser support matrix with tested versions, responsive and locale tests. Test CSP, cross-origin embed, multiple instances, JS-disabled fallback, slow network, token expiry, trusted-origin enforcement, server verification and no framework coupling.

---

## 6. RISK, BEHAVIORAL ANALYSIS AND SECURITY POLICY ENGINE

### 6.1 Rule-based risk engine (RISK-01)

Implement real configurable deterministic scoring and policy evaluation first; no required machine-learning model or AI API. Define score direction unambiguously: `risk_score` integer 0–100, higher means more risk, not probability unless calibrated with evidence. Return `decision` and confidence/coverage when useful. `unknown` or insufficient signals must not be treated as guaranteed human or malicious. Keep verdict decisions distinct from challenge verification success.

- Normalize and validate minimal signals. Use coarse interaction summaries (timing buckets, page interaction/visibility indicators, proof-of-work outcome, navigation context), first-party ephemeral identifiers where permitted, server-observed request rates, tenant-provided business events and verified server signals. Client-side telemetry is spoofable; weight cautiously. Avoid raw keystrokes, typed content, mouse trace history, passwords, clipboard, full fingerprints, cross-site tracking, canvas/audio fingerprinting by default, intrusive behavioral biometrics, or sensitive personal data. Provide a privacy mode and configuration to disable optional signal categories. Use tenant-specific short-lived pseudonymous identifiers with purpose separation, documented retention and rotating salts/keys where appropriate. No cross-tenant individual tracking by default.
- Server-side signals: velocity and repeated attempts at site/action/credential/destination/account pseudonym levels, failed or consumed tokens, suspicious burst indicators, trusted bot identity, feedback outcomes, optional licensed IP/ASN/reputation signals. Treat shared networks, VPNs, proxies, assistive technology, privacy browsers, low-power devices and geographic uncertainty fairly; never block solely because a VPN, IP region, shared IP, browser feature or disability signal exists. Do not assume an IP identifies a person. Only trust original client IP obtained through configured trusted proxies. Do not use client-submitted IP as authoritative.
- Implement a versioned `RiskEngine` with bounded feature extraction, deterministic rules, normalized score aggregation and coarse reason categories (e.g. `unusual_velocity`, `challenge_required`, `policy_restricted`, `trusted_automation`, `insufficient_context`). Separate detailed internal diagnostic data from tenant-visible and public responses. Public endpoints never expose exact detection weights, thresholds, raw telemetry or sensitive intelligence. Authenticated customers may receive actionable coarse reasons and aggregate insights, within scope.
- Manage platform defaults and tenant overrides with validation, strict bounds and policy precedence. Do not use arbitrary code execution, dynamic SQL or eval for rules. Create a typed condition/action DSL with allowlisted fields/operators; validate syntax, complexity, evaluation cost and regex safety. No user-provided executable code. Publish immutable policy versions and rollback; audit changes. Record applied policy version in outcomes.
- Configurable action-specific thresholds (e.g. lower-risk allow, medium step-up/challenge, high restrict/deny). Any numerical defaults are starting hypotheses, not universal safety thresholds. Provide a safe initial observe/tuning workflow and explain uncertainty. Apply platform safety controls and revocations regardless of tenant overrides. Support `allow` for authenticated trusted integrations only, never let allowlists disable platform quotas, expiry, authentication, tenant boundaries or mandatory security. Hard limits and emergency controls precede tenant preferences.

### 6.2 Rules, shadow mode and feedback (RISK-02)

- Rule builder in Filament: choose site/environment/action, evaluation order, condition group AND/OR with limited nesting, actions (allow subject to mandatory checks, challenge provider, step-up, throttle, deny, alert, tag/observe), enable schedule/expiry, preview impact, version history and rollback. Include managed defensive templates for login, registration, OTP, form spam, API bursts, promo abuse, content requests, known permitted bots, and tenant custom rules. Use immutable published versions; drafts do not affect decisions.
- Shadow/observe mode: record the decision the policy would have made, while preserving mandatory token/auth/rate-limit controls. Compare observed versus enforced outcomes, challenge rates, estimated false positives and feedback. Clearly label hypothetical counts, not actual blocks. Protect sampling and retention, prevent unbounded event volume. Enable explicit staged rollout; never automatically activate new rules solely on synthetic data.
- Authenticated server feedback endpoints for legitimate/abusive/unknown outcomes and event IDs bound to tenant/site/action. Validate labels, deduplicate and audit. No unauthenticated or cross-tenant label injection. Keep feedback separate from ground truth until quality is verified. Do not automatically learn high-impact policies from a single tenant or user-supplied label. Allow authorized review and correction.
- Provide configuration testing using synthetic fixtures and sandbox, policy linting, expected-decision preview, version diff, deterministic contract tests and a privacy-safe score distribution. No live traffic blocking merely to test an unapproved rule.

### 6.3 Reputation, device, session and future ML (RISK-03)

- Implement a local privacy-safe reputation/velocity baseline; optional IP reputation/geolocation/ASN provider adapters behind configuration, bounded timeout/caching and explicit licenses. Document that geo/IP is approximate. No external provider or paid data feed is required in shared mode. Do not send unnecessary personal data or secrets to a provider. Stale/unavailable intelligence is `unknown` with conservative policy, not automatically malicious.
- Session protection uses first-party scoped session identifiers and server events; expire state, limit collection and enforce privacy. Device intelligence is probabilistic, not identity; avoid invasive browser fingerprinting by default and require documented opt-in for any higher-sensitivity signal. No tracking across unrelated customer sites.
- Optional future ML adapter: define a stable model input/output schema and evaluation pipeline, offline training outside shared hosting, model/version governance, drift monitoring, labeled holdout data, calibration, fairness checks, signed model artifacts and rollback. Provide a genuine optional integration only if available/validated; otherwise use the deterministic engine and mark ML not configured. No fake AI badge, generated random score, mandatory Python daemon, or unverified claim of learned detection.
- Integration health checks: assess whether widget loaded, production keys/domains configured, recent backend verify received, expected action/context checked by sample integration, and deployment health. Client observations cannot conclusively prove all customer-side enforcement; show `observed`, `verified by test`, `not observed`, or `unknown` with troubleshooting links rather than a false guarantee.

---

## 7. PROTECTIVE PRODUCTS AND CUSTOMER ENFORCEMENT

Reuse shared risk/policy, rate-limit, tenant, credential and analytics services. Each product must have an actual integration, protected-operation contract, configuration, dashboard, docs, test vectors, and truthful limitations. A risk assessment alone is advisory unless enforcement occurs in the customer's backend or an authorized gateway. Never accept customer payloads containing passwords, OTPs, raw payment card data, bank credentials or unrelated sensitive content.

### 7.1 SMS Shield / OTP Abuse Prevention (ABUSE-01)

- Provide authenticated server-side pre-send assessment for registration, login OTP, recovery and resend; verify CAPTCHA token when configured, validate action/site and assess rate/velocity by tenant, site, destination pseudonym, account/session pseudonym and trusted request context. Prefer customer-side HMAC pseudonymization for phone numbers, document normalization and tenant-scoped keys. Do not collect raw phone numbers unless explicitly necessary and consented; never log OTP codes, SMS body or raw destinations. Do not send SMS on behalf of a customer by default.
- Implement per-destination, per-account, per-site, per-tenant and global controls with configurable safe cooldowns, bounded attempt windows, daily caps, challenge/step-up/deny and retries; ensure atomic counters under concurrency. Maintain quotas and replay resistance when many requests arrive at once. Do not allow a single untrusted signal to cause unjustified lockout; include user recovery and abuse-resistant policy guidance.
- API returns an authorized recommendation with event ID and decision; customer calls its own messaging provider only if policy permits. Provide backend integration samples and a mock sender for tests. Add feedback endpoints for sent/delivered/verified/abuse-confirmed events as supported by customer, deduplicate and validate tenant ownership. No false claim of messages prevented when the customer has not enforced the recommendation.
- Dashboard: assessment volume, actual reported sends, blocked/step-up recommendations, observed enforcement when confirmed, resend rate, policy reasons and estimated cost reduction. Cost per message configurable by tenant using integer minor units. Clearly distinguish actual measured savings from hypothetical/estimated savings; account for subscription, unknown labels and false positives. Implement a pricing/ROI calculator with explicit assumptions; no guaranteed financial result.

### 7.2 Account Shield (ABUSE-02)

- Protect login, registration, password reset and account recovery through server risk assessment, CAPTCHA or step-up, per-account/pseudonym and per-site velocity controls, outcome feedback (success, failure, confirmed abuse), unusual patterns and configurable policies. Tenant owns authentication and account records; never request plaintext passwords, MFA codes, session cookies or private identity documents.
- Support detection/recommendation for credential stuffing, password spraying, signup abuse, account takeover indicators and suspicious recovery patterns using legitimate customer events. Do not promise identity verification, breached-password detection without a valid provider, or replacing rate limits, password hygiene and MFA.
- Return coarse action-specific verdicts and recommendations (allow with normal auth, request CAPTCHA, step-up/MFA, throttle or deny). Customer must enforce them and must not expose internal risk reasons to potential attackers. Include account recovery and accessibility guidance, avoid revealing whether an account exists, and test shared-IP/legitimate-user scenarios.

### 7.3 Bot Shield and Anti-Spam (ABUSE-03)

- Protect configured browser/server actions and form endpoints. Classify events as permitted/trusted automation, browser interaction, unknown, suspected automated or policy-blocked; confidence is separate from classification. Do not treat User-Agent strings alone as identity. Provide tenant-level bot policies, quotas, action-based decisions, form spam protection, comment/contact templates, and supported CMS integrations.
- For authorized automation use scoped server/API credentials, registered client identity and optionally verifiable signatures or an official verified-bot directory with documented verification method. Never automatically trust a crawler because of a claimed User-Agent; avoid slow/unbounded network verification on the hot path. Implement narrow caching and fallback unknown. Distinguish registered, verified, unverified and malicious/suspicious classes; a legitimate machine client is not necessarily abuse.
- Anti-spam accepts minimal form event metadata/derived indicators; do not upload private form content unless a separate explicit, authorized, minimized integration is designed. Provide rate controls and feedback. No mandatory third-party CAPTCHA or AI service.

### 7.4 API Shield (ABUSE-04)

- Offer authenticated server-to-server endpoint assessment with tenant-scoped registered routes/actions, client/service pseudonyms, per-client/endpoint/tenant quotas and safe global bounds. SDK middleware for supported stacks, allowlisted policy inputs, scoped service keys, timeout/backoff, normalized errors and signed responses if a selected integration needs them.
- Protect an API only where the customer implements backend middleware or optional gateway enforcement. No browser CAPTCHA requirement for legitimate non-browser APIs; use service authentication, quotas and risk policies. Never represent a public site key as a private API token. Do not ingest full request bodies or authentication secrets. Use coarse metadata and event feedback.
- Optional edge profile: customer-authorized gateway adapter, authentication, trusted forwarding configuration, explicit upstream allowlists, request limits, health checks and integration tests with owned local mock upstream. Do not deploy a generic open proxy or arbitrary forwarding service. Fail closed for unsafe upstream configuration. Document how bypassing the gateway is prevented by customer origin controls. Network-layer DDoS protection must remain a provider/network capability, not a shared-hosting promise.

### 7.5 Fraud Shield (ABUSE-05)

- Rule-based assessment of signup, promo/coupon redemption, checkout and suspicious transactions via customer-provided minimized pseudonymous metadata: event type, amount/currency when appropriate, velocity, account age bucket, result, prior event linkage and trusted server context. Validate allowed fields and limits; do not collect payment card details, CVV, bank secrets, full billing addresses or passwords. No automated collection of financial identity data.
- Provide action-specific verdicts: allow normal customer controls, step-up, manual review, throttle or deny. Do not make actual financial transactions or charge users based solely on a score. Tenant remains responsible for decisions; include audit trail, review/appeal and feedback. Offer an operator-visible, configurable policy and a tenant review queue with coarse reasons and decision history. Clearly distinguish suspected and confirmed fraud.
- Report risk distribution, review outcomes, approved/declined recommendation versus actual customer-reported outcome, and estimated prevented abuse with uncertainty. Avoid fabricated fraud amounts or percentage guarantees.

### 7.6 Scraper Shield and AI Agent Management (ABUSE-06)

- Provide application-layer middleware examples and rules for explicitly protected content/data endpoints: request velocity, authentication/entitlement checks, bounded access, safe caching, registered automation, and risk-based limits. Keep normal public search indexing and approved automation possible; provide configurable allowlists subject to mandatory platform safeguards.
- Provide verified crawler and agent identity policies using supported official identity sources or tenant-registered service credentials, with separate labels for declared, verified, unknown and disallowed automation. Treat robots.txt as a communication mechanism, not an enforcement boundary. Allow tenants to configure permitted actions for AI agents and crawlers. No blanket promise to identify all AI agents or determine intent.
- Full-site anti-scraping, network controls and gateway enforcement require the optional edge profile and customer-side origin enforcement; do not claim protection on unintegrated URLs. Document differences between protecting forms, authenticated API endpoints and public web pages. Do not provide scraping, CAPTCHA circumvention or evasion tooling. Test only with synthetic authorized clients against local fixtures.

---

## 8. REST API, CONTRACTS, ERRORS AND INTEGRATIONS

### 8.1 Canonical API (API-01)

Define one versioned API and make the actual implementation the source of truth for OpenAPI, docs, SDKs, examples and tests. Suggested endpoints below may be renamed with a documented ADR, but all functions must exist. Use HTTPS in production, JSON, bounded body sizes and schemas, strict method/content-type handling, stable request IDs and error codes, pagination limits, idempotency where appropriate, and authentication/authorization before expensive work. Browser and server endpoints have different trust models.

| Method / path (relative to `/api/v1`) | Caller | Purpose |
|---|---|---|
| GET `/widget/config` | public site key | Safe public configuration and supported modes, no secrets/risk rules |
| POST `/challenges` | browser / public site key | Issue bound short-lived challenge or assessment flow |
| POST `/challenges/{id}/solve` | browser | Validate provider result and issue one-time token if permitted |
| POST `/verify` | customer backend / verification secret | Verify and consume one token, validate expected site/action/environment/context |
| POST `/assessments` | scoped server credential | Generic server-side action risk evaluation, optional CAPTCHA enforcement |
| POST `/sms/assess` | scoped server credential | Pre-send OTP/SMS assessment |
| POST `/accounts/assess` | scoped server credential | Account/login/signup/recovery assessment |
| POST `/api-protection/assess` | scoped server credential | API/endpoint access assessment |
| POST `/fraud/assess` | scoped server credential | Fraud policy assessment |
| POST `/bots/assess` | scoped server credential | Bot/crawler/agent/content policy assessment |
| POST `/feedback` | scoped server credential | Submit authorized event outcomes, idempotently |
| GET `/usage` | scoped management key | Tenant-scoped usage summary |
| GET `/events` | scoped management key | Paginated, redacted security event view |
| `/sites`, `/sites/{id}/keys`, `/sites/{id}/policies` | scoped management key | Authorized tenant management operations (create/read/update/revoke as appropriate; sensitive secrets shown once) |
| GET `/health/live` | public minimal | Liveness without sensitive diagnostics |
| GET `/health/ready` | protected or minimal public status | Readiness without DB details/secrets |
| dedicated sandbox routes or sandbox key context | test credential | Deterministic isolated test results; no production bypass |

If supporting a familiar `siteverify` migration endpoint, document its compatibility boundaries, accepted parameters, auth, context checks, errors and token semantics. Do not fake support for competitor tokens, vendor private APIs or third-party secrets. Do not implement CAPTCHA solving for competing services. Provide migration docs mapping the customer's existing form integration to your verified API.

### 8.2 Request and response semantics (API-02)

- Public request fields: `site_key`, registered `action`, locale, optional bounded privacy-safe context; issue challenge ID/provider/version/expiry/public challenge parameters/allowed next step. Do not return private answers, key material, detailed risk features or internal policy thresholds. If a challenge is not required, still follow server-owned token issuance and consumption protocol. Verify Origin/allowed host where meaningful; do not treat CORS as auth or Origin as non-browser proof. Host claim must be validated against registered context; document trust limits. Do not expose platform management endpoints to arbitrary origins.
- Server verification uses a scoped secret in `Authorization: Bearer ...` or another documented secure transport; never a URL query parameter or frontend header. Example canonical request (adapt and test actual fields):

```json
{
  "token": "<ONE_TIME_WIDGET_TOKEN>",
  "site_key": "site_prod_example_public_key",
  "expected_action": "login",
  "expected_hostname": "shop.example.test"
}
```

Example illustrative response (update docs to reflect exact real implementation):

```json
{
  "success": true,
  "decision": "allow",
  "risk_score": 18,
  "reason_codes": ["policy_satisfied"],
  "site_key": "site_prod_example_public_key",
  "action": "login",
  "hostname": "shop.example.test",
  "environment": "production",
  "verified_at": "2026-01-01T12:00:00Z",
  "request_id": "req_example"
}
```

`success` must mean a valid, newly consumed token and defined verification result; separate policy `decision` and enforce the customer's required policy. If a token is valid but a changed policy disallows an operation, make response semantics unambiguous and document safe handling. Never return `success:true` to an invalid, expired, replayed, wrong-context, sandbox/production mismatched or unauthorized request. Do not return another tenant's existence, event details or secret-dependent internal errors.

- Assessment contract: `{site_key, action, event_id?, captcha_token?, context:{allowlisted_minimal_fields}}`; define per-product schemas with limits, token consumption semantics, pseudonym formats, event timestamps, client-supplied versus server-observed field provenance, and required scopes. Return `assessment_id`, `decision`, `risk_score` or `null` if unknown, `reason_codes`, `policy_version`, `expires_at` when applicable, and `request_id`. A risk decision is not a replacement for token verification or customer auth. If assessment consumes a CAPTCHA token, document that a second `/verify` call is invalid; do not require double consumption. Make docs/examples internally consistent.
- Errors: stable machine-readable code and safe localized/display message separately; include request ID and `retry_after` where appropriate. Distinguish 400 malformed, 401 unauthenticated, 403 unauthorized/disallowed context, 404 generic missing resource, 409 consumed/conflict where specified, 422 validation, 429 throttled/quota, 503 transient unavailable, and deterministic security rejection (choose consistent HTTP/JSON contract and document). Suggested codes: `invalid_request`, `invalid_key`, `site_disabled`, `environment_mismatch`, `action_mismatch`, `domain_not_allowed`, `challenge_invalid`, `challenge_expired`, `attempt_limit`, `token_invalid`, `token_expired`, `token_already_used`, `policy_denied`, `quota_exceeded`, `rate_limited`, `service_unavailable`. Avoid exposing secrets or stack traces. Do not use ambiguous boolean-only errors in documentation.
- Strict validation: sizes, types, enums, unknown fields policy, UTF-8, identifier lengths, timestamps, numeric bounds, normalized hostnames, JSON depth, query limits. Reject unsupported content types and avoid expensive regex/image/parsing before rate controls. CORS only for public browser routes, exact configured origin matching where possible, `Vary: Origin` where dynamic, never credentials plus wildcard origin; authenticated panel routes use CSRF protections. Token/verification and admin responses are `no-store`. Version assets and cache only non-sensitive data.
- Idempotency: apply durable tenant-scoped keys to billable create/feedback/webhook operations when appropriate, request hash and expiry; reject changed payload under same key. A successful one-use verification must not be reused for a different operation. Document handling of lost responses and transient errors. SDKs must not blindly retry a non-idempotent consume, payment or charge.

### 8.3 API management, webhooks and observability (API-03)

- Management API keys with explicit scopes, expiration, rotation and tenant context; use policy checks, rate limits and audit for management operations. Never let an API consumer create operator accounts or access another tenant.
- Webhooks for tenant-enabled security events, usage thresholds, billing status and relevant service changes. Use signed payloads with timestamp, version and delivery ID, bounded retries, idempotency guidance, dead-letter status, allowlisted event types and endpoint validation. Protect outbound webhook delivery from SSRF: HTTPS by default, disallow internal/metadata destinations, revalidate DNS/redirect, limit ports/timeouts/response bytes, no arbitrary headers/secrets, never follow untrusted redirects. Store signing secret securely; test via owned local fixtures and configurable safe test transport. Operators can inspect delivery status and retry with authorization; never expose raw payloads containing secrets or personal data.
- Notification channels: in-app database notifications, configured SMTP email, optional Telegram bot or similar explicit opt-in provider, optional signed webhook. No outbound credentials or third-party dependency required for base operation. Show delivery status accurately; cron-compatible processing and bounded retries. Avoid leaking sensitive events in external channels. Test email with mail fake, SMS with mock and webhooks locally; no real external side effects without approval.
- OpenAPI spec under `openapi/v1.yaml` (or JSON), generated/validated examples, Postman collection/environment with placeholder variables, contract fixtures and error code catalog. Public docs display supported versions and deprecation policy. Add API/SDK compatibility tests and changelog entries for breaking changes. Use an offline/self-hosted docs viewer (no remote CDN dependency) with safe test-only interactive requests. Never autofill or store production secrets in an interactive browser console.

---
## 9. CUSTOMER PANEL — FILAMENTPHP 5 (CUST-01 THROUGH CUST-15)

Create a separate Filament 5 customer panel provider with tenant navigation, localized routes/labels, authenticated access, email verification, policies, optional MFA, tenant-aware global search and menu items gated by permissions and feature availability. Use current v5 resources, pages, tables, schemas, widgets, actions, relation managers and notifications. Do not copy v3/v4 syntax. Use two separate panel providers and unique IDs/paths. The customer panel is for tenant business users, not just an exposed admin panel.

| ID | Page/module | Required behavior |
|---|---|---|
| CUST-01 | Onboarding | Create/join organization, choose language, create site, verify domain, choose action/mode, show keys once, copy snippet, backend verification instructions, sandbox test, integration checklist and completion status. Progress persists; permit skipping optional settings. |
| CUST-02 | Security Overview | Current selected organization/sites/date range, usage/quota, actual verification requests/outcomes, challenges, risk distribution, potential threats, response-time metrics, availability and setup alerts. Explicitly label observed vs estimated vs unknown. Real aggregate queries, no production fixtures. |
| CUST-03 | Websites & Apps | Search/filter/create/update/pause/archive sites, environment separation, domain verification/allowed hosts, app IDs, registered actions, policy assignment, integration health and test tools. |
| CUST-04 | API Keys | Create/revoke/rotate scoped credentials, show once, copy safely, prefix/status/last-used, expiry and audit; no raw secret retrieval. |
| CUST-05 | CAPTCHA Designer | Provider modes, theme, language, size, accessible controls, difficulty bounds, custom approved assets, logo/white-label entitlements, live real sandbox preview and save/version config; generate actual integration snippet. |
| CUST-06 | Rules & Risk | Templates, typed rule editor, precedence, action thresholds, shadow/enforcement mode, validation, test preview, draft/publish, version history and rollback, coarse reason explanations. |
| CUST-07 | Traffic Analytics | Date/time/site/action/environment filters; verified/challenged/denied/throttled/unknown counts, success and completion rates with denominators, trends, device/network aggregate breakdowns only if collected, latency percentiles from real data, pagination. |
| CUST-08 | Security Events / Threats | Tenant-scoped paginated logs, event ID, timestamps, type, policy version, action, decision, coarse reasons, scope-filtered risk and sanitized metadata; retention controls; export safely. |
| CUST-09 | SMS Shield | Policy, test pre-send flow, velocity statistics, resend controls, reported sends/deliveries, blocked recommendations, cost assumptions and estimated savings, links to code examples. |
| CUST-10 | Account, Bot, API, Fraud, Scraper, Agents | Separate product pages with actual configuration, appropriate event schemas/metrics, protected action/site selection, integration instructions, feedback and review where relevant; disabled/infrastructure-dependent features accurately gated. |
| CUST-11 | Alerts & Webhooks | Alert rules/thresholds, channels, verified destinations, webhook secret rotation, delivery history/retries, opt-in, notification preferences, test with safe test payload. |
| CUST-12 | Integrations & Downloads | Tested framework/CMS catalog, copy code for selected environment/site, artifact version/compatibility, download link/checksum, migration checklist, integration health status, docs and sandbox. Never inject server secret into example frontend. |
| CUST-13 | Team & Audit | Invitations, permissions, roles, removal, ownership transfer, MFA status as authorized, audit trail for changes, scoped organization exports. |
| CUST-14 | Billing & Usage | Plans, quota and reset date/time, subscriptions, trial, invoices, payment history, manual invoice request if configured, upgrade/downgrade/cancel, notifications and explicit statuses. |
| CUST-15 | Settings, Privacy & Support | Organization profile, locale/timezone, retention settings, export/deletion requests, support tickets and replies, knowledge base, incident notifications, account and session settings. |

UI acceptance: no cross-tenant record leakage via filters, relation managers, record URLs, export, dashboard caches or global search. Validate all Livewire actions server-side. All destructive actions require clear confirmation and authorization, key rotation shows impact, and billing or policy actions are auditable. Use indexed/paginated queries and cached aggregate widgets rather than unbounded loading or heavy polling. Avoid operator-only reasons and raw security signals in tenant UI. Add Filament component tests for permissions, validation, scoping, form actions, and end-to-end onboarding and integration.

---

## 10. PLATFORM OPERATOR PANEL — FILAMENTPHP 5 (ADMIN-01 THROUGH ADMIN-12)

Create an independent `/admin` provider, operator-only roles, required MFA, restrictive session handling, scoped permissions, no public operator registration and secure initial operator provisioning via a controlled CLI command. Never create default production passwords or a hidden admin bypass.

| ID | Page/module | Required behavior |
|---|---|---|
| ADMIN-01 | Operations Overview | Real tenant/site counts, subscriptions, sanitized verification volume, error rates, latency, resource/cron/queue/backlog indicators, quota anomalies and incident status. |
| ADMIN-02 | Users & Organizations | Search, status, membership review, suspend/reactivate with reason, entitlements, safe privacy requests and audit; no secret reveal. |
| ADMIN-03 | Sites & Abuse | Site state, key fingerprints, suspicious volume, controlled pause/revocation, quota adjustment, global emergency disable, alerts and case records; all sensitive actions audited. |
| ADMIN-04 | Plans & Feature Flags | Versioned plan definitions, entitlements, quotas, trial, pricing, feature readiness, deployment profile, staged enable/disable, limits and safe defaults. No feature bypass by modifying client UI. |
| ADMIN-05 | Billing | Subscriptions, invoices, payment references, reconciliations, refunds/credit records only when supported, manual approvals, tax/currency settings, ledger consistency, access restrictions. |
| ADMIN-06 | Challenge & Risk | Supported provider registry, cost/TTL bounds, approved challenge assets, risk policy defaults, managed templates, versions, safe rollback, shadow results, aggregate evaluation. Do not expose private detector details to tenant/public routes. |
| ADMIN-07 | Security & Privacy | Security cases, redacted incidents, retention policies, privacy requests, access reviews, emergency revocation, system audit, abuse controls. Log sensitive administrative reads/actions with purpose. |
| ADMIN-08 | Support | Tickets, categories, statuses, priorities, internal notes restricted from customers, replies, attachments with scanning/validation where supported; secure private file access, no silent impersonation. |
| ADMIN-09 | Content & Docs | Localized pages/articles/FAQs, help, release notes, docs editorial workflow, draft/published state, safe Markdown rendering, version consistency and review; API reference remains generated from actual canonical spec. |
| ADMIN-10 | Downloads & Releases | Catalog imported from signed/checked manifest, artifact status, platform/version compatibility, hashes, release notes, visibility, deprecate/yank; no arbitrary path, script execution or public upload. |
| ADMIN-11 | System & Integrations | Brand and locale settings, provider readiness, SMTP/payment/webhook/CDN config references, safe operational test actions, health status, queue/cron failures, backup metadata, maintenance mode. No editable raw `.env` or shell/SQL console. |
| ADMIN-12 | Audit & Reports | Operator audit with actor, timestamp, reason, sanitized changes, request ID and scope; role management, least-privilege access, export and tamper-evident strategy as practical. |

Provide immutable/tamper-evident audit design where appropriate: append-only at application level, restricted DB permissions when hosting allows, integrity checks, off-host secure copies. Do not claim audit storage is immutable against database administrators on shared hosting. Separate operator/support/export privileges. Test operator-role changes, suspend/revoke flows, feature gating, and tenant isolation when privileged operators switch explicit scope.

---

## 11. PRICING, ENTITLEMENTS, USAGE AND BILLING (BILL-01 THROUGH BILL-06)

Implement configurable plans (Free, Starter, Growth, Business, Enterprise; names/prices/limits may change). Define actual quota units in UI and docs; for example, one authorized billable verification or assessment according to plan terms, not every asset request. Support multiple websites, included units, per-product units, rate/burst limits, custom quotas, log retention, team size, feature availability, branding and support entitlements. Free/trial usage must also be protected against abuse and cost overruns. Do not hard-code competitor prices or promise unlimited resources. Default prices must be editable and clearly designated as sample until configured; public pricing must reflect persisted published configuration.

- Version plan terms and retain historical invoice/contract terms. Subscriptions: trial, active, pending payment, past due, grace, canceled, expired, suspended; define state transitions and renewal/cancel/downgrade behavior, including quota reset period and timezone. Support monthly/yearly periods, and optional upgrades/proration only if implemented correctly with tests. Make entitlement checks in domain services and API, not only navigation. Do not silently revoke protection on a benign billing delay without documented customer notification and policy.
- Use append-only or adjustment-based usage ledger with idempotency/unique event constraints, integer quantities, product/action/site/environment dimensions, transactionally consistent quota enforcement, reset/renewal rules, and periodic aggregation. Define whether failed, expired, throttled, sandbox, consumed, shadow or assessment events are billable; default to clear, fair behavior. Meter one operation exactly once under concurrency and retries. Do not double bill challenge issuance plus verification unless transparent published terms specify it.
- Enforce per-tenant, site, product, free-tier and global limits atomically; avoid unbounded counters and noisy-neighbor resource exhaustion. On exceeded quota, return structured errors and documented retry/reset information. Configurable hard/soft limits, alerts at thresholds, grace policies and safe operator overrides with audit; no surprise charges or cross-tenant quotas. Avoid making availability promises based solely on plan names.
- Invoices: number, tenant, line items, quantity, period, tax configuration, currency, integer minor units, state, provider references and printable downloadable invoice (PDF or secure HTML, accurately labeled). For Iran support IRR (rial) accounting and configurable display in toman (10 rials = 1 toman) without ambiguous rounding or duplicate conversions; support other ISO currencies only if configured. Do not silently treat toman as an ISO currency or invent tax/legal obligations. Provide configurable company fields and legal review checklist.
- Payment gateway interface: manual invoice/bank-transfer workflow with review and audit, sandbox/fake adapter only in local/test, and a real provider adapter only where official credentials/docs and market/provider requirements are available (e.g. a configured Iranian gateway or international payment provider). Never store card PAN, CVV, OTP or sensitive payment data; use provider-hosted payment flow. Verify callback/signature/transaction with provider on the server, reconcile amount/currency/invoice and idempotently update payment state. Never mark paid based on a client redirect/query parameter or a mock callback in production. No test payment can create real charges. Secure refund/cancel/reconciliation and receipt handling. Mark provider activation blocked if credentials or account eligibility are missing.
- Enterprise quote/contact flow, manual subscription assignment with reason/expiry, configurable SLA terms and support workflow. Any actual SLA requires capacity and operational verification. Provide data export and retention on cancellation, with legal review where required.

---

## 12. DATABASE, ANALYTICS, PRIVACY AND RETENTION

### 12.1 Schema and invariants (DATA-01)

Design normalized, indexed Laravel migrations with foreign keys, tenant ownership, environment, timestamps and lifecycle state. Select UUID/ULID or numeric internal IDs deliberately; public identifiers must be non-secret and authorization must not depend on guessability. Use secure random values for credentials/tokens rather than identifier generators. MySQL/MariaDB baseline; do not require DB-specific features not available on selected host. Avoid giant JSON columns as the only query model. Illustrative entities (merge/split with documented decisions):

| Domain | Required logical entities |
|---|---|
| Identity/Tenancy | `users`, `organizations`, `organization_memberships`, `invitations`, `operator_roles`/permissions, sessions, MFA/recovery material according to framework design |
| Sites/Credentials | `sites`, `allowed_domains`, `domain_verifications`, `site_actions`, `api_keys`, `key_versions`, widget configurations and branding assets |
| Challenge/Verification | `challenges` or durable challenge-use records, `challenge_attempts` (bounded if needed), `verification_tokens`, `verification_events` |
| Risk/Policy | `security_policies`, `policy_versions`, `security_rules`, `risk_assessments`, `risk_feedback`, trusted bot/client identities |
| Abuse controls | `rate_limit_buckets`, `sms_events`, `account_events`, `fraud_events`/reviews, generic protected-operation events with typed minimal context |
| Billing | `plans`, `plan_versions`, entitlements, `subscriptions`, `usage_ledger`, `usage_rollups`, `invoices`, `invoice_items`, `payments`, adjustments/reconciliation records |
| Analytics | `hourly_statistics`, `daily_statistics`, `latency_histograms` or suitable real aggregation, per-product metrics, integration-health state |
| Alerts | `alert_rules`, `alert_events`, `notification_preferences`, `webhook_endpoints`, `webhook_deliveries`, jobs, failed jobs and job leases where needed |
| Content/Support | localized `articles`, categories, docs versions/publication metadata, `support_tickets`, `support_messages`, private attachments, `incidents` |
| Distribution | `release_artifacts`, release version/catalog metadata and download metrics (no raw secrets) |
| Governance | `audit_logs`, `privacy_requests`, retention/consent settings, idempotency records, safe configuration and operational readiness data |

Enforce unique constraints for tenant-scoped identifiers, membership, site domain/action combinations as appropriate, token digests, challenge consumption, idempotency, payment/provider references, usage-event IDs, and rate-limit bucket identities. Use indexes tailored to real queries: tenant/site/environment + timestamps, policy versions, expiry cleanup, status and lookup hashes. Verify SQL query plans with representative MySQL data. Avoid cross-tenant relationships even if internal IDs are supplied incorrectly; enforce in policies/services and constraints where feasible. Use transactions, bounded lock duration, consistent lock ordering, deadlock handling, atomic updates, and documented failure recovery. Test simultaneous requests on actual MySQL/MariaDB, not just SQLite. Keep currency and rates consistent. Write reversible safe migrations where possible, backup/upgrade notes, and never automatically drop customer data.

### 12.2 Analytics (DATA-02)

- Record only necessary sanitized event fields: tenant, site, environment, action, event type, timestamp, request ID, decision/outcome, policy/provider versions, duration bucket, coarse reason and optional privacy-safe dimensions. Never record raw CAPTCHA responses, answers, credentials, cookies, session tokens, personal form content or raw OTP data. IP/device collection must be minimized, configurable and justified; use truncation/pseudonymization and short retention where appropriate. Hashing a phone/IP alone is not anonymization.
- Build accurate dashboards from data and rollups. Real metrics: challenge issuance/completion/expiry, verification attempts and unique successful consumes, policy outcome distribution, 429/5xx, p50/p95/p99 latency where sufficient data, usage/quota, shadow would-block counts, integration-health observations, customer-reported send/abuse/fraud outcomes. Clearly label definitions, denominators and sample sizes; do not equate all challenges or rejections with bots or all allowed requests with humans.
- Frictionless rate = appropriately defined eligible legitimate/verified traffic passing without interactive challenge, with ground-truth limitations clearly explained; if no legitimate-user labels, report observed no-interaction completion instead. False positive rate and detection rate require labeled data and denominators; otherwise mark unavailable. Estimated savings must not be presented as actual savings. Analytics must distinguish sandbox and production, tenant and platform aggregate, recommendation and enforced/reported action, suspected and confirmed abuse.
- Efficient SQL aggregation: bounded cron batches, checkpoint/reprocess strategy, idempotent rollups, no duplicate usage from reprocessing, timezone-aware presentation, late-arriving feedback handling, reasonable chart date ranges, pagination and indexed filters. Avoid real-time full-table scans, unbounded dimensions, arbitrary report SQL or frequent Livewire polling. Offer near-real-time data if justified; show freshness/last update timestamp.
- Safe exports: tenant-scoped CSV and optionally PDF reports, access-controlled storage and expiring downloads, CSV formula-injection defense, row/size/time limits, async/cron-friendly large exports, no secret or sensitive raw events, audit exports. No public report path traversal.

### 12.3 Privacy and governance (DATA-03)

- Data inventory: what is collected, why, source, tenant/controller responsibility, retention, optional signals, legal basis/consent as applicable, who can access it, subprocessors/providers and deletion strategy. Do not assert legal compliance without review. Default to minimal data and no cross-site advertising/tracking.
- Configure retention tiers for raw challenge/token state (short TTL plus bounded operational grace), pseudonymous security events (e.g. 7/30/90 days by plan and legal constraints), rollups, audit and billing records. Explain defaults and legal retention exceptions. Provide scheduled bounded pruning, test deletion correctness, preserve necessary financial/audit records with minimization, and handle tenant closure/export/deletion without exposing other tenants. Backups have documented retention and expiry; do not claim immediate deletion from immutable backups.
- Redact logs and error monitoring; no secrets in telemetry. Apply encryption at rest where feasible, authenticated encryption for recoverable secrets, HTTPS in transit, strict file permissions and backups with encryption. Shared hosting cannot guarantee physical isolation or managed KMS; document threat model and enterprise alternatives. Access logs and private attachments must never be publicly served. Provide data export/delete and support privacy requests, and a privacy policy template that describes actual behavior.

---

## 13. DEVELOPER DOCUMENTATION, SDKs, CMS EXTENSIONS AND DOWNLOADS

This is a mandatory first-class product, not a README afterthought. Implement the public documentation site, runnable examples, real extension/SDK packages and the exact required root `downloads/` directory. Developers must be able to integrate from the site without reading the platform source code.

### 13.1 Public documentation architecture (DOC-01)

Use version-controlled Markdown or structured source files under `resources/docs/{version}/{locale}/`, rendered by a safe server-side Blade documentation site with searchable sidebar, categories, breadcrumbs, heading anchors, version selector, locale switcher, readable code highlighting, copy buttons, next/previous links, feedback link and mobile/RTL support. Build a local search index or efficient indexed DB search; no mandatory external docs platform. The public docs should be readable without login and link to actual release files. English complete; Persian complete for core onboarding, integration, API/error, security and CMS guides, with clear fallback for any advanced translation not yet verified. API parameter names, source code, commands and endpoint identifiers stay consistent across locales. Do not translate API field names. Support version-specific archived docs and visible version/deprecation labels.

Minimum table of contents:

1. Introduction, product capabilities, architecture/trust boundaries, differences among CAPTCHA, risk assessment, SMS, account, bot/API/fraud and proxy-based protection.
2. Quick Start: register, create site, verify domain, choose environment and action, obtain keys, embed widget, backend verification, run sandbox test and validate integration health. Complete copy-paste minimal frontend + backend pair in under 10 minutes as a target, not a promise.
3. Concepts: site key vs secret vs scoped management key; tenant and environments; challenge vs token vs assessment; one-time token semantics; TTL, replay, origin/domain/action, risk scores and decision handling; quotas/billing.
4. Widget: all supported modes, theme/language/size, appearance/branding, lifecycle, public JS API, callbacks, multi-widget and SPA examples, invisible/managed/interactive and accessible fallback, CSP/iframe/worker settings, performance and mobile behavior.
5. Backend Verification: HTTPS, authentication, expected action/hostname/site, safe timeout, error contract, no secret in frontend, no client-only verification, no blind retries, lost response, expiration, fail-closed or explicit low-risk degradation policy, server-side enforcement. Examples must actually check the response and prevent protected action when invalid, unavailable or denied according to documented policy.
6. API Reference: complete endpoint schemas, types, auth/scopes, response examples, HTTP statuses, reason codes, pagination, rate limits, versioning, idempotency, webhooks, OpenAPI download and optional safe sandbox playground. Generate/validate against real `openapi/v1.yaml`; no invented routes.
7. Server Integration by Language/Framework (see Section 13.2).
8. Frontend Integration by Framework (plain JS, TS, React, Vue, Next.js and other supported examples, with correct server boundary).
9. Mobile Integration: Android/Kotlin, iOS/Swift, Flutter; hosted/embedded supported verification flow, lifecycle/network handling, server verification and optional attestation limitations. Do not require unsafe arbitrary WebView bridges or ship server secrets in apps.
10. CMS Integrations: WordPress and WooCommerce, Drupal, Joomla, Magento/Adobe Commerce, OpenCart, PrestaShop, any other actually supported platform. Installation, site/secret storage, protected forms, compatibility, troubleshooting, uninstall/data retention, update and download checksums.
11. Product Guides: SMS Shield before provider send, account protection before auth action, anti-spam, API middleware, fraud review, scraper/AI agent policy, feedback and metrics. For each: prerequisites, API sample, enforcement point, privacy/minimized context, limitations and safe outage behavior.
12. Rules & Analytics: policy precedence, shadow mode, test and publish, score meaning/unknown, alerts, webhook verification and event deduplication, report definitions, avoid overblocking.
13. Sandbox and testing: test keys, deterministic outcomes, examples with success/failure/expiry/replay/context mismatch/timeout/quota/invalid secret, API health, integration health and local test setup.
14. Migration: real replacement instructions for customers using ARCaptcha, Google reCAPTCHA, hCaptcha and Turnstile; update widget, secrets, backend endpoint, expected context and error handling. No competitor token reuse or proprietary compatibility claims. Shadow/parallel evaluation only where safe, reversible rollout and removal of old integrations.
15. Security & Privacy: secret storage/rotation, domain verification, CSRF/CSP/CORS, safe proxy config, rate limits, bot signals, accessibility, privacy data inventory, tenant retention, responsible disclosure, customer responsibilities and incident contacts.
16. Troubleshooting: invalid key/site, origin/domain mismatch, wrong action, sandbox/production mix, token consumed/expired, missing backend verify, blocked script/CSP, widget network failures, unsupported browser, CMS plugin conflicts, mobile and RTL, 429/503, quota, duplicate submission, webhook signatures; request ID and safe support process.
17. Deployment and Self-Hosted: cPanel/DirectAdmin install, supported environment, cron, security, backup/restore, upgrade, limitations and migration to VPS. Operator-only sensitive material must not appear in public docs.
18. Downloads, SDKs, release notes, compatibility matrix, checksums, licensing, changelog, deprecations and known limitations.

Each page must have meaningful prose and actual examples, not placeholder headings. Add automated link checking, doc build/render tests, snippet extraction/validation or testable example files, and cross-check against API routes/response schema. Use versioned source and a clear maintenance workflow. Include docs for developers with low technical experience as well as API consumers. All code examples use `example.test`/configured local base URLs and placeholder keys; never publish real secrets or tenant data.

### 13.2 Required language and framework examples (DOC-02)

For each backend language provide at least one complete minimal runnable protected-route example (or minimal project with exact run instructions), configuration from environment, a client/widget or matching frontend example, secure HTTP request with bounded timeout, JSON parsing, explicit checks of successful verification and expected context/decision, failure handling, no raw secret/token logging, and a test with mock or local service. Include install dependencies, supported versions and exact invocation. Keep language-specific code idiomatic and secure. Frontend-only snippets must link directly to backend verification; server secrets must never appear in React/Vue/browser/mobile bundles.

| Integration | Minimum required documentation and example |
|---|---|
| HTML + Vanilla JS | Script/widget initialization, standard form and AJAX, multiple widgets, callbacks and token expiration/reset |
| TypeScript | Typed widget interface and event handling |
| cURL | Challenge/test API where appropriate and authenticated server verification/assessment with placeholders, error examples |
| PHP (plain) | cURL or vetted HTTP client, POST handler, timeouts, server-side verify, action checks |
| Laravel | Composer package or example middleware/service, config, FormRequest/controller, validation rule, Blade form, tests |
| Node.js | Server SDK usage and HTTP client with abort/timeout |
| Express.js | Middleware/protected POST route, explicit verify and policy handling |
| React | Hook/component, loading/cleanup/re-render/multiple widgets; backend example link |
| Vue 3 | Component/composable, lifecycle/cleanup and server integration |
| Next.js | Client component vs server route/action, environment variable boundaries, no `NEXT_PUBLIC` secret |
| Python | `requests` or supported HTTP client, timeout and safe failure |
| Flask | Minimal protected form/route |
| Django | View/form validation with CSRF and server-side check |
| FastAPI | Async or synchronous HTTP client, validated schema and dependency/middleware example |
| Go | `net/http` request/context timeout and response validation |
| Java / Spring Boot | HTTP client, controller/service and configuration, secure timeouts |
| C# / ASP.NET Core | `HttpClient`/typed client, controller or middleware and server settings |
| Ruby / Rails | Server-side HTTP integration, controller action and config |
| Rust | Minimal server-side verify using a maintained HTTP/JSON library; versioned sample |
| Android / Kotlin | Supported challenge UI/hosted flow, callback, lifecycle and send token to own backend |
| iOS / Swift | Supported challenge UI/hosted flow, callback, lifecycle and backend verification |
| Flutter / Dart | Cross-platform widget/hosted flow and server-side handoff |
| WordPress / WooCommerce | Actual plugin installation + configuration + supported hooks/forms + tests |
| Drupal / Joomla / Magento / OpenCart / PrestaShop | Supported installable integration, extension version and exact configuration/hooks, or explicitly identified blocked compatibility if native build/tests unavailable |

Provide examples for registration, login, form spam, SMS pre-send, API and fraud use cases as appropriate across representative server frameworks; not every language needs every business scenario, but all supported languages need real verification. Store source under `packages/examples/` and publish selected runnable projects as downloadable archives. When an ecosystem/toolchain is unavailable, do not label unexecuted sample as tested; add CI matrix and report the exact validation status and next command. Do not fabricate package registry names, version numbers or external publication.

### 13.3 SDK package engineering (SDK-01)

Implement source packages, typed API client, predictable error classes/results, cancellation/timeout support, safe authentication, scoped secret configuration, strict context validation, optional assessment helpers, supported version and real contract fixtures. SDKs must never execute a protected operation automatically or treat network errors as successful verification. Avoid unnecessary dependencies, logging tokens, hidden telemetry, and automatic unsafe retries. Keep a single contract source and generate consistent types where appropriate. Every package: README, license/NOTICE, CHANGELOG, semantic version, platform requirements, install/build/test commands, safe minimal example, unit/contract tests, release metadata, dependency/license audit. Distribute source plus actual installable artifact where ecosystem permits.

Required SDK/package coverage:

- Web JS/TypeScript standalone widget: self-contained versioned browser distribution (UMD/IIFE or ESM as designed), TypeScript declarations, local assets/worker, and npm-installable archive; no server secret functionality in browser package.
- PHP server SDK (Composer-compatible package/source archive) and Laravel integration (middleware/service provider/config/form validation helper/tests). Keep server credentials on server.
- Node.js server SDK, React component package, Vue component package and Next.js integration examples/package where appropriate. Build and test separately from core app; distinguish browser/server exports.
- Python server SDK (wheel + source distribution if tools available); Go module/source archive; Java library (Maven/Gradle compatible source and JAR when built); .NET client (NuGet `.nupkg`); Ruby gem; optionally Rust crate/source when implemented. Require runnable examples and contract tests, not just generated method stubs.
- Android Kotlin SDK (AAR or verified source package), iOS Swift package (source and supported distribution), Flutter package (Dart source/appropriate archive). Provide documented supported challenge flow; never embed verification secret in a mobile app. Do not pretend iOS artifacts were built on a platform without required tooling. Separate source release status from verified installable/binary release status.
- An OpenAPI/HTTP client kit with Postman collection, example environment, API schema, signed webhook verification example and migration examples. Include SDK support matrix in docs. Do not publish to npm, Packagist, PyPI, Maven Central, NuGet, WordPress.org or app stores without explicit authorization and credentials; provide local install commands for actual artifacts and optional publishing instructions.

Prioritize full PHP/Laravel, JS/TS and WordPress releases first, then other languages, wrappers and CMS. All named targets remain requirements; if a toolchain cannot be installed or tested, record an explicit blocked status with source, test/build instructions and no fake binary. Reuse the stable contract rather than writing ten inconsistent implementations.

### 13.4 CMS extensions (CMS-01 THROUGH CMS-06)

Build standalone native, installable packages in `packages/cms/{platform}/`. No separate runtime service beyond the core CAPTCHA platform. Use official CMS extension APIs and current supported platform versions, verify compatibility and document tested versions. Use platform-native permission, CSRF, escaping, secure HTTP client, secure configuration and uninstall conventions. Secret must stay server-side. Default policies must not lock out administrators unexpectedly; include safe recovery instructions requiring authorized server/admin access. Never let client-side validation alone protect a form. When a platform does not support server-side enforcement for a certain hosted feature, explain it and do not falsely advertise protection.

**WordPress + WooCommerce (CMS-01, mandatory first):**

- Real installable ZIP with valid plugin header, namespaced/prefixed code, admin settings, capability checks/nonces, internationalization/text domain, accessible JS, secure site/secret configuration (offer environment/constant override; use available platform secure storage guidance), test vs live mode, endpoint configuration, connection test, per-form activation, theme/language, migration and safe uninstall behavior. No secrets in `wp_localize_script`, DOM, REST responses or JS. Sanitize settings, escape output, no arbitrary external endpoints/SSRF, no insecure default keys.
- Protect supported native login, registration, password reset, comments and configurable forms using appropriate server-side hooks. Include WooCommerce supported login/registration/checkout/account flows, and explicitly test classic checkout versus Blocks/Store API where supported; if a flow cannot be protected securely, disable/label it instead of claiming coverage. Support major form plugin adapters (Contact Form 7, WPForms, Gravity Forms, Elementor Forms, etc.) only for verified supported hooks/licensed test environments; otherwise provide documented extension contract. Do not claim integration from injecting widget alone.
- Integrate expiration/reset, multi-form coexistence, cache compatibility, AJAX/REST where supported, multisite behavior with scoped settings, admin emergency recovery and outage policy by action. Provide shortcodes/blocks where useful but ensure backend enforcement for real protected actions. Audit plugin capabilities, dependency footprint, performance and compatibility. Include `readme.txt`/README, installation, uninstall, changelog, license notices, tests and `shield-captcha-wordpress-<version>.zip` with correct root folder structure, no secrets/dev junk.

**Drupal (CMS-02):** native module metadata, services/config schema, permissions, form integration/validation, configuration and settings security, uninstall/cleanup, localization, supported forms and tests; installable source/archive.

**Joomla (CMS-03):** native CAPTCHA/system extension where appropriate, valid manifest, admin config, server validation hooks, ACL/CSRF, localization, supported version and installable ZIP with tests.

**Magento / Adobe Commerce (CMS-04):** Composer-compatible module, configuration ACL, secure backend verification on supported customer forms/checkout actions, compatibility with cache and storefront architecture, localization, module registration, upgrade/uninstall instructions and artifact.

**OpenCart (CMS-05):** native extension package/manifest for selected major version, admin config and permission, theme/widget integration and server-side validation for selected supported forms, proper ZIP/install instructions and tests.

**PrestaShop (CMS-06):** native module, admin settings, hooks and backend validation, localization, compatibility matrix, cleanup and installable ZIP with tests.

Optional additional platforms (e.g. other regional CMS or hosted commerce platforms) only where official extension/verification capability exists. Each catalog entry must declare exact supported forms, supported versions, known limitations, source and test status. Never place an empty ZIP or generic README in downloads and label it as an installable extension.

### 13.5 Downloads directory and release pipeline (REL-01)

Create the literal repository-root directory **`downloads/`**. It is the canonical generated release output directory for SDKs, plugins/extensions, examples, API kits and self-hosted distributions. Keep package source under `packages/` and artifact outputs under `downloads/`. Never store live app `.env`, actual credentials, logs, test customer data, private signing keys, database dumps, payment config, development caches or unrelated executables in release archives.

Illustrative expected tree (actual filenames must reflect real version and built format; do not create empty/fake files just to match this tree):

```text
downloads/
  README.md                         # release layout and build instructions
  manifest.json                     # machine-readable catalog generated from real artifacts
  checksums/SHA256SUMS
  checksums/SHA256SUMS.sig            # optional real signature; never a fake signature
  sdk/
    javascript/shield-captcha-js-<version>.tgz
    php/shield-captcha-php-<version>.zip
    laravel/shield-captcha-laravel-<version>.zip
    node/shield-captcha-node-<version>.tgz
    react/shield-captcha-react-<version>.tgz
    vue/shield-captcha-vue-<version>.tgz
    python/shield_captcha-<version>-py3-none-any.whl
    go/shield-captcha-go-<version>.zip
    java/shield-captcha-java-<version>.jar
    dotnet/ShieldCaptcha.<version>.nupkg
    ruby/shield_captcha-<version>.gem
    android/shield-captcha-android-<version>.aar
    ios/shield-captcha-ios-<version>.zip
    flutter/shield-captcha-flutter-<version>.tar.gz
  cms/
    wordpress/shield-captcha-wordpress-<version>.zip
    drupal/shield-captcha-drupal-<version>.zip
    joomla/shield-captcha-joomla-<version>.zip
    magento/shield-captcha-magento-<version>.zip
    opencart/shield-captcha-opencart-<version>.zip
    prestashop/shield-captcha-prestashop-<version>.zip
  examples/
    shield-captcha-quickstarts-<version>.zip
    shield-captcha-openapi-<version>.yaml
    shield-captcha-postman-<version>.json
  self-hosted/
    shield-captcha-shared-host-<version>.zip
    shield-captcha-vps-profile-<version>.zip
```

Implement reproducible build commands (e.g. `scripts/build-releases.*` and per-package scripts), run unit/contract/package tests, validate archive contents/root paths and metadata, calculate real SHA-256 checksums, generate catalog/manifest from artifacts that actually exist, scan for secrets, include license notices/SBOM where appropriate, and provide documented optional release signing using operator-provided key outside repository. Never execute arbitrary uploaded code or trust a client-supplied file path. CI should produce artifacts even when optional ecosystems need separate runners. Do not claim byte-for-byte reproducibility unless proven; otherwise document deterministic input versions and known nondeterminism.

`manifest.json` schema must include artifact ID, product/package, ecosystem, category, artifact type (source/binary), version, API protocol compatibility, supported platform versions, relative file path, filename, byte size, SHA-256, release timestamp, license, release notes/docs URL, visibility/entitlement, status (`verified`, `built-unverified`, `source-only`, `blocked`, `yanked`), and signature metadata when available. A missing/blocked artifact is represented as status metadata, not a fabricated checksum or download path. Validate schema and verify each downloadable file against manifest.

### 13.6 Download center and security (REL-02)

- `/downloads` public catalog, filter by category/ecosystem/version/platform, latest stable, changelog, requirements, installation command, checksum and verification instructions, language-specific docs link, honest build/test status. Customer panel includes tenant-relevant download links and selected-site setup guidance. Operator panel manages visibility/release notes/version metadata only through validated manifest/release workflow. Provide deprecated/yanked warnings; no yanked artifact as latest.
- Actual file delivery through a controlled route or explicitly allowlisted public copy of verified public artifacts. Resolve artifact IDs to manifest records, verify normalized real path remains within `downloads/`, reject traversal/symlinks and hidden/internal files, check access/entitlement for private packages, stream bounded files or use safe server-supported acceleration, set correct content type/disposition, rate limits and cache policy. Do not expose repository directories, app source, all files through `storage:link`, arbitrary uploads or private enterprise packages. Allow resumable/CDN delivery where supported and safe. Public docs/links must point to actual files with correct hashes.
- Add package-install smoke tests: unzip plugin in disposable CMS, verify metadata/hooks/config, run protected-form test, install SDK from built archive and call local contract fixtures. Compare declared compatibility with tested platform matrix. Build optional binary artifacts on the correct toolchain (e.g. Android/iOS) in CI; report inability honestly. Deliver actual WordPress and core SDK archives, not just package source, if local toolchain permits. Use a release gate to hide unverified packages from default stable listing or display explicit experimental status.
- Self-hosted edition: package core application with deploy instructions, required dependency/asset builds, `.env.example`, migrations, safe CLI setup, license/NOTICE, checksums, upgrade/backup guide, and optional enterprise profile prerequisites. Use install-time unique secrets, no production credentials or seeded backdoors. Clearly distinguish a source distribution from a ready-to-upload hosting distribution containing vendor/build assets. Provide operator license/entitlement mechanism only if explicitly required; no mandatory remote activation preventing offline core operation, no obfuscated backdoor.

---
## 14. SECURITY ENGINEERING, THREAT MODEL AND DEFENSIVE CONTROLS

Treat this product as a high-value security boundary. Implement a threat model before exposing public endpoints. Document assets, data flows, trust boundaries, actors, likely abuse, mitigations, residual risks and evidence. Review at least OWASP ASVS Level 2 as an engineering target (not a certification), OWASP API Security, Automated Threats and relevant cheat sheets. Record which controls have been verified and which require external review. Independent professional security review is a launch gate for commercial production; coding-agent tests are not a substitute.

### 14.1 Required security properties (SEC-01)

- **Isolation:** deny by default, tenant scoping across HTTP/Filament/Livewire/jobs/cache/files/exports/analytics/API, platform roles separate from tenant roles. Policies and backend authorization for every sensitive operation, including direct URLs, object IDs and non-UI API routes. No mass-assignment privilege escalation. Test user/tenant/operator role matrix.
- **Cryptography:** standard maintained primitives/libraries, secure random generation, purpose-separated keys, signed/expiring provider payloads, key rotation, hashed opaque tokens, constant-time verification where relevant, no custom cryptographic schemes, no secrets in client bundles. Document rotation and recovery of platform keys without exposing historical data or invalidating data unexpectedly.
- **Replay/TOCTOU:** atomic challenge/token consumption and quota enforcement using real InnoDB transactions/unique constraints; concurrent verification, rotation, deletion, suspension, rate-limit and payment callback races. Never use check-then-update without atomicity for security state. Test under actual concurrency. Ensure one token cannot be reused for a different protected action, tenant or environment.
- **Request defenses:** validated JSON/schema, size/depth/rate/time limits, SQL injection prevention, safe output encoding, XSS prevention, CSRF, restrictive CORS, CSP, clickjacking protections compatible with intentional widget embedding, correct `frame-ancestors`, HSTS when TLS/deployment supports it, appropriate cookie flags, secure session management, safe file upload/download, untrusted redirects and SSRF protections. For widget routes, carefully isolate required cross-origin framing rather than globally weakening headers. Explicit trusted host/proxy configuration and header provenance.
- **Abuse controls:** cheap edge/application checks before expensive challenge generation/verification; per-site, credential, action, pseudonymous destination/account/session, and global rate limits; max outstanding challenges; bounded retries and request body/asset limits; fair resource isolation; graceful overload responses. Do not let a forged site key consume another tenant's entire billable quota through challenge requests alone. Separate unauthenticated challenge issuance budgets from verified billable usage. Document abuse reporting, account suspension, emergency revocation and throttling. Shared-host app controls cannot stop saturation of network, web-server connection capacity or host CPU before PHP; document provider protections and upgrade trigger.
- **Resilient integration:** customer decides policy for timeouts, outages and unknown risk; default deny for high-risk login/recovery/OTP/financial operations unless customer explicitly implements a safer fallback such as independent MFA/manual review. For selected low-risk forms only, allow explicit documented degradation with independent rate controls and monitoring; never disguise fallback as successful verification. Provide integration timeout guidance, no blind non-idempotent retries, and recovery path.
- **Supply chain:** lock dependencies, review vulnerabilities/licenses, audit Composer and npm, pin CI actions where practical, verify official package sources, no unreviewed install scripts, no unsafe eval or dynamic code execution. Keep secret scanning, package checksums, release provenance/SBOM and signed releases when key management exists. Establish security update and dependency deprecation workflow. Never misrepresent third-party license status or bundle proprietary enterprise features as open-source.
- **Operator controls:** MFA, session revocation, least privilege, audit, secure provisioning, re-auth sensitive operations, approvals/reasons where appropriate, emergency disable, no hard-coded admin user or production seeding. Disable debug and development tools in production. Redact error responses/logging, keep environment secrets outside public root, permissions and secure backups.
- **Privacy/ethical boundaries:** defensive-only product; no third-party CAPTCHA solving, credential attack, bot evasion, scraping or identity surveillance tool. No invasive cross-site fingerprinting by default, no raw private form contents, no secrets/OTP/card data, no profiling based on protected personal traits. Avoid discriminatory denial and support legitimate users, VPN users, assistive tools and verified automation. Use only owned/authorized test fixtures, synthetic data and limited local security test traffic.

### 14.2 Risk evaluation and evidence (SEC-02)

Create an authorized test harness for challenge lifecycle, policy correctness, accessibility, legitimate traffic, synthetic automated clients and abuse patterns. Do not include real-world bypass tooling or claims of detection efficacy based solely on synthetic tests. Test protocol invariants and failure paths systematically. Design a pilot evaluation with labeled consented data, separate training/tuning/holdout sets where applicable, representative mobile/desktop/network conditions, and customer-authorized A/B or shadow comparisons.

Metrics: true positives/false positives/false negatives/true negatives with labels and denominators, detection vs false rejection trade-off, interactive challenge abandonment/completion, unknown outcomes, latency, error rates, false positives by benign scenarios, customer-side enforcement and outage behavior. Use safe confidence estimates and disclose sample size/limits. Compare competitors only through their publicly permitted interfaces on an owned test website with equivalent integration and authorized terms; no unauthorized testing or claims about competitor private internals.

Engineering targets (not guarantees): for a measured appropriately sized deployment, 99.99% service availability as a future SLA goal, API p95 below 150 ms in a defined region/load scenario, >98% no-interaction completion for labeled legitimate traffic where feasible, <0.5% false rejection and >99% detection on a clearly defined labeled test set as aspirational targets, integration quick start under 10 minutes, WCAG 2.2 AA design/test target. Shared hosting may not meet these; state measured values and test conditions, not invented benchmarks. Never optimize detection at the expense of unacceptable false positives or access barriers. Do not release a feature solely because it meets a synthetic benchmark.

### 14.3 Required security documents

- `SECURITY_MODEL.md`: architecture/trust boundaries, challenge and token protocol, credential/key lifecycle, policy and tenant invariants, threat scenarios and residual risks, privacy model, supported deployment profiles.
- `SECURITY.md`: supported versions, private reporting process and configurable real contact, vulnerability triage/patch/release workflow, no bug bounty promises unless authorized.
- `PRIVACY_DATA_INVENTORY.md`: signal inventory, classification, lawful purpose/consent review, retention, encryption, providers and deletion.
- `INCIDENT_RUNBOOK.md`: API outage, suspected leaked key, abuse spike, cross-tenant incident, billing incident, data leak, restore/rollback, customer communication, evidence preservation and responsible disclosure. Do not include real secrets or private attacker-enabling detection internals in public documents.

---

## 15. PERFORMANCE, RELIABILITY AND OPERATIONS (OPS-01 THROUGH OPS-05)

### 15.1 Hot path and resource budget

- Keep verification and token consumption synchronous, bounded, indexed and transactionally safe. Separate expensive reports, emails, export, cleanup, webhook delivery and rollups via cron/jobs. Cache immutable/static provider assets, sanitized public widget configuration with safe invalidation, and tenant-scoped aggregates; never cache credentials, private risk results or token success as reusable authorization.
- Implement bounded query/page sizes, payload sizes, timeouts, maximum challenge complexity, quotas and cleanup batches. Measure memory per request, SQL count, row contention, slow queries, p95 latency and error rate. Avoid N+1 in Filament; use pagination and aggregate queries; avoid eager-loading unbounded relationships. Use signed/immutable assets with content hashes, compression where supported, minimized widget bundle, no mandatory external font/CDN.
- Load-test scenarios with authorized local/staging systems: challenge issuance/solve/verify, mixed products, token race, bursts, quota contention, dashboard aggregation, cron overlap and provider outage. Record hardware, PHP version/workers, DB, test volume, duration, response times, throughput, failures and memory/CPU. Do not invent maximum RPS or assume a shared host offers dedicated resources. Verify provider permission before any load/security testing on a hosting account. Establish actual capacity and a safe pilot limit from measurements.

### 15.2 Scheduled jobs and background processing

Implement bounded, idempotent Artisan commands and Laravel scheduled jobs for expired challenge/token cleanup, counter cleanup, analytics aggregation, quota reset/renewal, invoice generation, pending reconciliation, alerts/webhook delivery, release-manifest verification, privacy retention, operational health/heartbeat, backup status and stuck job recovery. Use durable lease/lock semantics appropriate to shared MySQL, avoid overlap, bounded runtime per cron tick, retry/backoff with dead-letter/failed jobs, and safe re-run after timeout. No per-request heavy cleanup or endless loops. Document job dependencies and recovery if cron stops. Ensure core verification still works when noncritical jobs are delayed; prevent expired data from becoming valid due to missing cleanup.

### 15.3 Health, monitoring and alerting

- Minimal `/health/live` and `/health/ready` responses, Laravel health route compatibility, safe readiness checks for DB, required configuration, schema, filesystem, key material and cron lag; do not expose versions, stack traces, paths, DB details, secrets or sensitive tenant information publicly. Restrict detailed diagnostics to authorized operators.
- Structured redacted logs with request correlation IDs, exception handling, log rotation/retention, metrics for request volume, latency, errors, challenge/verify outcomes, usage quotas, DB contention, queue/backlog, disk, CPU/RAM where available, cron heartbeat, backup age and provider failures. Shared hosting may not expose system metrics; show unknown rather than fabricating. Support local structured logs/DB aggregates; optional metrics exporter or external monitoring without mandatory dependency.
- Configure external uptime/latency monitoring and external incident/status page for production if possible. In-app status page reflects measured availability, not an assumed 100%. Provide alert thresholds, notification throttling/dedup, incident owner, severity and response procedure. Protect own API with provider-level DDoS/network service when feasible and accessible; do not depend on it for correctness.
- Define service degradation, overload rejection, planned maintenance and emergency policy. Include health and readiness in deployment gates. Never allow maintenance mode to unexpectedly expose a verification bypass. A customer whose CAPTCHA service is down needs documented safe behavior; consider a dedicated endpoint/status path and explicit retry policy.

### 15.4 Backups, recovery, upgrades

- Backup MySQL, protected storage, required configuration (secrets separately and securely), release manifests and package artifacts according to a documented policy; encrypted off-host backups where possible, retention and access checks. Do not keep publicly accessible `.sql`, `.zip`, backup files or `.env` under document root. Do not embed backup credentials in repo. On constrained shared hosting provide provider backup plus operator-controlled export/import procedure and an explicit restore test using a nonproduction database.
- Document recovery point/recovery time objectives as operator-configurable targets. Test restore to clean environment, schema integrity, key recovery, tenant isolation, release artifacts, background job resume and smoke verification. Record evidence. Account for old backup retention/privacy requests. Do not promise failover without separate infrastructure.
- Safe deploys: maintenance/compatibility plan, preflight, backup, additive migrations, compiled immutable assets, config/route/view cache where compatible, migration verification, queue/cron compatibility, smoke tests, rollback procedure and known irreversible migration steps. No unapproved destructive data changes. Use deployment version and changelog. Optional VPS profile provides rolling deployment/worker reload instructions.

### 15.5 Hosting migration triggers

Define pilot acceptance from real performance measurements. Move to stronger shared hosting or VPS when sustained worker/CPU/IO/DB limits, queue lag, lock contention, response latency, availability or abuse volume exceed safe operating margins. Provide a migration checklist: export/restore, secrets, DNS/TLS, asset base URL, database/counter correctness, downtime and token TTL handling, Redis transition, queue drain, smoke tests, monitoring and rollback. Preserve API/widget compatibility and public downloads. Scale optional edge/full-site protection separately; do not require that to upgrade a basic CAPTCHA deployment.

---

## 16. DEPLOYMENT ON DIRECTADMIN, CPANEL AND VPS

Create a practical `DEPLOYMENT.md`, per-profile guides under `infra/`, a preflight command and a reproducible release artifact. Use actual tested commands adjusted to installed versions. Support the cheapest safe deployable environment; do not require Docker, root or a resident worker in the shared profile. Provide both SSH/CLI and constrained upload/control-panel workflows where the provider permits safe CLI setup via scheduled command or supported interface. If a provider prevents secure document root, migrations, cron or required extensions, declare that host incompatible rather than deploying insecurely.

### 16.1 Environment and preflight (DEP-01)

- `.env.example` contains placeholders only: `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY` generated locally, `APP_LOCALE`, supported locales, database settings, cache/session/queue drivers, trusted hosts/proxies, widget/API/docs base URLs, CAPTCHA signing/key configuration, deployment profile, email, payment, storage, retention, quotas, risk defaults, optional Redis/reputation/edge/SSO, artifact directory, monitoring and feature flags. Align exact variable names with implemented config; never include real values.
- A `php artisan shield:preflight` (or actual documented equivalent) verifies PHP/version/extensions, Composer platform, database driver/version/InnoDB, schema readiness, DB privileges needed for install, storage/bootstrap cache permissions, HTTPS/base URLs, secrets generation/readiness, debug disabled in production, document-root safety where detectable, config consistency, cron requirements, optional provider readiness and artifact checks. Print sanitized actionable results and nonzero exit on mandatory failure. Do not reveal credentials. Hosting environment restrictions must be clearly identified.
- Minimum host checklist: PHP 8.3+, Composer-compatible build, MySQL/MariaDB InnoDB, HTTPS/TLS, safe `public/` mapping, cron (preferred every minute or supported interval documented), PHP CLI availability for install/upgrade, writable private storage, outbound HTTPS only for configured integrations, sufficient CPU/RAM/I/O/connections and provider approval for API traffic. Node build runs off-host; vendor and compiled assets included when deploying prebuilt artifact. No `.env`/vendor/storage access from web.

### 16.2 cPanel guide (DEP-02)

Document a real click-by-click and CLI guide, accounting for host-specific UI differences:

1. Add domain/subdomain in Domains; map document root to `<app-directory>/public` if host permits. Otherwise use a safe split where private app files live outside `public_html` and the public index/autoloader paths are deliberately adapted; verify no private files served. Do not suggest simply uploading the whole Laravel root to `public_html` or relying on `.htaccess` alone to conceal secrets.
2. Create database and least-privilege user via MySQL Database Wizard, configure charset/timezone and migration privileges as required. Select supported PHP and extensions in MultiPHP/host selector; verify PHP CLI matches web PHP. Set memory/time constraints within provider limits.
3. Upload verified ready-to-deploy artifact through File Manager/SFTP or Git deployment; extract to private directory; create `.env` securely and set permissions. Generate unique application/signing keys using trusted local/CLI setup; do not ship default secrets. Configure URLs, TLS/AutoSSL, mail and optional providers. Configure only required outbound integrations.
4. Run safe installation commands in nonproduction first: `composer install --no-dev --optimize-autoloader` if Composer is available and dependencies not prebundled, `php artisan key:generate` only for first install, application-specific secure setup, `php artisan migrate --force` only after explicit operator approval/backup for production, storage/public asset setup with narrow exposure, `php artisan optimize` or compatible cache commands. Use actual Filament 5 deployment/asset commands where required. Do not regenerate `APP_KEY` on upgrades. Run preflight and bootstrap first operator interactively or secure CLI; no public setup backdoor.
5. Add Cron Jobs entry using absolute PHP CLI path and app path; example pattern `* * * * * /path/to/php /home/ACCOUNT/app/artisan schedule:run` with controlled private logging appropriate to provider. Verify actual path/interval, heartbeat and non-overlap, and document fallback interval if provider restricts every-minute cron. No persistent worker required. Do not leave verbose cron logs or secrets in public folders.
6. Verify public site, customer and operator panels, real sandbox widget on separate owned test origin, server verification, downloads, docs, HTTPS, private path denial, health, cron, backup and rollback. Document resource limits and how to inspect provider error/resource usage. Never claim all cPanel hosts support identical features.

### 16.3 DirectAdmin guide (DEP-03)

Provide analogous panel-specific steps for Domain Setup/Subdomain Management, SSL Certificates (Let's Encrypt if supported), PHP Version Selector, MySQL Management, File Manager/SFTP, Cron Jobs and resource usage. Explain host-specific paths and permissions; do not assume exact DirectAdmin layout. Securely map public root or use verified split public/private deployment. Document same install, migrate, compile-off-host, key generation, first admin, scheduler and smoke commands. Explain Apache/LiteSpeed rewrite handling or host support requirements. Verify that forbidden sensitive paths are inaccessible. No requirement to purchase a DirectAdmin or cPanel license separately when using a provider's shared hosting account.

### 16.4 Local development, CI and VPS (DEP-04)

- README with exact local setup for supported PHP, Composer, MySQL/MariaDB, Node build-time, migrations and seed/test data. Provide optional local Docker Compose only as developer convenience, never required for production. Use dedicated test database, `.env.testing`, synthetic test seeds and no real credentials. A deterministic demo seed is allowed only in explicitly local/demo environment.
- VPS profile with standard PHP-FPM/Apache/Nginx, TLS, Redis optional, queue worker supervision, proper file ownership, database backups, health and monitoring. Optional edge profile with actual authorized upstream config, no open proxy. Optional enterprise/self-hosted guide and security/recovery plan. Provide deployment cost drivers and capacity estimates labeled as assumptions, not current market prices or guarantees.
- CI runs PHP lint/style/static analysis, Laravel unit/feature/MySQL tests, Filament tests, widget build/typecheck, browser tests, OpenAPI/docs validation, SDK/CMS matrix where feasible, security dependency/secret scans, and release package checks. Include build artifacts and reports. Do not deploy production or publish packages automatically without explicit configured authorization. Use minimal CI permissions and masked secrets; run untrusted contributions without privileged production credentials.

---

## 17. TEST PLAN, ACCEPTANCE CRITERIA AND RELEASE GATES

Do not treat implementation as complete unless supported by tests and observable evidence. Add fixtures, factories, deterministic clocks/randomness in test contexts, and appropriate mocks for external providers. Production secure randomness must not be overridden by test configuration. Test core protocol against real MySQL/MariaDB, not only in-memory SQLite. Execute what the environment supports; list skipped suites and reasons explicitly.

### 17.1 Required test families (QA-01)

| Test ID | Test family | Mandatory coverage |
|---|---|---|
| QA-UNIT | PHP/JS unit | Challenge provider contract, TTL, digest/signature verification, rule evaluator, risk normalization, policy precedence, quota units, validation, localization and widget state transitions. |
| QA-API | API feature/contract | Real endpoints matching OpenAPI; valid/invalid credentials, public/private split, site/action/domain/env, auth scopes, expiry, revoked keys/site, malformed and bounded requests, CORS, retry/error codes, sandbox isolation, no secrets in output. |
| QA-REPLAY | MySQL concurrency | Same challenge cannot mint multiple usable tokens; simultaneous token verification permits at most one successful consume; concurrent rate limits/quotas and feedback/payment events are consistent; race with revocation and site suspension. |
| QA-TENANT | Authorization/isolation | Two tenants and multiple roles; direct object IDs, Filament resources/actions/search, API, cache, jobs, relation managers, export/download, webhook, metrics and billing; no cross-tenant leak or privilege escalation. |
| QA-POLICY | Risk and product tests | Deterministic rules, shadow cannot bypass mandatory controls, bounded complexity, allowed automation, unknown context, SMS/OTP assessment, account/API/bot/fraud/scraper/agent baseline, feedback and actual enforcement examples. Benign shared-IP/VPN/assistive-device fixtures. |
| QA-WIDGET | Browser/E2E | Real local service, cross-origin owned test page, full challenge->token->backend verify->protected operation, checkbox/PoW/invisible/adaptive and enabled interactive/audio providers, multiple instances, SPA cleanup, keyboard/screen reader states, RTL, mobile, dark mode, expiry, errors, CSP and offline/slow network. |
| QA-FILAMENT | UI/Filament | Auth, email verification/MFA, tenant switch, onboarding, sites/domains/keys, save/rotate/revoke, policies/preview, analytics, billing, team roles, alerts, support, admin operations, feature gates and real navigation; permission tests for actions. |
| QA-BILL | Billing | Plan publication/entitlements, quota resets, no duplicate meter on retries, sandbox not charged, integer amount/currency/toman conversion, subscription transitions, payment callback verification, idempotency, mismatch, refunds/credits where supported, operator overrides and audit. |
| QA-DATA | Data/retention | Indexed migrations, deletion/retention, no raw secret/OTP/token logs, rollup idempotency, correct tenant metrics/denominators, safe CSV, privacy export/deletion, backup/restore. |
| QA-SEC | Authorized security | Threat-model-based negative cases, auth/authorization, request limits, safe file downloads, SSRF protections for webhook/domain checks, XSS/CSRF/CSP, injection prevention, secrets scans, dependency review and production config. Use safe local fixtures only. |
| QA-DOC | Docs/contracts | Actual routes and field names match OpenAPI; snippets and language examples build/run where available; no real secrets; internal link/version/download/hash checks; Persian and English core content; quick start from clean project. |
| QA-SDK | SDK/package | Install each built package from artifact, contract happy/negative paths, timeouts/invalid context, no unsafe retries/logging, semantic versions, dependencies and platform support; report unsupported toolchains. |
| QA-CMS | CMS | Install actual plugin/module in disposable supported version, configure site/secret, valid backend enforcement, deny invalid/replayed/unavailable according to policy, form compatibility, admin rights, cache/AJAX, uninstall and recovery; test WordPress/WooCommerce priority first. |
| QA-LOAD | Load/reliability | Defined environment, local authorized volume, p50/p95/p99/error rate/throughput, burst throttling, resource constraints, cron overlap/delay, recovery and profiling; no production or third-party stress tests. |
| QA-DEPLOY | Installation/ops | Clean shared-profile install without Node/Redis/workers at runtime, preflight, migration, first admin, config, docs/downloads, health, cron, private path denial, backup and restore, upgrade/rollback smoke. Actual control-panel host test only if provided. |
| QA-A11Y | Accessibility | Keyboard, focus, screen-reader status, contrast, reduced motion, nonvisual/non-audio alternative, RTL, mobile, zoom, error recovery and manual review of relevant WCAG 2.2 AA criteria. Do not claim formal compliance from automated tests alone. |

Add exact test commands and reports. Use Pest/PHPUnit, Filament 5 official testing utilities, Playwright browser tests, TypeScript/JS tests, k6 or equivalent controlled load tests, local OWASP ZAP/passive checks where appropriate, and ecosystem-native package tests. Use a separate test DB and prevent accidental production connections. Where a security or load tool is unavailable, record the limitation and provide authorized local execution steps. Never run tests against competitor services.

### 17.2 End-to-end release acceptance (QA-02)

A core shared-profile pilot is acceptable only when all of these are demonstrated with evidence:

1. Fresh install in a PHP/MySQL environment; production build assets served without running Node or persistent queue workers. Preflight passes mandatory checks.
2. Register a customer, verify email in local/test transport, create organization/site, configure owned test domain/action, receive public and private keys; private key shown once and never leaked afterward.
3. Onboard a separate owned test website using the actual JS widget or built WordPress plugin; get challenge, solve/assess in sandbox and normal local production-like context, submit form and verify token on customer backend. Protected operation occurs only on accepted response and correct context.
4. Reject an invalid, expired, already consumed, wrong-action, wrong-domain, wrong-site, wrong-environment or revoked token; simultaneous consume attempts have at most one success. Test negative paths explicitly. No hidden magic token or public always-pass production path.
5. Demonstrate actual initial risk policy and SMS pre-send assessment with mocked customer messaging provider and customer enforcement; legitimate and suspicious fixtures produce expected test outcomes without real messages. Show policy version and quota metering.
6. Show customer/administrator Filament panels, real filtered analytics, policies, events, usage, permissions and tenant isolation. No fake data in normal production environment. Show test-mode data only where clearly labeled.
7. Real API reference, versioned public documentation, complete minimal quick starts, error handling and downloadable OpenAPI/Postman examples. Docs link to functioning local API and actual artifact IDs.
8. Root `downloads/` exists, manifest validates, at least JS/PHP and WordPress packages are built and inspected/installed/tested if required tools exist; hashes match, unauthorized downloads and traversal rejected; other packages have honest release/test status and CI/build instructions.
9. Security model, privacy inventory, incident/backup runbooks, DirectAdmin/cPanel instructions, preflight, cron cleanup and restore test are delivered. Verify no private files exposed and no secrets committed.
10. All core automated tests pass or remaining failures/blockers are explicitly reported as preventing release. No claims of production readiness, security superiority or supported integrations based on stubs/unrun tests.

### 17.3 Advanced feature acceptance (QA-03)

For each P1–P3 requirement, provide a real demonstrated workflow, API/implementation files, configuration, tests, docs and actual readiness state. For example:

- Adaptive challenge selects verified provider or step-up using a published policy; provider failures do not become allow.
- Each enabled challenge type has server verification, expiry, replay prevention and an accessible recovery path.
- Rules/shadow, feedback, bot/AI automation identification, account/API/fraud/scraper products and tenant integrations use real shared services and customer enforcement; labels accurately indicate observed vs enforced.
- Alerts/webhooks have real signed delivery with retry/idempotency test; no fabricated external delivery.
- Billing either uses tested manual invoicing or genuinely configured/verified gateway; no sandbox payment in production.
- Every advertised SDK/CMS extension has supported versions, docs, source, installable artifacts where toolchain permits and verified tests; unsupported items clearly marked.
- Self-hosted package installs from clean artifact; optional VPS/edge/SSO/ML features must be tested in their required environment or clearly unavailable. Core remains usable without them.
- Enterprise features, retention, white-label and team roles enforce entitlements and permissions in backend, not only UI.

Run an independent authorized security review and capacity/load validation before opening commercial production, and complete legal/privacy review, support/incident process, monitoring and backup verification. A code-complete pilot is not the same as a production service with a validated SLA.

---

## 18. IMPLEMENTATION ORDER AND EXECUTION WORKFLOW

Do not try to generate every file in one unreviewed batch. Execute complete vertical slices, keeping application runnable at each milestone. The following order is a dependency plan, not a time guarantee. All feature IDs are mandatory backlog items unless explicitly documented as blocked by infrastructure, credentials, license or toolchain.

### Milestone 0 — Repository discovery and technical design

- Inspect workspace and tools; create a short inventory and note existing changes. Resolve real framework/package compatibility. Create architecture ADRs, threat model, deployment profiles, feature/requirement matrix, initial data model, API contract and test strategy. Choose brand/configuration defaults. Do not stop at planning.
- Initialize or integrate Laravel/Filament 5 safely, configure build/test database, two panels, CI/test scripts, localized scaffolding, preflight and initial migrations. Run initial smoke tests.

### Milestone 1 — Secure identity and a working CAPTCHA vertical slice

- Identity, tenancy, roles, sites/domain checks, credentials, environments and policies; first real Filament onboarding/resource pages.
- Challenge provider abstraction, audited PoW/checkbox provider, server-issued challenge, widget, token issuance, atomic verification and quota/rate controls. Build real test consumer page/backend; run valid and negative protocol tests including MySQL concurrency. Add OpenAPI and initial quick starts. Do not move to advanced features if core security invariants fail.

### Milestone 2 — Competitive risk engine and primary products

- Rule-based risk/policy engine, configurable action templates, adaptive/invisible mode, shadow and feedback; SMS Shield, Account Shield and API Shield baseline, integration health, customer/admin event views and metrics.
- Interactive image/puzzle and accessible audio/nonvisual recovery, enabled only after implementation and tests; performance and privacy checks. Implement settings/designer and actual demo/playground.

### Milestone 3 — Commercial SaaS and broader protective modules

- Plans/entitlements/subscriptions/usage/billing and real manual payment path; configured gateway adapter only if supported. Alerts/webhooks, report/export, localization, privacy, support, admin operations and security auditing.
- Bot/anti-spam, Fraud, Scraper and AI agent management with actual customer-side integration and clear limits; enterprise teams, custom policies, white-label, optional verified automation/reputation and self-hosted baseline.
- Complete public marketing, product/solution pages, pricing, status, help, legal templates, security and developer navigation; ensure feature status is honest.

### Milestone 4 — Developer platform and distribution

- Complete versioned public documentation and language/framework examples, schema validation, Postman/OpenAPI, migration guide and sandbox. Build first real JS/TypeScript, PHP/Laravel and WordPress/WooCommerce packages; install/test them from archives.
- Expand SDK and CMS implementations across required ecosystems and produce real artifact releases in `downloads/`. Verify manifest, hashes, public catalog, private entitlement, package compatibility and CI matrix; mark blockers precisely. No fake downloads.

### Milestone 5 — Deployment and hardening

- Complete shared-host preflight/install artifact, DirectAdmin and cPanel guides, cron, backups, restore, upgrade and operational monitoring. Test clean install in available PHP/MySQL profile and record exact constraints. Provide VPS, edge and enterprise optional profiles/adapters with integration tests and readiness gates; do not block core on them.
- Execute full test suite, negative protocol/tenant tests, package tests, authorized load/security tests, accessibility review and docs link checks. Fix defects and rerun. Provide pilot readiness review and specific independent security/operational review requirements before commercial launch.

### Milestone 6 — Evidence and release handoff

- Produce final working tree and actual artifacts, not just pasted code. Deliver `README.md`, `DEPLOYMENT.md`, `IMPLEMENTATION_STATUS.md`, `REQUIREMENTS_TRACEABILITY.md`, `DECISIONS.md`, `SECURITY_MODEL.md`, security/privacy/incident docs, OpenAPI, complete documentation site, source packages, `downloads/` and release manifest, test evidence and changelog.
- Summarize changed files and commands run, tests passed/failed/skipped, environment/version, limitations, setup URLs/routes, installation steps, artifact inventory/hash status, feature readiness, and next concrete steps for any blocked work. Do not label a feature as implemented if only design or a placeholder exists. Do not claim a CI run, physical hosting test, CMS install or security audit that did not occur.

---

## 19. REQUIRED FINAL DELIVERABLES AND REPORT FORMAT

When reporting, use the following sections. Provide exact repository paths and real results, not generic assurances.

1. **Executive implementation summary:** what is functioning, supported deployment profiles and current readiness.
2. **Architecture and key decisions:** stack versions resolved, module boundaries, shared-host constraints, risk model and key security trade-offs.
3. **Feature traceability:** every requirement ID from this prompt, status, implementation files/routes, test identifiers and known limitations. Include all public website, Filament, product, docs, SDK, CMS, billing, security and deployment modules.
4. **Local setup:** precise commands to install/run the application and dependencies, database setup, migrations, create operator, build assets and run tests. Use placeholder values; no actual secret output.
5. **Shared-host deployment:** exact guide paths, prerequisites, ready-to-upload artifact location, cron, public root, health and backup/recovery; identify any actual incompatibility or missing hosting access.
6. **Usage demo:** actual public site/panel/docs/playground/download routes, create site/test keys, copy integration code and demonstrate backend token verification. Explain that sandbox examples cannot authorize production.
7. **Downloads inventory:** every existing verified/built/source-only/blocked SDK/CMS/example/self-hosted artifact, file path, version, SHA-256 if file exists, installation method and test status. Confirm root `downloads/` and manifest validity.
8. **Documentation coverage:** languages/frameworks, CMS, API reference, versioning, migration, troubleshooting and privacy/security docs. Identify unverified examples explicitly.
9. **Test/security evidence:** commands, output summary, counts, concurrency tests, real DB/browser/package test status, security observations, measured performance with environment, accessibility checks, remaining issues and external review requirements.
10. **Remaining blockers and launch checklist:** clear P0/P1/P2/P3 statuses, missing credentials/toolchains/license/hosting access, real operational work still required, and exact next commands/actions. Separate implemented vs verified vs commercial-ready. Never silently drop requirements.

### 19.1 Non-negotiable definition of done

- Working code in the repository, installed compatible dependencies and reproducible lockfiles/builds.
- A real secure multi-tenant challenge-to-token-to-backend-verification flow; one-time tokens and tenant isolation tested against a real relational database.
- Working customer and operator Filament 5 panels, public product site, onboarding, key management, risk policy, primary protective products, quotas, documentation and release system.
- Actual public developer documentation with tested or explicitly identified untested multilingual code examples and integration instructions. Do not omit backend verification from any integration.
- Literal root `downloads/` directory, real built artifacts and validated manifest/checksums; no fictional downloads or unsupported package claims.
- No mandatory root, Redis, Docker, Node runtime, Python runtime, GPU, ML service or paid API for shared-host core. Optional profiles cannot break shared default.
- Clear tests and evidence, honest limitations, security/privacy review documentation, deployability/restore guidance and no secrets in source, logs, examples or archives.
- Feature flags and documentation accurately reflect readiness; no unsupported feature appears as enabled or complete. An incomplete feature must not be mistaken for a secure one.

### 19.2 Initial command to the agent

Start now by inspecting the current repository and environment. Create the requirements traceability and implementation status files, verify Laravel 13 and Filament 5 compatibility against official docs and package metadata, and then implement Milestone 0 and the secure P0 vertical slice before expanding. Continue through all milestones within the tools and execution budget available. Use the highest reasoning effort the host application actually provides, but do not rely on a model-name or effort string to grant permissions, tools, unlimited runtime or background execution.

If the repository is empty, create the project in the current workspace. If it is existing, preserve the existing project and implement incrementally. Make real changes and verify them. If you cannot finish, leave a coherent runnable checkpoint and detailed, truthful remaining-work ledger, rather than pretending that the full scope is complete. The user expects an actual product implementation with source code, documentation, SDK/CMS distribution and secure deployment, not only a project plan.

**END OF MASTER IMPLEMENTATION PROMPT.**
