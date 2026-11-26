# Build Shield CAPTCHA

Implement a self-operated CAPTCHA, bot management, and fraud prevention SaaS in this repository. Use the [detailed product specification](docs/requirements/product-specification.md) as the acceptance backlog. This prompt defines execution order, evidence, and workstation constraints. Preserve every requirement ID from the specification in the traceability ledger.

## Operating constraints

- Inspect the current checkout and toolchain. Preserve existing work and licensing. Work on local fixtures and disposable databases. Use synthetic data, generated development secrets, and mail capture. Keep real credentials and production access outside the work.
- The user is actively using this computer. Run one resource-intensive process at a time, use a single test worker and a single headless browser context when needed, keep database and worker memory bounded, and use low process priority where supported. Check memory pressure before heavier steps. Suspend load and compatibility matrices when they would interfere with interactive use. Close temporary servers and test processes. Run every test without opening desktop windows.
- Verify compatibility against official docs and real package resolution. Use Laravel 13, Filament 5, PHP 8.3 or later, and MySQL or MariaDB with InnoDB. Lock dependencies. Serve compiled or static browser assets. The cPanel/DirectAdmin baseline uses cron and needs no permanent workers, Redis, Docker, or Node at runtime.
- Build defensive controls and validate their behavior with owned, local fixtures. Keep detection evaluation and capacity testing bounded. Obtain any required external environment or provider access separately.

## Delivery milestones

| Milestone | Implement in working vertical slices | Evidence before progressing |
| --- | --- | --- |
| Foundation | Laravel and Filament, two panels, migrations, isolated test database, public site shell, status and preflight | Locked dependencies, application boots, clean migration, operator and customer route checks |
| Core protection | Verified customer identity, isolated tenants, sites, exact origin and action configuration, sandbox separation, scoped server keys, bounded checkbox proof of work, one-time verification, SMS pre-send and rate/quota controls | Owned browser-to-customer-backend flow, MySQL/MariaDB concurrency, tenant and authorization checks, recovery and revocation tests |
| Core release | Real dashboard metrics, EN/FA public quick start, OpenAPI, JS/PHP and WordPress/WooCommerce integrations, downloads manifest, shared-host guide and security/incident documentation | Rebuilt checksums, install and smoke checks, fresh local production-like setup, documented residual gaps |
| Competitive | Adaptive and invisible providers, accessible challenge alternatives, account and API protection, rules, feedback, analytics, alerts, billing, additional SDKs and CMS extensions | Each enabled workflow has a concrete test and safe recovery path |
| Enterprise and scale | Fraud, bot, scraper and verified-agent controls, enterprise teams and plans, self-hosted artifacts, optional SSO/edge/Redis/ML profiles | Verify each profile independently with its prerequisites. Keep the shared-host installation working throughout. |

## Invariants

- A browser can request and solve a challenge for a tenant-configured origin and action. Only the customer backend can consume the resulting token using a scoped credential and its own expected context. Scope and match tenant, site, origin, action and environment. Browser-origin headers provide a browser routing boundary. Domain ownership requires separate proof.
- Use a compatible maintained provider when available. Bound browser computation, expiry and attempts. Use standard crypto and high-entropy opaque credentials, store digests, and atomically enforce challenge issuance, token consumption, revocation and usage with InnoDB. Activity reports use redacted data and the production backend enforces every authorization decision.
- Treat protection as an integration: the customer must enforce the verdict before sending messages or carrying out protected actions. Bound and explain outage behavior. Keep sandbox authority separate. Record privacy, accessibility and security limits in the security model.
- Release artifacts from real package sources. Record the version, hash, size and tested compatibility. Gate public features and downloads on actual readiness. Core pilot readiness requires the listed acceptance evidence. Commercial readiness also depends on independent security review, measured capacity and operational checks.

## Evidence and handoff

Maintain [implementation status](IMPLEMENTATION_STATUS.md), [requirements traceability](REQUIREMENTS_TRACEABILITY.md), [decisions](DECISIONS.md), [deployment](DEPLOYMENT.md), and [security model](SECURITY_MODEL.md). Record verified work with commands and scope, partial work with the missing criteria, and blocked work with the exact dependency and recovery command. Include runnable local steps and real artifact paths. Continue through milestones while authorized and possible. When execution reaches a genuine limit, leave the latest coherent runnable checkpoint and explicit release gates. Begin with actual repository changes and verification.
